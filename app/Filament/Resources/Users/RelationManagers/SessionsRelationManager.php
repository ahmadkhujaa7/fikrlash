<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Models\UserSession;
use App\Services\Security\SessionService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Faol qurilmalar (brauzer sessiyalari) — istalganini tugatish mumkin. */
class SessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sessions';

    protected static ?string $title = 'Faol qurilmalar';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedComputerDesktop;

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->sessions()->count();

        return $count ? (string) $count : null;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ip_address')
            ->columns([
                TextColumn::make('device')->label('Qurilma')->state(fn (UserSession $r) => $r->device()),
                TextColumn::make('ip_address')->label('IP')->copyable()->fontFamily('mono'),
                TextColumn::make('last_activity')->label('Oxirgi faollik')->sortable()
                    ->state(fn (UserSession $r) => $r->isOnline() ? 'Hozir onlayn' : $r->lastActivityAt()->diffForHumans())
                    ->color(fn (UserSession $r) => $r->isOnline() ? 'success' : 'gray')
                    ->tooltip(fn (UserSession $r) => $r->lastActivityAt()->format('d.m.Y H:i')),
            ])
            ->defaultSort('last_activity', 'desc')
            ->emptyStateHeading('Faol brauzer sessiyasi yo‘q')
            ->headerActions([
                Action::make('terminateAll')->label('Hammasidan chiqarish')->icon(Heroicon::OutlinedArrowRightStartOnRectangle)->color('danger')
                    ->requiresConfirmation()->modalDescription('Barcha brauzer sessiyalari va mobil ilova tokenlari bekor qilinadi.')
                    ->visible(fn () => ! $this->getOwnerRecord()->is(auth()->user()))
                    ->action(function () {
                        $count = app(SessionService::class)->terminateAll($this->getOwnerRecord(), auth()->user());
                        Notification::make()->title("{$count} ta sessiya/token bekor qilindi")->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('terminate')->label('Chiqarish')->icon(Heroicon::OutlinedXMark)->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (UserSession $r) => $r->id !== session()->getId())
                    ->action(function (UserSession $record) {
                        app(SessionService::class)->terminate($record, auth()->user());
                        Notification::make()->title('Sessiya tugatildi')->success()->send();
                    }),
            ]);
    }
}
