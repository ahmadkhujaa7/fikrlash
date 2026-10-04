<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Users\UserResource;
use App\Models\LoginEvent;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** So‘nggi kirishlar va urinishlar — jonli lenta. */
class LatestLogins extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = '30s';

    public function table(Table $table): Table
    {
        return $table
            ->heading('So‘nggi kirishlar')
            ->query(LoginEvent::query()->with('user')->latest('id'))
            ->columns([
                TextColumn::make('event')->label('Hodisa')->badge()
                    ->formatStateUsing(fn (LoginEvent $r) => $r->label())
                    ->color(fn (LoginEvent $r) => LoginEvent::COLORS[$r->event] ?? 'gray'),
                TextColumn::make('user.username')->label('Kim')->prefix('@')
                    ->placeholder(fn (LoginEvent $r) => $r->identifier ? '“'.$r->identifier.'”' : '—')
                    ->url(fn (LoginEvent $r) => $r->user ? UserResource::getUrl('view', ['record' => $r->user]) : null),
                TextColumn::make('device')->label('Qurilma')->placeholder('—'),
                TextColumn::make('created_at')->label('Vaqt')->since()
                    ->tooltip(fn (LoginEvent $r) => $r->created_at->format('d.m.Y H:i:s').' · '.$r->ip),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }
}
