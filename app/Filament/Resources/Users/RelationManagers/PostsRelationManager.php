<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Enums\PostStatus;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PostsRelationManager extends RelationManager
{
    protected static string $relationship = 'posts';

    protected static ?string $title = 'Postlar';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedDocumentText;

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->posts()->count();
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([SoftDeletingScope::class]))
            ->columns([
                TextColumn::make('content')->label('Matn')->limit(90)->wrap()->searchable(),
                TextColumn::make('status')->label('Holat')->badge()->color(fn (Post $r) => $r->trashed() ? 'danger' : PostResource::statusColor($r->status))
                    ->formatStateUsing(fn (Post $r) => $r->trashed() ? 'O‘chirilgan' : $r->status->getLabel()),
                TextColumn::make('likes_count')->label('Like')->numeric()->sortable(),
                TextColumn::make('comments_count')->label('Izoh')->numeric()->sortable(),
                TextColumn::make('views_count')->label('Ko‘rish')->numeric()->sortable(),
                TextColumn::make('created_at')->label('Sana')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([SelectFilter::make('status')->label('Holat')->options(PostStatus::class)])
            ->recordActions([
                ActionGroup::make([
                    Action::make('admin')->label('Batafsil')->icon(Heroicon::OutlinedMagnifyingGlass)
                        ->url(fn (Post $r) => PostResource::getUrl('view', ['record' => $r])),
                    Action::make('site')->label('Saytda ochish')->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->url(fn (Post $r) => route('posts.show', $r), true)->visible(fn (Post $r) => ! $r->trashed()),
                    ...PostResource::moderationActions(),
                ]),
            ]);
    }
}
