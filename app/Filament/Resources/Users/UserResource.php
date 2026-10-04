<?php

namespace App\Filament\Resources\Users;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\User;
use App\Services\Account\VerificationService;
use App\Services\Moderation\ModerationService;
use App\Support\PhoneNumber;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Foydalanuvchilar';

    protected static ?string $modelLabel = 'foydalanuvchi';

    protected static ?string $pluralModelLabel = 'Foydalanuvchilar';

    protected static ?string $recordTitleAttribute = 'username';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Profil')->columns(2)->columnSpanFull()->schema([
                TextInput::make('name')->label('Ism')->required()->maxLength(100),
                TextInput::make('username')->required()->maxLength(30)
                    ->rule('regex:/^(?=.*[a-z])[a-z0-9_]+$/')
                    ->unique(ignoreRecord: true),
                TextInput::make('email')->email()->nullable()->unique(ignoreRecord: true),
                Select::make('role')->label('Rol')->options(UserRole::class)->required()
                    ->helperText('Admin roli admin panelga to‘liq kirish huquqini beradi.'),
                Textarea::make('bio')->maxLength(300)->columnSpanFull(),
            ]),
            // Faqat yangi foydalanuvchi yaratishda: kirish ma'lumotlari va boshlang‘ich holat.
            Section::make('Kirish ma’lumotlari')->columns(2)->columnSpanFull()->visibleOn('create')->schema([
                TextInput::make('phone')->label('Telefon')->tel()->required()->placeholder('+998 90 123 45 67')
                    ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                        $phone = PhoneNumber::normalize($value);
                        if (! $phone) {
                            $fail('Telefon raqam noto‘g‘ri. Masalan: +998 90 123 45 67');
                        } elseif (User::withTrashed()->where('phone', $phone)->exists()) {
                            $fail('Bu telefon raqam bilan akkaunt allaqachon mavjud.');
                        }
                    }),
                TextInput::make('password')->label('Parol')->password()->revealable()->required()->minLength(8)
                    ->helperText('Foydalanuvchiga xavfsiz yo‘l bilan yetkazing; u keyin sozlamalarda o‘zgartira oladi.'),
                Select::make('status')->label('Holat')->options(UserStatus::class)->default(UserStatus::Active->value)->required(),
                Toggle::make('is_verified')->label('Tasdiqlangan akkaunt')->inline(false)
                    ->helperText('Ism yonida tasdiqlangan belgisi ko‘rinadi.'),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Profil')->columns(3)->columnSpanFull()->schema([
                TextEntry::make('name')->label('Ism'),
                TextEntry::make('username')->prefix('@')->url(fn (User $r) => $r->trashed() ? null : $r->profileUrl(), true),
                TextEntry::make('phone')->label('Telefon')->formatStateUsing(fn ($state) => PhoneNumber::format($state)),
                TextEntry::make('email')->placeholder('—'),
                TextEntry::make('role')->label('Rol')->badge(),
                IconEntry::make('verified_at')->label('Tasdiqlangan')->boolean()
                    ->state(fn (User $r) => $r->isVerified())
                    ->trueIcon(Heroicon::CheckBadge)->trueColor('primary')->falseIcon(Heroicon::OutlinedMinus)->falseColor('gray'),
                TextEntry::make('status')->label('Holat')->badge()->color(fn (User $r) => self::statusColor($r->status)),
                TextEntry::make('suspended_until')->label('Cheklov muddati')->dateTime('d.m.Y H:i')->placeholder('—'),
                TextEntry::make('followers_count')->label('Obunachilar'),
                TextEntry::make('following_count')->label('Obunalar'),
                TextEntry::make('created_at')->label('Ro‘yxatdan o‘tgan')->dateTime('d.m.Y H:i'),
                TextEntry::make('last_login_at')->label('Oxirgi kirish')->dateTime('d.m.Y H:i')->placeholder('—'),
                TextEntry::make('deleted_at')->label('O‘chirilgan')->dateTime('d.m.Y H:i')->placeholder('—'),
                TextEntry::make('bio')->columnSpanFull()->placeholder('—'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Ism')->searchable()->sortable()
                    ->description(fn (User $r) => '@'.$r->username)
                    ->icon(fn (User $r) => $r->isVerified() ? Heroicon::CheckBadge : null)->iconColor('primary')->iconPosition('after'),
                TextColumn::make('username')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')->label('Telefon')->searchable()
                    ->formatStateUsing(fn ($state) => PhoneNumber::format($state))->toggleable(),
                TextColumn::make('status')->label('Holat')->badge()->color(fn (User $r) => self::statusColor($r->status)),
                TextColumn::make('role')->label('Rol')->badge()->color(fn (User $r) => $r->isAdmin() ? 'warning' : 'gray'),
                IconColumn::make('verified_at')->label('Tasdiqlangan')->boolean()
                    ->state(fn (User $r) => $r->isVerified())
                    ->trueIcon(Heroicon::CheckBadge)->trueColor('primary')->falseIcon(Heroicon::OutlinedMinus)->falseColor('gray')
                    ->toggleable(),
                TextColumn::make('followers_count')->label('Obunachilar')->numeric()->sortable(),
                TextColumn::make('created_at')->label('Ro‘yxatdan o‘tgan')->dateTime('d.m.Y')->sortable(),
                TextColumn::make('last_active_at')->label('Faollik')->since()->sortable()->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Holat')->options(UserStatus::class),
                SelectFilter::make('role')->label('Rol')->options(UserRole::class),
                TernaryFilter::make('verified')->label('Tasdiqlangan')
                    ->trueLabel('Faqat tasdiqlanganlar')->falseLabel('Tasdiqlanmaganlar')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('verified_at'),
                        false: fn (Builder $query) => $query->whereNull('verified_at'),
                    ),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    ...self::verificationActions(),
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
                ]),
            ]);
    }

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
                ->visible(fn (User $r) => ! $r->trashed() && in_array($r->status, [UserStatus::Blocked, UserStatus::Suspended], true))
                ->requiresConfirmation()
                ->action(function (User $record) {
                    app(ModerationService::class)->activateUser($record, auth()->user());
                    Notification::make()->title('Foydalanuvchi faollashtirildi')->success()->send();
                }),
        ];
    }

    public static function statusColor(UserStatus $status): string
    {
        return match ($status) {
            UserStatus::Active => 'success',
            UserStatus::Suspended => 'warning',
            UserStatus::Blocked => 'danger',
            UserStatus::Deactivated => 'gray',
        };
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
