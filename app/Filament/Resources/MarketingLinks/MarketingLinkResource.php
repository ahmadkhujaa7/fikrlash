<?php

namespace App\Filament\Resources\MarketingLinks;

use App\Filament\Resources\MarketingLinks\Pages\CreateMarketingLink;
use App\Filament\Resources\MarketingLinks\Pages\EditMarketingLink;
use App\Filament\Resources\MarketingLinks\Pages\ListMarketingLinks;
use App\Filament\Resources\MarketingLinks\Pages\ViewMarketingLink;
use App\Filament\Resources\MarketingLinks\RelationManagers\SignupsRelationManager;
use App\Models\MarketingLink;
use App\Services\Marketing\MarketingService;
use App\Services\Monetization\MonetizationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Kampaniya havolalari: har bir reklama joyi uchun alohida havola (masalan "Tg1" → fikrlash.uz/r/tg1).
 * Bosishlar, noyob tashrifchilar, ro‘yxatni boshlaganlar, ro‘yxatdan o‘tganlar, konversiya va
 * bitta a’zo narxi (CPA) hisoblanadi. Har biri uchun QR kod (offline reklama uchun).
 */
class MarketingLinkResource extends Resource
{
    protected static ?string $model = MarketingLink::class;

    protected static ?string $slug = 'marketing-links';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Reklama havolalari';

    protected static ?string $modelLabel = 'havola';

