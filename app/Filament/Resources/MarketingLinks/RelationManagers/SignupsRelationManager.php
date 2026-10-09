<?php

namespace App\Filament\Resources\MarketingLinks\RelationManagers;

use App\Filament\InitialsAvatarProvider;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Shu havola orqali ro‘yxatdan o‘tganlar: kim, qachon, faol bo‘ldimi. */
class SignupsRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Ro‘yxatdan o‘tganlar';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedUserPlus;

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $q) => $q->withCount(['posts', 'comments']))
            ->recordUrl(fn (User $r) => UserResource::getUrl('view', ['record' => $r]))
            ->columns([
                ImageColumn::make('avatar')->label('')->circular()->imageSize(28)
                    ->state(fn (User $r) => InitialsAvatarProvider::urlFor($r)),
                TextColumn::make('name')->label('Foydalanuvchi')->searchable(['name', 'username'])
                    ->description(fn (User $r) => '@'.$r->username),
                TextColumn::make('created_at')->label('Ro‘yxatdan o‘tgan')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('posts_count')->label('Postlar')->numeric()->sortable(),
                TextColumn::make('comments_count')->label('Izohlar')->numeric()->sortable(),
                TextColumn::make('last_active_at')->label('Oxirgi faollik')->since()->sortable()->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Hali hech kim ro‘yxatdan o‘tmadi')
            ->emptyStateDescription('Havola orqali kelgan foydalanuvchi telefonini tasdiqlashi bilan shu yerda chiqadi.');
    }
}
