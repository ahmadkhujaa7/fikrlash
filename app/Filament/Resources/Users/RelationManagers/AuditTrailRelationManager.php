<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Models\AuditLog;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Adminlar shu foydalanuvchiga nisbatan qilgan barcha amallar (kim, qachon, nima o‘zgardi). */
class AuditTrailRelationManager extends RelationManager
{
    protected static string $relationship = 'auditTrail';

    protected static ?string $title = 'Admin amallari';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedClipboardDocumentList;

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->columns([
                TextColumn::make('action')->label('Amal')->badge()->color('gray')
                    ->formatStateUsing(fn ($state) => AuditLogResource::actionLabel($state)),
                TextColumn::make('user.username')->label('Admin')->prefix('@')->placeholder('tizim'),
                TextColumn::make('changes')->label('O‘zgarish')->wrap()
                    ->state(fn (AuditLog $r) => AuditLogResource::describeChanges($r))->placeholder('—'),
                TextColumn::make('ip_address')->label('IP')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Vaqt')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
