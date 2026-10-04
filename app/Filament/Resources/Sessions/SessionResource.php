<?php

namespace App\Filament\Resources\Sessions;

use App\Filament\InitialsAvatarProvider;
use App\Filament\Resources\Sessions\Pages\ListSessions;
use App\Filament\Resources\Users\UserResource;
use App\Models\UserSession;
use App\Services\Security\SessionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Barcha faol brauzer sessiyalari (kim, qaysi qurilmadan, qachon) — istalganini tugatish mumkin. */
class SessionResource extends Resource
{
    protected static ?string $model = UserSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static string|UnitEnum|null $navigationGroup = 'Kuzatuv';

    protected static ?int $navigationSort = 20;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'sessiya';

    protected static ?string $pluralModelLabel = 'Faol sessiyalar';

    protected static ?string $slug = 'sessions';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNotNull('user_id')->with('user');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar')->label('')->circular()->imageSize(28)
                    ->state(fn (UserSession $r) => InitialsAvatarProvider::urlFor($r->user)),
                TextColumn::make('user.name')->label('Foydalanuvchi')
                    ->description(fn (UserSession $r) => $r->user ? '@'.$r->user->username : null)
                    ->url(fn (UserSession $r) => $r->user ? UserResource::getUrl('view', ['record' => $r->user]) : null),
                TextColumn::make('device')->label('Qurilma')->state(fn (UserSession $r) => $r->device()),
                TextColumn::make('ip_address')->label('IP')->fontFamily('mono')->copyable()->searchable(),
                TextColumn::make('last_activity')->label('Oxirgi faollik')->sortable()
                    ->state(fn (UserSession $r) => $r->isOnline() ? 'Hozir onlayn' : $r->lastActivityAt()->diffForHumans())
                    ->color(fn (UserSession $r) => $r->isOnline() ? 'success' : 'gray'),
            ])
            ->defaultSort('last_activity', 'desc')
            ->filters([
                TernaryFilter::make('online')->label('Holat')
                    ->trueLabel('Faqat onlayn')->falseLabel('Faol emas')
                    ->queries(
                        true: fn (Builder $query) => $query->online(),
                        false: fn (Builder $query) => $query->where('last_activity', '<', now()->subMinutes(UserSession::ONLINE_MINUTES)->timestamp),
                    ),
            ])
            ->recordActions([
                Action::make('terminate')->label('Chiqarish')->icon(Heroicon::OutlinedXMark)->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (UserSession $r) => $r->id !== session()->getId())
                    ->action(function (UserSession $record) {
                        app(SessionService::class)->terminate($record, auth()->user());
                        Notification::make()->title('Sessiya tugatildi')->success()->send();
                    }),
            ])
            ->poll('30s');
    }

    public static function getPages(): array
    {
        return ['index' => ListSessions::route('/')];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
