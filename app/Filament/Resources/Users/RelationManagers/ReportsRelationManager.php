<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Enums\ReportStatus;
use App\Models\Report;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Shu foydalanuvchining o‘ziga (profiliga) qilingan shikoyatlar. */
class ReportsRelationManager extends RelationManager
{
    protected static string $relationship = 'reportsAgainst';

    protected static ?string $title = 'Unga shikoyatlar';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedFlag;

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $pending = $ownerRecord->reportsAgainst()->where('status', ReportStatus::Pending)->count();

        return $pending ? (string) $pending : null;
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        return 'danger';
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.username')->label('Kim')->prefix('@'),
                TextColumn::make('reason')->label('Sabab')->badge()->color('gray'),
                TextColumn::make('description')->label('Izoh')->limit(80)->wrap()->placeholder('—'),
                TextColumn::make('status')->label('Holat')->badge()
                    ->color(fn (Report $r) => $r->status === ReportStatus::Pending ? 'danger' : 'gray'),
                TextColumn::make('created_at')->label('Sana')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
