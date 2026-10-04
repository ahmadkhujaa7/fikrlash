<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\LoginEvents\LoginEventResource;
use App\Models\LoginEvent;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

/** So‘nggi 24 soatda ko‘p marta noto‘g‘ri parol kiritgan IP manzillar (parol terish hujumi belgisi). */
class SuspiciousIps extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Shubhali IP manzillar (24 soat, 5+ noto‘g‘ri urinish)')
            ->query(LoginEvent::query()
                ->selectRaw('MIN(id) as id, ip, COUNT(*) as attempts, COUNT(DISTINCT identifier) as accounts, MAX(created_at) as last_at')
                ->whereIn('event', ['failed', 'blocked'])
                ->where('created_at', '>=', now()->subDay())
                ->whereNotNull('ip')
                ->groupBy('ip')
                ->having(DB::raw('COUNT(*)'), '>=', 5))
            ->defaultSort('attempts', 'desc')
            ->columns([
                TextColumn::make('ip')->label('IP')->fontFamily('mono')->copyable(),
                TextColumn::make('attempts')->label('Urinishlar')->sortable()->color('danger')->weight('bold'),
                TextColumn::make('accounts')->label('Turli akkauntlar')
                    ->description(fn ($record) => $record->accounts > 3 ? 'ko‘p akkauntni sinamoqda' : null),
                TextColumn::make('last_at')->label('Oxirgi urinish')->since(),
            ])
            ->recordActions([
                Action::make('history')->label('Tarix')->icon(Heroicon::OutlinedMagnifyingGlass)
                    ->url(fn ($record) => LoginEventResource::getUrl('index', ['tableSearch' => $record->ip])),
            ])
            ->emptyStateHeading('Shubhali faollik yo‘q')
            ->emptyStateDescription('So‘nggi 24 soatda birorta IP 5 martadan ko‘p xato qilmagan.')
            ->emptyStateIcon('heroicon-o-shield-check');
    }
}
