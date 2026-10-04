<?php

namespace App\Filament\Widgets;

use App\Filament\InitialsAvatarProvider;
use App\Filament\Resources\Users\UserResource;
use App\Models\UserSession;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Hozir saytda bo‘lgan foydalanuvchilar (so‘nggi 5 daqiqada faol sessiya). */
class OnlineUsers extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = '30s';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Hozir onlayn')
            ->query(UserSession::query()->online()->whereNotNull('user_id')->with('user')->latest('last_activity'))
            ->columns([
                ImageColumn::make('avatar')->label('')->circular()->imageSize(28)
                    ->state(fn (UserSession $r) => InitialsAvatarProvider::urlFor($r->user)),
                TextColumn::make('user.name')->label('Foydalanuvchi')
                    ->description(fn (UserSession $r) => '@'.$r->user?->username)
                    ->url(fn (UserSession $r) => $r->user ? UserResource::getUrl('view', ['record' => $r->user]) : null),
                TextColumn::make('device')->label('Qurilma')->state(fn (UserSession $r) => $r->device()),
                TextColumn::make('last_activity')->label('Faollik')
                    ->state(fn (UserSession $r) => $r->lastActivityAt()->diffForHumans()),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('Hozir hech kim onlayn emas')
            ->emptyStateIcon('heroicon-o-signal-slash');
    }
}
