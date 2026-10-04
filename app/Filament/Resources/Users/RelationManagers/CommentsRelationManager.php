<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Services\Moderation\ModerationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = 'Izohlar';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedChatBubbleLeft;

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->comments()->count();
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('content')->label('Izoh')->limit(100)->wrap()->searchable(),
                TextColumn::make('post_id')->label('Post')->formatStateUsing(fn ($state) => '#'.$state)
                    ->url(fn (Comment $r) => route('posts.show', $r->post_id).'#comment-'.$r->id, true),
                TextColumn::make('status')->label('Holat')->badge()
                    ->color(fn (Comment $r) => $r->status === CommentStatus::Published ? 'success' : 'warning'),
                TextColumn::make('likes_count')->label('Like')->numeric()->sortable(),
                TextColumn::make('created_at')->label('Sana')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('hide')->label('Yashirish')->icon(Heroicon::OutlinedEyeSlash)->color('warning')
                    ->visible(fn (Comment $r) => $r->status !== CommentStatus::Hidden)
                    ->requiresConfirmation()
                    ->action(function (Comment $record) {
                        app(ModerationService::class)->hideComment($record, auth()->user());
                        Notification::make()->title('Izoh yashirildi')->success()->send();
                    }),
            ]);
    }
}
