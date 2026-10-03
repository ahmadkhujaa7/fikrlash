<?php

namespace App\Filament\Resources\ApiTokens;

use App\Filament\Resources\ApiTokens\Pages\ManageApiTokens;
use App\Models\PersonalAccessToken;
use App\Services\Social\AuditLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ApiTokenResource extends Resource
{
    protected static ?string $model = PersonalAccessToken::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Tizim';

    protected static ?string $modelLabel = 'API token';

    protected static ?string $pluralModelLabel = 'API tokenlar';

    protected static ?string $slug = 'api-tokens';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('tokenable'))
            ->columns([
                TextColumn::make('tokenable.username')->label('Foydalanuvchi')->prefix('@'),
                TextColumn::make('name')->label('Nomi')->searchable(),
                TextColumn::make('last_used_at')->label('Oxirgi ishlatilgan')->since()->sortable()->placeholder('hech qachon'),
                TextColumn::make('expires_at')->label('Muddati')->dateTime('d.m.Y')->sortable()->placeholder('cheksiz'),
                TextColumn::make('created_at')->label('Yaratilgan')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Filter::make('expired')->label('Muddati o‘tgan')->query(fn (Builder $q) => $q->where('expires_at', '<', now())),
            ])
            ->recordActions([
                Action::make('revoke')->label('Bekor qilish')->icon(Heroicon::OutlinedTrash)->color('danger')
                    ->requiresConfirmation()
                    ->action(function (PersonalAccessToken $record) {
                        app(AuditLogger::class)->log('token.revoked', $record->tokenable, [], ['token_id' => $record->id, 'name' => $record->name]);
                        $record->delete();
                        Notification::make()->title('Token bekor qilindi')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageApiTokens::route('/')];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
