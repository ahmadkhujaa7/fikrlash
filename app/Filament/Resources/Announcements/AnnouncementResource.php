<?php

namespace App\Filament\Resources\Announcements;

use App\Enums\UserStatus;
use App\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Filament\Resources\Announcements\Pages\EditAnnouncement;
use App\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Filament\Resources\Announcements\Pages\ViewAnnouncement;
use App\Filament\Resources\Announcements\RelationManagers\ReceiptsRelationManager;
use App\Models\Announcement;
use App\Models\User;
use App\Services\Media\ImageService;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

/**
 * Admin e'lonlari (foydalanuvchilarga bildirishnoma). Foydalanuvchi ro‘yxatda sarlavha va qisqa mazmunni
 * ko‘radi, bosganda to‘liq ochiladi. Statistika: nechta odamga yuborildi, nechta ro‘yxatda ko‘rdi,
 * nechta bosib ochdi. Tahrirlash va o‘chirish hamma foydalanuvchida darhol aks etadi.
 */
class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Tizim';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'E’lonlar (bildirishnoma)';

    protected static ?string $modelLabel = 'e’lon';

    protected static ?string $pluralModelLabel = 'E’lonlar';

    protected static ?string $recordTitleAttribute = 'title';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('author')
            ->withCount([
                'receipts as seen_count' => fn (Builder $q) => $q->whereNotNull('seen_at'),
                'receipts as opened_count' => fn (Builder $q) => $q->whereNotNull('opened_at'),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        $disk = config('fikrlash.media.disk');

        return $schema->components([
            Section::make('E’lon')->columnSpanFull()->schema([
                TextInput::make('title')->label('Sarlavha')->required()->maxLength(150)
                    ->helperText('Bildirishnomalar ro‘yxatida qalin bo‘lib ko‘rinadi.'),
                Textarea::make('body')->label('Matn')->required()->maxLength(5000)->rows(8)
                    ->helperText('Ro‘yxatda boshidagi 2 qatori ko‘rinadi, bosilganda — to‘liq. Havolalar (masalan fikrlash.uz yoki https://...) bosiladigan bo‘ladi.'),
                FileUpload::make('image_path')->label('Rasm (ixtiyoriy)')->image()
                    ->disk($disk)->directory('announcements')->visibility('public')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(8192)
                    // EXIF/GPS tozalanadi, WebP'ga aylantiriladi (eni 1600 px gacha).
                    ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file) => app(ImageService::class)->storeTo($file, $disk, 'announcements', 1600)['path'])
                    ->deleteUploadedFileUsing(fn () => null) // eskisi model saqlanganda o‘chiriladi
                    ->placeholder('Rasmni shu yerga tashlang yoki bosib tanlang'),
                Grid::make(2)->schema([
                    TextInput::make('link_url')->label('Tugma havolasi (ixtiyoriy)')->url()->maxLength(500)->placeholder('https://fikrlash.uz/...'),
                    TextInput::make('link_label')->label('Tugma matni')->maxLength(60)->placeholder('Batafsil')
                        ->requiredWith('link_url'),
                ]),
            ]),
            Section::make('Kimga yuboriladi')->columnSpanFull()->visibleOn('create')->schema([
                Radio::make('audience')->hiddenLabel()->required()->live()->default(Announcement::AUDIENCE_ALL)
                    ->options(fn () => [
                        Announcement::AUDIENCE_ALL => 'Barcha faol foydalanuvchilar ('.Number::format(User::query()->where('status', UserStatus::Active)->count()).' ta)',
                        Announcement::AUDIENCE_USERS => 'Tanlangan foydalanuvchilar',
                    ]),
                Select::make('user_ids')->label('Foydalanuvchilar')->multiple()->searchable()
                    ->visible(fn (Get $get) => $get('audience') === Announcement::AUDIENCE_USERS)
                    ->required(fn (Get $get) => $get('audience') === Announcement::AUDIENCE_USERS)
                    ->getSearchResultsUsing(fn (string $search) => User::query()
                        ->where('status', UserStatus::Active)
                        ->where(fn ($q) => $q->where('username', 'like', ltrim($search, '@').'%')->orWhere('name', 'like', "%{$search}%"))
                        ->limit(20)->get()
                        ->mapWithKeys(fn (User $u) => [$u->id => "{$u->name} (@{$u->username})"])->all())
                    ->getOptionLabelsUsing(fn (array $values) => User::query()->whereKey($values)->get()
                        ->mapWithKeys(fn (User $u) => [$u->id => "{$u->name} (@{$u->username})"])->all())
                    ->helperText('Ism yoki username bo‘yicha qidiring.'),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Foydalanuvchi ko‘radigan e’lon')->columnSpanFull()->columns(3)->schema([
                ImageEntry::make('image_path')->hiddenLabel()->disk(config('fikrlash.media.disk'))->imageHeight(180)
                    ->visible(fn (Announcement $r) => (bool) $r->image_path),
                Grid::make(1)->columnSpan(fn (Announcement $r) => $r->image_path ? 2 : 3)->schema([
                    TextEntry::make('title')->hiddenLabel()->weight('bold')->size('lg'),
                    TextEntry::make('body')->hiddenLabel()->prose()->formatStateUsing(fn (string $state) => nl2br(e($state)))->html(),
                    TextEntry::make('link_url')->label('Tugma')->url(fn (Announcement $r) => $r->link_url, true)
                        ->formatStateUsing(fn (Announcement $r) => ($r->link_label ?: 'Batafsil').' → '.$r->link_url)
                        ->visible(fn (Announcement $r) => (bool) $r->link_url),
                ]),
            ]),
            Section::make('Ma’lumot')->columnSpanFull()->columns(4)->schema([
                TextEntry::make('audience')->label('Kimga')->badge()
                    ->formatStateUsing(fn (string $state) => $state === Announcement::AUDIENCE_ALL ? 'Hammaga' : 'Tanlanganlarga'),
                TextEntry::make('sent_at')->label('Yuborilgan')->dateTime('d.m.Y H:i'),
                TextEntry::make('updated_at')->label('Oxirgi tahrir')->dateTime('d.m.Y H:i'),
                TextEntry::make('author.name')->label('Admin')->placeholder('—'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $percent = fn (int $part, Announcement $r) => $r->recipients_count ? round($part / $r->recipients_count * 100, 1).'%' : '—';

        return $table
            ->columns([
                ImageColumn::make('image_path')->label('')->disk(config('fikrlash.media.disk'))->square()->imageSize(44),
                TextColumn::make('title')->label('Sarlavha')->searchable()->weight('medium')->wrap()
                    ->description(fn (Announcement $r) => Str::limit(preg_replace('/\s+/u', ' ', $r->body), 90)),
                TextColumn::make('audience')->label('Kimga')->badge()
                    ->formatStateUsing(fn (string $state) => $state === Announcement::AUDIENCE_ALL ? 'Hammaga' : 'Tanlanganlar')
                    ->color(fn (string $state) => $state === Announcement::AUDIENCE_ALL ? 'info' : 'gray'),
                TextColumn::make('recipients_count')->label('Yuborildi')->numeric()->sortable(),
                TextColumn::make('seen_count')->label('Ro‘yxatda ko‘rdi')->numeric()->sortable()
                    ->description(fn (Announcement $r) => $percent((int) $r->seen_count, $r))->color('info'),
                TextColumn::make('opened_count')->label('Bosib ochdi')->numeric()->sortable()
                    ->description(fn (Announcement $r) => $percent((int) $r->opened_count, $r))->color('success'),
                TextColumn::make('sent_at')->label('Yuborilgan')->dateTime('d.m.Y H:i')->sortable()
                    ->description(fn (Announcement $r) => $r->sent_at?->diffForHumans()),
                TextColumn::make('author.username')->label('Admin')->prefix('@')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('Hali e’lon yo‘q')
            ->emptyStateDescription('Yangi e’lon yarating — u barcha (yoki tanlangan) foydalanuvchilarning bildirishnomalarida chiqadi.')
            ->emptyStateIcon(Heroicon::OutlinedMegaphone)
            ->recordActions([
                ViewAction::make()->label('Statistika'),
                EditAction::make(),
                DeleteAction::make()->modalDescription('E’lon barcha foydalanuvchilarning bildirishnomalaridan o‘chiriladi. Buni qaytarib bo‘lmaydi.'),
            ]);
    }

    public static function getRelations(): array
    {
        return [ReceiptsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAnnouncements::route('/'),
            'create' => CreateAnnouncement::route('/create'),
            'view' => ViewAnnouncement::route('/{record}'),
            'edit' => EditAnnouncement::route('/{record}/edit'),
        ];
    }
}
