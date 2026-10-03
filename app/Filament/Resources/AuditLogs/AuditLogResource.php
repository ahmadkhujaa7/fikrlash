<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ManageAuditLogs;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Faqat o‘qish uchun: audit yozuvlarini tahrirlab yoki o‘chirib bo‘lmaydi. */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Tizim';

    protected static ?string $modelLabel = 'audit yozuvi';

    protected static ?string $pluralModelLabel = 'Audit log';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('action')->label('Amal')->badge(),
            TextEntry::make('user.username')->label('Kim')->prefix('@')->placeholder('tizim'),
            TextEntry::make('target_type')->label('Obyekt')->formatStateUsing(fn ($state, AuditLog $r) => "{$state} #{$r->target_id}"),
            TextEntry::make('created_at')->label('Vaqt')->dateTime('d.m.Y H:i:s'),
            TextEntry::make('ip_address')->label('IP')->placeholder('—'),
            TextEntry::make('user_agent')->label('User agent')->placeholder('—'),
            KeyValueEntry::make('old_values')->label('Oldingi qiymatlar')->columnSpanFull(),
            KeyValueEntry::make('new_values')->label('Yangi qiymatlar')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->columns([
                TextColumn::make('created_at')->label('Vaqt')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('action')->label('Amal')->badge()->searchable(),
                TextColumn::make('user.username')->label('Kim')->prefix('@')->placeholder('tizim'),
                TextColumn::make('target_type')->label('Obyekt')->formatStateUsing(fn ($state, AuditLog $r) => "{$state} #{$r->target_id}"),
                TextColumn::make('ip_address')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('action')->label('Amal')
                    ->options(fn () => AuditLog::query()->distinct()->orderBy('action')->pluck('action', 'action')->all()),
                SelectFilter::make('target_type')->label('Obyekt turi')
                    ->options(['post' => 'Post', 'comment' => 'Izoh', 'user' => 'Foydalanuvchi', 'report' => 'Shikoyat']),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAuditLogs::route('/')];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