    protected static ?string $pluralModelLabel = 'Reklama havolalari';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('creator')->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'code', 'partner'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Havola')->columnSpanFull()->columns(2)->schema([
                TextInput::make('name')->label('Nomi')->required()->maxLength(80)
                    ->placeholder('Tg1 — @kanal_nomi')
                    ->helperText('O‘zingiz uchun: qaysi kanal / bloger / reklama ekanini tanib olasiz.')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set, string $operation) {
                        if ($operation === 'create' && blank($get('code'))) {
                            $set('code', self::suggestCode((string) $state));
                        }
                    }),
                TextInput::make('code')->label('Havola kodi')->required()->maxLength(40)
                    ->prefix(preg_replace('#^https?://#', '', url('/r')).'/')
                    ->regex('/^[a-z0-9_-]+$/')
                    ->unique(MarketingLink::class, 'code', ignoreRecord: true)
                    ->dehydrateStateUsing(fn (?string $state) => mb_strtolower(trim((string) $state)))
                    ->validationMessages([
                        'regex' => 'Faqat kichik lotin harflari, raqamlar, "-" va "_" belgilari.',
                        'unique' => 'Bu kod band (arxivdagi havolalar ham hisobga olinadi).',
                    ])
                    ->helperText(fn (string $operation) => $operation === 'edit'
                        ? 'Diqqat: kod o‘zgarsa, avval tarqatilgan havola ishlamay qoladi.'
                        : 'Masalan: tg1. Kichik lotin harflari, raqamlar, "-" va "_".'),
                Select::make('channel')->label('Kanal turi')->options(MarketingLink::CHANNELS)->required()->default('telegram'),
                TextInput::make('target')->label('Qayerga olib boradi')->required()->maxLength(255)->default('/register')
                    ->datalist(array_keys(MarketingLink::TARGETS))
                    ->regex('#^/(?!/)\S*$#')
                    ->validationMessages(['regex' => 'Sayt ichidagi yo‘l bo‘lishi kerak: "/" bilan boshlanadi, masalan /register.'])
                    ->helperText('/register — ro‘yxatdan o‘tish (tavsiya), / — lenta, yoki istalgan maqola manzili (domen yozilmaydi).'),
            ]),
            Section::make('Hamkor va xarajat')->columnSpanFull()->columns(2)->schema([
                TextInput::make('partner')->label('Hamkor / joy')->maxLength(120)->placeholder('@kanal_nomi yoki bloger ismi'),
                TextInput::make('contact')->label('Aloqa')->maxLength(120)->placeholder('@kanal_admini yoki +998 90 …'),
                TextInput::make('cost')->label('Reklama narxi')->numeric()->minValue(0)->maxValue(1_000_000_000)->suffix('so‘m')
                    ->helperText('Kiritilsa — bitta ro‘yxatdan o‘tish necha so‘mga tushgani (CPA) hisoblanadi.'),
                DateTimePicker::make('expires_at')->label('Amal qilish muddati')->seconds(false)
                    ->helperText('Muddat o‘tgach bosishlar hisoblanmaydi (havola baribir saytga olib boradi).'),
            ]),
            Section::make('Qo‘shimcha')->columnSpanFull()->schema([
                Textarea::make('welcome')->label('Salomlashuv matni (ixtiyoriy)')->rows(2)->maxLength(200)
                    ->placeholder('@kanal_nomi o‘quvchilari, Fikrlash’ga xush kelibsiz!')
                    ->helperText('Shu havola orqali kelganlarga ro‘yxatdan o‘tish sahifasining tepasida ko‘rsatiladi.'),
                Textarea::make('notes')->label('Ichki izoh')->rows(2)->maxLength(2000)
                    ->helperText('Faqat adminlar ko‘radi: kelishuv shartlari, reklama sanasi va h.k.'),
                Toggle::make('is_active')->label('Faol')->default(true)
                    ->helperText('O‘chirilsa — bosishlar va ro‘yxatdan o‘tishlar shu havolaga yozilmaydi.'),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Havola')->columnSpanFull()->columns(['default' => 1, 'lg' => 3])->schema([
                Grid::make(['default' => 1, 'md' => 2])->columnSpan(['default' => 1, 'lg' => 2])->schema([
                    TextEntry::make('url')->label('Ulashiladigan havola')->state(fn (MarketingLink $r) => $r->url())
                        ->copyable()->copyMessage('Havola nusxalandi')->weight('bold')->columnSpanFull()
                        ->helperText(fn (MarketingLink $r) => 'Istalgan sahifaga ?ref='.$r->code.' qo‘shilsa ham shu havolaga yoziladi.'),
                    TextEntry::make('status')->label('Holat')->badge()
                        ->state(fn (MarketingLink $r) => self::status($r)[0])
                        ->color(fn (MarketingLink $r) => self::status($r)[1]),
                    TextEntry::make('channel')->label('Kanal turi')->badge()
                        ->formatStateUsing(fn (MarketingLink $r) => $r->channelLabel())
                        ->color(fn (MarketingLink $r) => MarketingLink::CHANNEL_COLORS[$r->channel] ?? 'gray'),
                    TextEntry::make('partner')->label('Hamkor / joy')->placeholder('—'),
                    TextEntry::make('contact')->label('Aloqa')->placeholder('—')->copyable(),
                    TextEntry::make('target')->label('Qayerga olib boradi')
                        ->formatStateUsing(fn (MarketingLink $r) => MarketingLink::TARGETS[$r->targetPath()] ?? $r->targetPath()),
                    TextEntry::make('cost')->label('Reklama narxi')->placeholder('—')
                        ->formatStateUsing(fn ($state) => MonetizationService::money((float) $state)),
                    TextEntry::make('expires_at')->label('Amal qilish muddati')->dateTime('d.m.Y H:i')->placeholder('Cheklanmagan'),
                    TextEntry::make('created_at')->label('Yaratilgan')->dateTime('d.m.Y H:i')
                        ->helperText(fn (MarketingLink $r) => $r->creator ? 'Admin: @'.$r->creator->username : null),
                    TextEntry::make('welcome')->label('Salomlashuv matni')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('notes')->label('Ichki izoh')->placeholder('—')->columnSpanFull(),
                ]),
                Grid::make(1)->schema([
                    ImageEntry::make('qr')->label('QR kod')->imageHeight(180)->alignCenter()
                        ->state(fn (MarketingLink $r) => route('marketing.qr', [$r, 'png'])),
                    Actions::make([
                        Action::make('qrPng')->label('PNG')->icon(Heroicon::OutlinedArrowDownTray)->size('sm')->color('gray')
                            ->url(fn (MarketingLink $r) => route('marketing.qr', [$r, 'png', 'download' => 1])),
                        Action::make('qrSvg')->label('SVG (bosmaxona uchun)')->icon(Heroicon::OutlinedArrowDownTray)->size('sm')->color('gray')
                            ->url(fn (MarketingLink $r) => route('marketing.qr', [$r, 'svg', 'download' => 1])),
                    ])->alignCenter(),
                ]),
            ]),
            Section::make('Kim bosdi')->description('Bosishlar bo‘yicha: qurilma, tizim, brauzer, qaysi ilova ichidan ochilgani va qaysi saytdan kelgani.')
                ->columnSpanFull()->columns(['default' => 1, 'md' => 2, 'xl' => 5])->collapsible()
                ->schema(collect([
                    'device' => 'Qurilma',
                    'os' => 'Operatsion tizim',
                    'app' => 'Qaysi ilovadan',
                    'browser' => 'Brauzer',
                    'referrer' => 'Qaysi saytdan',
                ])->map(fn (string $label, string $field) => TextEntry::make('breakdown_'.$field)->label($label)
                    ->state(fn (MarketingLink $r) => self::breakdown($r, $field))
                    ->listWithLineBreaks()->placeholder('Hali bosish yo‘q'))->values()->all()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nomi')->searchable(['name', 'partner', 'code'])->sortable()->weight('medium')
                    ->description(fn (MarketingLink $r) => $r->partner),
                TextColumn::make('code')->label('Havola')
                    ->formatStateUsing(fn (MarketingLink $r) => '/r/'.$r->code)
                    ->tooltip(fn (MarketingLink $r) => $r->url())
                    ->copyable()->copyableState(fn (MarketingLink $r) => $r->url())->copyMessage('Havola nusxalandi')
                    ->icon(Heroicon::OutlinedClipboardDocument)->iconPosition('after')->color('primary')
                    ->description(fn (MarketingLink $r) => ($status = self::status($r))[0] !== 'Faol' ? $status[0] : null),
                TextColumn::make('channel')->label('Kanal')->badge()
                    ->formatStateUsing(fn (MarketingLink $r) => $r->channelLabel())
                    ->color(fn (MarketingLink $r) => MarketingLink::CHANNEL_COLORS[$r->channel] ?? 'gray'),
                TextColumn::make('clicks_count')->label('Bosishlar')->numeric()->sortable()
                    ->description(fn (MarketingLink $r) => Number::format($r->visitors_count).' noyob'),
                TextColumn::make('starts_count')->label('Ro‘yxatni boshladi')->numeric()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('signups_count')->label('Ro‘yxatdan o‘tdi')->numeric()->sortable()->weight('bold')->color('success')
                    ->description(fn (MarketingLink $r) => $r->conversion() !== null ? 'konversiya '.$r->conversion().'%' : null),
                TextColumn::make('cost')->label('Xarajat')->sortable()->placeholder('—')->toggleable()
                    ->formatStateUsing(fn ($state) => MonetizationService::money((float) $state))
                    ->description(fn (MarketingLink $r) => $r->costPerSignup() !== null ? '1 a’zo: '.MonetizationService::money(round($r->costPerSignup())) : null),
                ToggleColumn::make('is_active')->label('Faol')->disabled(fn (MarketingLink $r) => $r->trashed()),
                TextColumn::make('last_click_at')->label('Oxirgi bosish')->since()->sortable()->placeholder('Hali yo‘q')->toggleable(),
                TextColumn::make('created_at')->label('Yaratilgan')->dateTime('d.m.Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('channel')->label('Kanal turi')->options(MarketingLink::CHANNELS)->multiple(),
                TernaryFilter::make('is_active')->label('Faol'),
                TrashedFilter::make()->label('Arxiv'),
            ])
            ->emptyStateHeading('Hali reklama havolasi yo‘q')
            ->emptyStateDescription('Har bir kanal yoki bloger uchun alohida havola yarating (masalan "Tg1") — kim qancha odam olib kelganini ko‘rasiz.')
            ->emptyStateIcon(Heroicon::OutlinedLink)
            ->recordActions([
                ViewAction::make()->label('Statistika'),
                self::qrAction(),
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make()->label('Arxivlash')->modalHeading('Havola arxivlansinmi?')
                        ->modalDescription('Havola endi bosishlarni hisoblamaydi, lekin to‘plangan statistika saqlanadi. Arxivdan qaytarish mumkin.')
                        ->successNotificationTitle('Havola arxivlandi'),
                    RestoreAction::make()->label('Qaytarish'),
                ]),
            ]);
    }

    /** QR kodni ko‘rsatadigan va yuklab olish havolalarini beradigan modal. */
    public static function qrAction(): Action
    {
        return Action::make('qr')->label('QR')->icon(Heroicon::OutlinedQrCode)->color('gray')
            ->modalHeading(fn (MarketingLink $record) => 'QR kod — '.$record->name)
            ->modalWidth('sm')
            ->modalContent(fn (MarketingLink $record) => new HtmlString(
                '<div style="text-align:center">'
                .'<img src="'.e(route('marketing.qr', [$record, 'png'])).'" alt="QR" style="width:240px;height:240px;margin:0 auto;image-rendering:pixelated">'
                .'<p style="margin-top:8px;font-size:13px;opacity:.7">'.e($record->url()).'</p></div>'
            ))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Yopish')
            ->extraModalFooterActions(fn (MarketingLink $record) => [
                Action::make('downloadPng')->label('PNG yuklab olish')->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(route('marketing.qr', [$record, 'png', 'download' => 1])),
                Action::make('downloadSvg')->label('SVG')->color('gray')
                    ->url(route('marketing.qr', [$record, 'svg', 'download' => 1])),
            ]);
    }

    /** "Tg1 — @kanal" → "tg1-kanal" (bo‘sh bo‘lsa — tasodifiy kod). */
    public static function suggestCode(string $name): string
    {
        $code = Str::limit(trim(Str::slug($name, '-', 'en', ['@' => ' ']), '-'), 40, '');

        return $code !== '' ? $code : Str::lower(Str::random(6));
    }

    /** @return array{0: string, 1: string} [matn, rang] */
    public static function status(MarketingLink $r): array
    {
        return match (true) {
            $r->trashed() => ['Arxivda', 'gray'],
            ! $r->is_active => ['O‘chirilgan', 'gray'],
            $r->expires_at && $r->expires_at->isPast() => ['Muddati o‘tgan', 'warning'],
            default => ['Faol', 'success'],
        };
    }

    /** @return list<string> "Android — 120 (62%)" ko‘rinishida */
    private static function breakdown(MarketingLink $r, string $field): array
    {
        $rows = app(MarketingService::class)->breakdown($r, $field);
        $total = max(1, (int) $r->clicks_count);
        $labels = ['fikrlash' => 'Fikrlash ilovasi', 'telegram' => 'Telegram', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'tiktok' => 'TikTok',
            'mobile' => 'Telefon', 'tablet' => 'Planshet', 'desktop' => 'Kompyuter',
            'android' => 'Android', 'ios' => 'iOS', 'windows' => 'Windows', 'mac' => 'macOS', 'linux' => 'Linux', 'other' => 'Boshqa',
            'chrome' => 'Chrome', 'safari' => 'Safari', 'firefox' => 'Firefox', 'edge' => 'Edge', 'opera' => 'Opera',
            'yandex' => 'Yandex Browser', 'samsung' => 'Samsung Internet'];

        return $rows->map(function (int $count, string $key) use ($total, $labels, $field) {
            $name = $key === '' ? ($field === 'app' ? 'Oddiy brauzer' : ($field === 'referrer' ? 'Noma’lum / ilova' : 'Noma’lum')) : ($labels[$key] ?? $key);

            return $name.' — '.Number::format($count).' ('.round($count / $total * 100).'%)';
        })->values()->all();
    }

    public static function getRelations(): array
    {
        return [SignupsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMarketingLinks::route('/'),
            'create' => CreateMarketingLink::route('/create'),
            'view' => ViewMarketingLink::route('/{record}'),
            'edit' => EditMarketingLink::route('/{record}/edit'),
        ];
    }
}
