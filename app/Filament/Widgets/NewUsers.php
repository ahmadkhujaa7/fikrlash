<?php

namespace App\Filament\Widgets;

use App\Filament\InitialsAvatarProvider;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class NewUsers extends TableWidget
{
    protected static bool $isDiscovered = false;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Yangi foydalanuvchilar')
            ->query(User::query()->withCount('posts')->latest('id'))
            ->recordUrl(fn (User $r) => UserResource::getUrl('view', ['record' => $r]))
            ->columns([
                ImageColumn::make('avatar')->label('')->circular()->imageSize(28)
                    ->state(fn (User $r) => InitialsAvatarProvider::urlFor($r)),
                TextColumn::make('name')->label('Ism')->description(fn (User $r) => '@'.$r->username)
                    ->icon(fn (User $r) => $r->isVerified() ? Heroicon::CheckBadge : null)->iconColor('primary')->iconPosition('after'),
                TextColumn::make('posts_count')->label('Postlar'),
                TextColumn::make('created_at')->label('Qo‘shilgan')->since(),
            ])
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5);
    }
}
