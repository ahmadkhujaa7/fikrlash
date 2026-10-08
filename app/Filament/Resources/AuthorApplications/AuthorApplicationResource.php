<?php

namespace App\Filament\Resources\AuthorApplications;

use App\Filament\Resources\AuthorApplications\Pages\ManageAuthorApplications;
use App\Filament\Resources\Users\UserResource;
use App\Models\AuthorApplication;
use App\Services\Monetization\MonetizationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;
use UnitEnum;

/** "Muallif bo‘lish" so‘rovlari: tasdiqlash (monetizatsiya yoqiladi) yoki rad etish. */
class AuthorApplicationResource extends Resource
{
    protected static ?string $model = AuthorApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Monetizatsiya';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Muallif so‘rovlari';

    protected static ?string $modelLabel = 'so‘rov';

    protected static ?string $pluralModelLabel = 'Muallif so‘rovlari';

    public static function getNavigationBadge(): ?string
    {
        $n = AuthorApplication::query()->where('status', AuthorApplication::PENDING)->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'reviewer']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Foydalanuvchi')->searchable()->weight('medium')
                    ->description(fn (AuthorApplication $r) => $r->user ? '@'.$r->user->username : null)
                    ->url(fn (AuthorApplication $r) => $r->user && ! $r->user->trashed() ? UserResource::getUrl('view', ['record' => $r->user]) : null),
                TextColumn::make('article_views')->label('Ko‘rsatkichlar (so‘rov paytida)')->sortable()
                    ->formatStateUsing(fn (AuthorApplication $r) => Number::format($r->followers).' obunachi · '.Number::format($r->article_views).' ko‘rish · '.$r->articles.' maqola')
                    ->description(fn (AuthorApplication $r) => $r->message ? '“'.str($r->message)->limit(70).'”' : null)->wrap(),
                TextColumn::make('status')->label('Holat')->badge()
                    ->formatStateUsing(fn (string $state) => AuthorApplication::LABELS[$state] ?? $state)
                    ->color(fn (string $state) => AuthorApplication::COLORS[$state] ?? 'gray'),
                TextColumn::make('created_at')->label('Yuborilgan')->dateTime('d.m.Y H:i')->sortable()
                    ->description(fn (AuthorApplication $r) => $r->admin_note ? 'Izoh: '.str($r->admin_note)->limit(40) : null),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Holat')->options(AuthorApplication::LABELS)->default(AuthorApplication::PENDING),
            ])
            ->emptyStateHeading('So‘rovlar yo‘q')
            ->emptyStateIcon(Heroicon::OutlinedInboxArrowDown)
            ->recordActions([
                Action::make('approve')->label('Tasdiqlash')->icon(Heroicon::OutlinedCheckBadge)->color('success')
                    ->visible(fn (AuthorApplication $r) => $r->status === AuthorApplication::PENDING)
                    ->modalHeading('Muallif deb tasdiqlansinmi?')
                    ->modalDescription('Monetizatsiya yoqiladi: shu kundan keyin chop etilgan maqolalari daromad keltiradi, profilida "Muallif" belgisi chiqadi.')
                    ->schema([Textarea::make('note')->label('Foydalanuvchiga izoh (ixtiyoriy)')->maxLength(500)])
                    ->action(function (AuthorApplication $record, array $data) {
                        app(MonetizationService::class)->approve($record, auth()->user(), $data['note'] ?? null);
                        Notification::make()->title('Muallif tasdiqlandi')->success()->send();
                    }),
                Action::make('reject')->label('Rad etish')->icon(Heroicon::OutlinedXCircle)->color('danger')
                    ->visible(fn (AuthorApplication $r) => $r->status === AuthorApplication::PENDING)
                    ->schema([Textarea::make('reason')->label('Sabab (foydalanuvchiga ko‘rinadi)')->required()->maxLength(500)])
                    ->action(function (AuthorApplication $record, array $data) {
                        app(MonetizationService::class)->reject($record, auth()->user(), $data['reason']);
                        Notification::make()->title('So‘rov rad etildi')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAuthorApplications::route('/')];
    }
}
