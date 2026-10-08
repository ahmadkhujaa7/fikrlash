<?php

namespace App\Filament\Resources\Authors;

use App\Filament\Resources\Authors\Pages\ManageAuthors;
use App\Filament\Resources\Users\UserResource;
use App\Models\AuthorPayout;
use App\Models\Post;
use App\Models\User;
use App\Services\Monetization\MonetizationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Monetizatsiyasi yoqilgan mualliflar: daromad, to‘lovlar, balans; to‘xtatish. */
class AuthorResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'authors';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Monetizatsiya';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Mualliflar';

    protected static ?string $modelLabel = 'muallif';

    protected static ?string $pluralModelLabel = 'Mualliflar';

    protected static bool $isGloballySearchable = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereNotNull('monetized_at')
            ->withSum('authorEarnings as earned', 'amount')
            ->withSum('authorEarnings as paid_views', 'views')
            ->withSum(['authorPayouts as paid' => fn (Builder $q) => $q->where('status', AuthorPayout::PAID)], 'amount')
            ->withSum(['authorPayouts as waiting' => fn (Builder $q) => $q->where('status', AuthorPayout::PENDING)], 'amount')
            ->withCount(['posts as articles_since' => fn (Builder $q) => $q->where('type', Post::TYPE_ARTICLE)->whereColumn('posts.published_at', '>=', 'users.monetized_at')]);
    }

    public static function table(Table $table): Table
    {
        $money = fn ($state) => MonetizationService::money((float) $state);

        return $table
            ->columns([
                TextColumn::make('name')->label('Muallif')->searchable()->weight('medium')
                    ->description(fn (User $r) => '@'.$r->username.' · '.$r->monetized_at?->format('d.m.Y').' dan')
                    ->url(fn (User $r) => UserResource::getUrl('view', ['record' => $r])),
                TextColumn::make('articles_since')->label('Yangi maqolalar')->numeric()->sortable(),
                TextColumn::make('paid_views')->label('Daromadli ko‘rishlar')->numeric()->sortable()->placeholder('0'),
                TextColumn::make('earned')->label('Ishlagan')->sortable()->formatStateUsing($money)->placeholder('0 so‘m')
                    ->description(fn (User $r) => $r->paid ? 'to‘langan: '.MonetizationService::money((float) $r->paid) : null),
                TextColumn::make('balance')->label('Balans')->weight('bold')
                    ->state(fn (User $r) => max(0, (float) $r->earned - (float) $r->paid - (float) $r->waiting))
                    ->formatStateUsing($money),
            ])
            ->defaultSort('monetized_at', 'desc')
            ->emptyStateHeading('Hali muallif yo‘q')
            ->emptyStateDescription('Foydalanuvchilar talablarga yetgach so‘rov yuboradi — "Muallif so‘rovlari" bo‘limida tasdiqlang.')
            ->recordActions([
                Action::make('revoke')->label('To‘xtatish')->icon(Heroicon::OutlinedPauseCircle)->color('danger')
                    ->modalHeading('Monetizatsiya to‘xtatilsinmi?')
                    ->modalDescription('Yangi daromad hisoblanmaydi va "Muallif" belgisi olinadi. Balansdagi mablag‘ saqlanadi — yechib olishi mumkin.')
                    ->schema([Textarea::make('reason')->label('Sabab (foydalanuvchiga ko‘rinadi)')->required()->maxLength(500)])
                    ->action(function (User $record, array $data) {
                        app(MonetizationService::class)->revoke($record, auth()->user(), $data['reason']);
                        Notification::make()->title('Monetizatsiya to‘xtatildi')->send();
                    }),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ManageAuthors::route('/')];
    }
}
