<?php

namespace App\Filament\Resources\Users;

use App\Enums\Gender;
use App\Enums\NotificationType;
use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\InitialsAvatarProvider;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\Category;
use App\Models\LoginEvent;
use App\Models\Tag;
use App\Models\User;
use App\Models\UserSession;
use App\Services\Account\VerificationService;
use App\Services\Feed\TasteService;
use App\Services\Media\ImageService;
use App\Services\Moderation\ModerationService;
use App\Services\Security\ImpersonationService;
use App\Services\Security\SessionService;
use App\Services\Social\AuditLogger;
use App\Services\Social\NotificationService;
use App\Support\PhoneNumber;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

/**
 * Foydalanuvchilar — to‘liq boshqaruv va kuzatuv:
 *  - yaratish va istalgan ma'lumotni tahrirlash (telefon, parol, avatar, rol, holat, tasdiq belgisi, ichki izoh);
 *  - 360° sahifa: ko‘rsatkichlar, kirishlar tarixi, faol qurilmalar, postlar, izohlar, shikoyatlar, admin amallari;
 *  - foydalanuvchi nomidan ko‘rish, barcha qurilmalardan chiqarish, xabar yuborish, bloklash/cheklash.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Foydalanuvchilar';

    protected static ?int $navigationSort = 1;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'foydalanuvchi';

    protected static ?string $pluralModelLabel = 'Foydalanuvchilar';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    // ---- Global qidiruv (Ctrl+K) ----

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'username', 'phone', 'email'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return ['Username' => '@'.$record->username, 'Telefon' => PhoneNumber::format($record->phone)];
    }

    public static function getNavigationBadge(): ?string
    {
        $online = self::onlineCount();

        return $online > 0 ? (string) $online : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Hozir onlayn';
    }

    public static function onlineCount(): int
    {
        return (int) cache()->remember('admin:online-users', 30, fn () => UserSession::query()->online()->whereNotNull('user_id')->distinct()->count('user_id'));
    }

    // ---- Forma: yaratish va tahrirlash ----

    public static function form(Schema $schema): Schema
    {
        $isSelf = fn (?User $record) => $record !== null && $record->is(auth()->user());

        return $schema->components([
            Section::make('Profil')->columns(2)->columnSpanFull()->schema([
                FileUpload::make('avatar_path')->label('Avatar')->avatar()->image()
                    ->disk(config('fikrlash.media.disk'))->directory('avatars')->visibility('public')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(5120)
                    // Saytdagidek: EXIF/GPS tozalanadi, kvadrat WebP'ga aylantiriladi.
                    ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file) => app(ImageService::class)->storeAvatar($file))
                    ->deleteUploadedFileUsing(fn () => null)
                    ->placeholder('Rasmni shu yerga tashlang yoki bosib tanlang')
                    ->columnSpanFull(),
                TextInput::make('name')->label('Ism')->required()->maxLength(config('fikrlash.profile.name_max')),
                TextInput::make('username')->label('Username')->required()->prefix('@')
                    ->minLength(config('fikrlash.username.min'))->maxLength(config('fikrlash.username.max'))
                    ->rule('regex:/^(?=.*[a-z])[a-z0-9_]+$/')
                    ->validationMessages(['regex' => 'Faqat kichik lotin harflari, raqam va _ (kamida bitta harf).'])
                    ->unique(ignoreRecord: true),
                TextInput::make('email')->label('Email')->email()->nullable()->unique(ignoreRecord: true),
                Select::make('gender')->label('Jinsi')->options(Gender::class)->placeholder('Ko‘rsatilmagan'),
                DatePicker::make('birth_date')->label('Tug‘ilgan sana')->native(false)->maxDate(now()->subYears(10))
                    ->displayFormat('d.m.Y')->placeholder('kk.oo.yyyy'),
                Textarea::make('bio')->label('O‘zi haqida')->maxLength(300)->rows(3)->columnSpanFull(),
            ]),

            Section::make('Kirish ma’lumotlari')->columns(2)->columnSpanFull()->schema([
                TextInput::make('phone')->label('Telefon')->tel()->required()->placeholder('+998 90 123 45 67')
                    ->formatStateUsing(fn (?string $state) => $state ? PhoneNumber::format($state) : $state)
                    ->rule(fn (?User $record) => function (string $attribute, $value, \Closure $fail) use ($record) {
                        $phone = PhoneNumber::normalize($value);
                        if (! $phone) {
                            $fail('Telefon raqam noto‘g‘ri. Masalan: +998 90 123 45 67');
                        } elseif (User::withTrashed()->where('phone', $phone)->when($record, fn ($q) => $q->whereKeyNot($record->id))->exists()) {
                            $fail('Bu telefon raqam bilan boshqa akkaunt mavjud.');
                        }
                    }),
                TextInput::make('password')
                    ->label(fn (string $operation) => $operation === 'create' ? 'Parol' : 'Yangi parol')
                    ->password()->revealable()->minLength(8)->maxLength(128)
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->helperText(fn (string $operation) => $operation === 'create'
                        ? 'Foydalanuvchiga xavfsiz yo‘l bilan yetkazing; u keyin sozlamalarda o‘zgartira oladi.'
                        : 'O‘zgartirmasangiz bo‘sh qoldiring. O‘zgartirilsa — foydalanuvchi barcha qurilmalardan chiqariladi.'),
            ]),

            Section::make('Huquq va holat')->columns(2)->columnSpanFull()->schema([
                Select::make('role')->label('Rol')->options(UserRole::class)->required()->default(UserRole::User->value)
                    ->disabled($isSelf)->helperText(fn (?User $record) => $isSelf($record)
                        ? 'O‘z rolingizni o‘zgartira olmaysiz.'
                        : 'Admin roli admin panelga to‘liq kirish huquqini beradi.'),
                Select::make('status')->label('Holat')->options(UserStatus::class)->required()->default(UserStatus::Active->value)
                    ->live()->disabled($isSelf)
                    ->helperText('Bloklashda foydalanuvchi barcha qurilmalardan chiqariladi va kontenti yashiriladi.'),
                DateTimePicker::make('suspended_until')->label('Cheklov muddati')->seconds(false)
                    ->minDate(now())->default(now()->addDays(3))
                    ->visible(fn (Get $get) => self::enumValue($get('status')) === UserStatus::Suspended->value)
                    ->required(fn (Get $get) => self::enumValue($get('status')) === UserStatus::Suspended->value),
                Toggle::make('is_verified')->label('Tasdiqlangan akkaunt')->inline(false)
                    ->afterStateHydrated(fn (Toggle $component, ?User $record) => $component->state($record?->isVerified() ?? false))
                    ->helperText('Ism yonida tasdiqlangan belgisi ko‘rinadi.'),
            ]),

            Section::make('Ichki izoh')->description('Faqat adminlar ko‘radi; bazada shifrlangan holda saqlanadi.')
                ->columnSpanFull()->collapsible()->schema([
                    Textarea::make('admin_note')->hiddenLabel()->rows(3)->maxLength(5000)
                        ->placeholder('Masalan: 2026-10-05 — spam uchun ogohlantirildi.'),
                ]),
        ]);
    }

    // ---- 360° ko‘rinish ----

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->schema([
                Grid::make(['default' => 1, 'md' => 12])->schema([
                    ImageEntry::make('avatar')->hiddenLabel()->circular()->imageSize(88)
                        ->state(fn (User $r) => InitialsAvatarProvider::urlFor($r))
                        ->columnSpan(['md' => 2]),
                    Grid::make(1)->columnSpan(['md' => 6])->schema([
                        TextEntry::make('name')->hiddenLabel()->size(TextSize::Large)->weight(FontWeight::Bold)
                            ->icon(fn (User $r) => $r->isVerified() ? Heroicon::CheckBadge : null)->iconColor('primary')->iconPosition('after'),
                        TextEntry::make('username')->hiddenLabel()->prefix('@')->color('gray')
                            ->url(fn (User $r) => $r->trashed() ? null : $r->profileUrl(), true),
                        TextEntry::make('bio')->hiddenLabel()->placeholder('Bio yozilmagan')->color('gray'),
                    ]),
                    Grid::make(2)->columnSpan(['md' => 4])->schema([
                        TextEntry::make('status')->label('Holat')->badge()->color(fn (User $r) => self::statusColor($r->status)),
                        TextEntry::make('role')->label('Rol')->badge()->color(fn (User $r) => $r->isAdmin() ? 'warning' : 'gray'),
                        TextEntry::make('presence')->label('Faollik')
                            ->state(fn (User $r) => self::presence($r))
                            ->badge()->color(fn (User $r) => self::isOnline($r) ? 'success' : 'gray'),
                        TextEntry::make('suspended_until')->label('Cheklov tugaydi')->dateTime('d.m.Y H:i')
                            ->visible(fn (User $r) => $r->status === UserStatus::Suspended),
                    ]),
                ]),
            ]),

            Section::make('Ko‘rsatkichlar')->columnSpanFull()->columns(['default' => 2, 'md' => 4, 'xl' => 8])->schema([
                TextEntry::make('posts_total')->label('Postlar')->state(fn (User $r) => Number::format($r->posts()->count())),
                TextEntry::make('comments_total')->label('Izohlar')->state(fn (User $r) => Number::format($r->comments()->count())),
                TextEntry::make('likes_received')->label('Olgan like')->state(fn (User $r) => Number::format((int) $r->posts()->sum('likes_count'))),
                TextEntry::make('views_received')->label('Ko‘rishlar')->state(fn (User $r) => Number::abbreviate((int) $r->posts()->sum('views_count'))),
                TextEntry::make('followers_count')->label('Obunachilar')->numeric(),
                TextEntry::make('following_count')->label('Obunalar')->numeric(),
                TextEntry::make('likes_given')->label('Bergan like')->state(fn (User $r) => Number::format($r->likes()->count())),
                TextEntry::make('reports_against')->label('Unga shikoyatlar')
                    ->state(fn (User $r) => self::reportsSummary($r))
                    ->color(fn (User $r) => $r->reportsAgainst()->where('status', ReportStatus::Pending)->exists() ? 'danger' : null),
            ]),

            Section::make('Kirish va xavfsizlik')->columnSpanFull()->columns(['default' => 1, 'md' => 3])->schema([
                TextEntry::make('phone')->label('Telefon')->formatStateUsing(fn ($state) => PhoneNumber::format($state))->copyable(),
                TextEntry::make('email')->label('Email')->placeholder('—')->copyable(),
                TextEntry::make('phone_verified_at')->label('Telefon tasdiqlangan')->dateTime('d.m.Y H:i')->placeholder('—'),
                TextEntry::make('last_login_at')->label('Oxirgi kirish')->dateTime('d.m.Y H:i')->placeholder('—'),
                TextEntry::make('last_login_from')->label('Oxirgi kirgan joyi')
                    ->state(fn (User $r) => self::lastLogin($r))->placeholder('—'),
                TextEntry::make('last_active_at')->label('Oxirgi faollik')->since()->placeholder('—')
                    ->tooltip(fn (User $r) => $r->last_active_at?->format('d.m.Y H:i')),
                TextEntry::make('failed_logins')->label('Noto‘g‘ri parol (30 kun)')
                    ->state(fn (User $r) => $r->loginEvents()->whereIn('event', ['failed', 'blocked'])->where('created_at', '>=', now()->subDays(30))->count())
                    ->color(fn ($state) => $state >= 5 ? 'danger' : null),
                TextEntry::make('sessions_total')->label('Faol brauzer sessiyalari')->state(fn (User $r) => $r->sessions()->count()),
                TextEntry::make('tokens_total')->label('API tokenlar (ilova)')->state(fn (User $r) => $r->tokens()->count()),
                TextEntry::make('created_at')->label('Ro‘yxatdan o‘tgan')->dateTime('d.m.Y H:i'),
                TextEntry::make('verified_at')->label('Tasdiqlangan')->dateTime('d.m.Y')->placeholder('Yo‘q')
                    ->helperText(fn (User $r) => $r->verified_by ? 'Admin: @'.User::withTrashed()->find($r->verified_by)?->username : null),
                TextEntry::make('deleted_at')->label('O‘chirilgan')->dateTime('d.m.Y H:i')->placeholder('—')
                    ->visible(fn (User $r) => $r->trashed()),
            ]),

            Section::make('Shaxsiy ma’lumotlar')->columnSpanFull()->columns(['default' => 1, 'md' => 3])->collapsible()->schema([
                TextEntry::make('gender')->label('Jinsi')->placeholder('Ko‘rsatilmagan'),
                TextEntry::make('birth_date')->label('Tug‘ilgan sana')->placeholder('Ko‘rsatilmagan')
                    ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('d.m.Y').' ('.Carbon::parse($state)->age.' yosh)' : null),
                TextEntry::make('admin_note')->label('Ichki izoh')->placeholder('—')->columnSpanFull(),
            ]),

            // Tavsiya algoritmi nimani o‘rgangan — faqat admin uchun (foydalanuvchiga ko‘rsatilmaydi).
            Section::make('Qiziqishlari (tavsiya algoritmi)')->columnSpanFull()->columns(['default' => 1, 'md' => 3])->collapsible()->collapsed()->schema([
                TextEntry::make('taste_topics')->label('Ko‘p o‘qiydigan mavzular')->badge()->color('primary')
                    ->state(fn (User $r) => self::tasteNames($r, TasteService::CATEGORY))->placeholder('Ma’lumot hali yetarli emas'),
                TextEntry::make('taste_tags')->label('Teglar')->badge()->color('gray')
                    ->state(fn (User $r) => self::tasteNames($r, TasteService::TAG))->placeholder('—'),
                TextEntry::make('taste_authors')->label('Ko‘p o‘qiydigan mualliflar')->badge()->color('gray')
                    ->state(fn (User $r) => self::tasteNames($r, TasteService::AUTHOR))->placeholder('—'),
            ]),
        ]);
    }

    // ---- Ro‘yxat ----

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar')->label('')->circular()->imageSize(36)
                    ->state(fn (User $r) => InitialsAvatarProvider::urlFor($r)),
                TextColumn::make('name')->label('Ism')->searchable()->sortable()
                    ->description(fn (User $r) => '@'.$r->username)
                    ->icon(fn (User $r) => $r->isVerified() ? Heroicon::CheckBadge : null)->iconColor('primary')->iconPosition('after'),
                TextColumn::make('username')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')->label('Telefon')->searchable()
                    ->formatStateUsing(fn ($state) => PhoneNumber::format($state))->toggleable(),
                TextColumn::make('email')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->label('Holat')->badge()->color(fn (User $r) => self::statusColor($r->status)),
                TextColumn::make('role')->label('Rol')->badge()->color(fn (User $r) => $r->isAdmin() ? 'warning' : 'gray')->toggleable(),
                TextColumn::make('posts_count')->label('Postlar')->counts('posts')->numeric()->sortable(),
                TextColumn::make('followers_count')->label('Obunachilar')->numeric()->sortable()->toggleable(),
                TextColumn::make('last_active_at')->label('Faollik')->sortable()
                    ->state(fn (User $r) => self::presence($r))
                    ->color(fn (User $r) => self::isOnline($r) ? 'success' : 'gray')
                    ->icon(fn (User $r) => self::isOnline($r) ? Heroicon::Signal : null),
                TextColumn::make('last_login_at')->label('Oxirgi kirish')->dateTime('d.m.Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Ro‘yxatdan o‘tgan')->dateTime('d.m.Y')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Holat')->options(UserStatus::class)->multiple(),
                SelectFilter::make('role')->label('Rol')->options(UserRole::class),
                TernaryFilter::make('verified')->label('Tasdiqlangan')
                    ->trueLabel('Faqat tasdiqlanganlar')->falseLabel('Tasdiqlanmaganlar')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('verified_at'),
                        false: fn (Builder $query) => $query->whereNull('verified_at'),
                    ),
                SelectFilter::make('activity')->label('Faollik')
                    ->options(['online' => 'Hozir onlayn', '1' => 'Bugun faol', '7' => '7 kunda faol', '30' => '30 kunda faol', 'inactive' => '30+ kun faol emas'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'online' => $query->whereIn('id', UserSession::query()->online()->whereNotNull('user_id')->select('user_id')),
                        '1', '7', '30' => $query->where('last_active_at', '>=', now()->subDays((int) $data['value'])),
                        'inactive' => $query->where(fn ($q) => $q->whereNull('last_active_at')->orWhere('last_active_at', '<', now()->subDays(30))),
                        default => $query,
                    }),
                Filter::make('registered')->label('Ro‘yxatdan o‘tgan sana')
                    ->schema([
                        DatePicker::make('from')->label('Dan'),
                        DatePicker::make('until')->label('Gacha'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d)))
                    ->indicateUsing(fn (array $data) => array_filter([
                        ($data['from'] ?? null) ? 'Dan: '.Carbon::parse($data['from'])->format('d.m.Y') : null,
                        ($data['until'] ?? null) ? 'Gacha: '.Carbon::parse($data['until'])->format('d.m.Y') : null,
                    ])),
                Filter::make('reported')->label('Ustidan shikoyat bor')->toggle()
                    ->query(fn (Builder $query) => $query->whereHas('reportsAgainst', fn ($q) => $q->where('status', ReportStatus::Pending))),
                Filter::make('failed_logins')->label('Ko‘p noto‘g‘ri parol (24 soat)')->toggle()
                    ->query(fn (Builder $query) => $query->whereIn('id', LoginEvent::query()->select('user_id')
                        ->whereIn('event', ['failed', 'blocked'])->where('created_at', '>=', now()->subDay())
                        ->whereNotNull('user_id')->groupBy('user_id')->havingRaw('COUNT(*) >= 3'))),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    ...self::verificationActions(),
                    ...self::accountActions(),
                    ...self::moderationActions(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('verifyMany')
                        ->label('Tasdiqlash')->icon(Heroicon::OutlinedCheckBadge)->color('primary')
                        ->requiresConfirmation()->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $count = $records->reject(fn (User $u) => $u->trashed())
                                ->filter(fn (User $u) => app(VerificationService::class)->verify($u, auth()->user()))->count();
                            Notification::make()->title("{$count} ta akkaunt tasdiqlandi")->success()->send();
                        }),
                    BulkAction::make('unverifyMany')
                        ->label('Tasdiqni olib tashlash')->icon(Heroicon::OutlinedXCircle)->color('gray')
                        ->requiresConfirmation()->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $count = $records->filter(fn (User $u) => app(VerificationService::class)->unverify($u, auth()->user()))->count();
                            Notification::make()->title("{$count} ta akkauntdan belgi olindi")->success()->send();
                        }),
                    BulkAction::make('messageMany')
                        ->label('Xabar yuborish')->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                        ->schema([Textarea::make('message')->label('Xabar (bildirishnoma sifatida boradi)')->required()->maxLength(500)])
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, array $data) {
                            $records->reject(fn (User $u) => $u->trashed())->each(fn (User $u) => self::sendMessage($u, $data['message']));
                            Notification::make()->title($records->count().' ta foydalanuvchiga xabar yuborildi')->success()->send();
                        }),
                    BulkAction::make('blockMany')
                        ->label('Bloklash')->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                        ->requiresConfirmation()->deselectRecordsAfterCompletion()
                        ->modalDescription('Tanlangan foydalanuvchilar tizimdan chiqariladi va kontenti yashiriladi. Adminlar bloklanmaydi.')
                        ->action(function (Collection $records) {
                            $count = $records->reject(fn (User $u) => $u->isAdmin() || $u->trashed() || $u->status === UserStatus::Blocked)
                                ->each(fn (User $u) => app(ModerationService::class)->blockUser($u, auth()->user()))->count();
                            Notification::make()->title("{$count} ta foydalanuvchi bloklandi")->success()->send();
                        }),
                    BulkAction::make('exportCsv')
                        ->label('CSV yuklab olish')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                        ->action(fn (Collection $records) => self::exportCsv($records)),
                ]),
            ]);
    }

    // ---- Amallar ----

    /** @return list<Action> */
    public static function verificationActions(): array
    {
        return [
            Action::make('verify')
                ->label('Tasdiqlash')
                ->icon(Heroicon::OutlinedCheckBadge)->color('primary')
                ->visible(fn (User $r) => ! $r->trashed() && ! $r->isVerified())
                ->requiresConfirmation()
                ->modalDescription('Foydalanuvchi ismi yonida tasdiqlangan belgisi paydo bo‘ladi va unga bildirishnoma boradi.')
                ->action(function (User $record) {
                    app(VerificationService::class)->verify($record, auth()->user());
                    Notification::make()->title('Akkaunt tasdiqlandi')->success()->send();
                }),
            Action::make('unverify')
                ->label('Tasdiqni olib tashlash')
                ->icon(Heroicon::OutlinedXCircle)->color('gray')
                ->visible(fn (User $r) => $r->isVerified())
                ->requiresConfirmation()
                ->action(function (User $record) {
                    app(VerificationService::class)->unverify($record, auth()->user());
                    Notification::make()->title('Tasdiq belgisi olib tashlandi')->success()->send();
                }),
        ];
    }

    /** @return list<Action> */
    public static function accountActions(): array
    {
        return [
            Action::make('impersonate')
                ->label('Uning nomidan ko‘rish')
                ->icon(Heroicon::OutlinedEye)->color('warning')
                ->visible(fn (User $r) => ! $r->trashed() && ! $r->isAdmin() && $r->canSignIn())
                ->requiresConfirmation()
                ->modalHeading(fn (User $r) => '@'.$r->username.' nomidan saytni ko‘rish')
                ->modalDescription('Sayt aynan shu foydalanuvchi ko‘rgandek ochiladi (lenta, bildirishnomalar). Parol, telefon va akkauntni o‘zgartirish bu rejimda taqiqlangan. Kirish audit log va kirishlar tarixiga yoziladi; pastdagi tugma orqali admin panelga qaytasiz.')
                ->modalSubmitActionLabel('Kirish')
                ->action(function (User $record) {
                    app(ImpersonationService::class)->start(auth()->user(), $record, request());

                    return redirect()->route('home');
                }),
            Action::make('message')
                ->label('Xabar yuborish')
                ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                ->visible(fn (User $r) => ! $r->trashed())
                ->schema([Textarea::make('message')->label('Xabar (bildirishnoma sifatida boradi)')->required()->maxLength(500)])
                ->action(function (User $record, array $data) {
                    self::sendMessage($record, $data['message']);
                    Notification::make()->title('Xabar yuborildi')->success()->send();
                }),
            Action::make('setPassword')
                ->label('Parolni o‘zgartirish')
                ->icon(Heroicon::OutlinedKey)
                ->visible(fn (User $r) => ! $r->trashed())
                ->schema([
                    TextInput::make('password')->label('Yangi parol')->password()->revealable()->required()->minLength(8)->maxLength(128),
                ])
                ->modalDescription('Foydalanuvchi barcha qurilmalardan chiqariladi va yangi parol bilan kirishi kerak bo‘ladi.')
                ->action(function (User $record, array $data) {
                    $record->forceFill(['password' => $data['password']])->save();
                    app(AuditLogger::class)->log('user.password_set_by_admin', $record, [], ['password_changed' => true]);
                    if (! $record->is(auth()->user())) {
                        app(SessionService::class)->terminateAll($record, auth()->user());
                    }
                    Notification::make()->title('Parol o‘zgartirildi')->success()->send();
                }),
            Action::make('logoutEverywhere')
                ->label('Barcha qurilmalardan chiqarish')
                ->icon(Heroicon::OutlinedArrowRightStartOnRectangle)->color('gray')
                ->visible(fn (User $r) => ! $r->trashed() && ! $r->is(auth()->user()))
                ->requiresConfirmation()
                ->modalDescription('Barcha brauzer sessiyalari va mobil ilova tokenlari bekor qilinadi.')
                ->action(function (User $record) {
                    $count = app(SessionService::class)->terminateAll($record, auth()->user());
                    Notification::make()->title("{$count} ta sessiya/token bekor qilindi")->success()->send();
                }),
        ];
    }

    /** @return list<Action> */
    public static function moderationActions(): array
    {
        return [
            Action::make('suspend')
                ->label('Vaqtincha cheklash')
                ->icon(Heroicon::OutlinedPause)->color('warning')
                ->visible(fn (User $r) => ! $r->trashed() && ! $r->isAdmin() && $r->status === UserStatus::Active)
                ->schema([
                    DateTimePicker::make('until')->label('Qachongacha')->required()->minDate(now())->default(now()->addDays(3)),
                    Textarea::make('reason')->label('Sabab (foydalanuvchiga ko‘rinadi)')->maxLength(300),
                ])
                ->action(function (User $record, array $data) {
                    app(ModerationService::class)->suspendUser($record, Carbon::parse($data['until']), auth()->user(), $data['reason'] ?? null);
                    Notification::make()->title('Foydalanuvchi cheklandi')->success()->send();
                }),
            Action::make('block')
                ->label('Bloklash')
                ->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                ->visible(fn (User $r) => ! $r->trashed() && ! $r->isAdmin() && $r->status !== UserStatus::Blocked)
                ->requiresConfirmation()
                ->modalDescription('Foydalanuvchi tizimdan chiqariladi, barcha tokenlari bekor qilinadi va kontenti yashiriladi.')
                ->schema([Textarea::make('reason')->label('Sabab (ichki)')->maxLength(300)])
                ->action(function (User $record, array $data) {
                    app(ModerationService::class)->blockUser($record, auth()->user(), $data['reason'] ?? null);
                    Notification::make()->title('Foydalanuvchi bloklandi')->success()->send();
                }),
            Action::make('activate')
                ->label('Faollashtirish')
                ->icon(Heroicon::OutlinedCheckCircle)->color('success')
                ->visible(fn (User $r) => ! $r->trashed() && in_array($r->status, [UserStatus::Blocked, UserStatus::Suspended, UserStatus::Deactivated], true))
                ->requiresConfirmation()
                ->action(function (User $record) {
                    app(ModerationService::class)->activateUser($record, auth()->user());
                    Notification::make()->title('Foydalanuvchi faollashtirildi')->success()->send();
                }),
        ];
    }

    // ---- Yordamchilar ----

    public static function statusColor(UserStatus $status): string
    {
        return match ($status) {
            UserStatus::Active => 'success',
            UserStatus::Suspended => 'warning',
            UserStatus::Blocked => 'danger',
            UserStatus::Deactivated => 'gray',
        };
    }

    public static function isOnline(User $user): bool
    {
        return $user->last_active_at !== null && $user->last_active_at->gt(now()->subMinutes(10));
    }

    public static function presence(User $user): string
    {
        return match (true) {
            self::isOnline($user) => 'Onlayn',
            $user->last_active_at !== null => $user->last_active_at->diffForHumans(),
            default => 'Hech qachon',
        };
    }

    public static function enumValue(mixed $value): ?string
    {
        return $value instanceof BackedEnum ? (string) $value->value : ($value === null ? null : (string) $value);
    }

    private static function lastLogin(User $user): ?string
    {
        $event = $user->loginEvents()->where('event', 'login')->latest('id')->first();

        return $event ? trim(($event->device ?? '').' · '.($event->ip ?? ''), ' ·') : null;
    }

    private static function reportsSummary(User $user): string
    {
        $total = $user->reportsAgainst()->count();
        $pending = $user->reportsAgainst()->where('status', ReportStatus::Pending)->count();

        return $total === 0 ? '0' : "{$total} ({$pending} kutilmoqda)";
    }

    /** @return list<string> */
    private static function tasteNames(User $user, string $kind): array
    {
        $lifts = array_filter(app(TasteService::class)->profile($user->id)[$kind], fn ($l) => $l > 1.05);
        arsort($lifts);
        $ids = array_slice(array_keys($lifts), 0, 6);
        if ($ids === []) {
            return [];
        }

        $names = match ($kind) {
            TasteService::CATEGORY => Category::query()->whereIn('id', $ids)->pluck('name', 'id'),
            TasteService::TAG => Tag::query()->whereIn('id', $ids)->pluck('name', 'id')->map(fn ($n) => '#'.$n),
            default => User::query()->whereIn('id', $ids)->pluck('username', 'id')->map(fn ($n) => '@'.$n),
        };

        return collect($ids)->map(fn ($id) => $names[$id] ?? null)->filter()->values()->all();
    }

    public static function sendMessage(User $user, string $message): void
    {
        app(NotificationService::class)->notify($user, NotificationType::System, null, null, ['message' => $message]);
        app(AuditLogger::class)->log('user.messaged', $user, [], ['message' => mb_substr($message, 0, 200)]);
    }

    private static function exportCsv(Collection $records)
    {
        app(AuditLogger::class)->log('users.exported', null, [], ['count' => $records->count()]);

        return response()->streamDownload(function () use ($records) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // Excel uchun UTF-8 BOM
            fputcsv($out, ['ID', 'Ism', 'Username', 'Telefon', 'Email', 'Holat', 'Rol', 'Tasdiqlangan', 'Obunachilar', 'Ro‘yxatdan o‘tgan', 'Oxirgi faollik']);
            foreach ($records as $u) {
                fputcsv($out, [
                    $u->id, $u->name, $u->username, $u->phone, $u->email, $u->status->value, $u->role->value,
                    $u->isVerified() ? 'ha' : 'yo‘q', $u->followers_count,
                    $u->created_at?->format('Y-m-d H:i'), $u->last_active_at?->format('Y-m-d H:i'),
                ]);
            }
            fclose($out);
        }, 'foydalanuvchilar-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PostsRelationManager::class,
            RelationManagers\CommentsRelationManager::class,
            RelationManagers\LoginEventsRelationManager::class,
            RelationManagers\SessionsRelationManager::class,
            RelationManagers\ReportsRelationManager::class,
            RelationManagers\AuditTrailRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'view' => ViewUser::route('/{record}'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
