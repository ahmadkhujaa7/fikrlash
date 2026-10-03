<?php

namespace App\Filament\Resources\Comments;

use App\Enums\CommentStatus;
use App\Filament\Resources\Comments\Pages\ListComments;
use App\Models\Comment;
use App\Services\Moderation\ModerationService;
use App\Services\Social\AuditLogger;
use App\Services\Social\CommentService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class CommentResource extends Resource
{
    protected static ?string $model = Comment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Kontent';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'izoh';

    protected static ?string $pluralModelLabel = 'Izohlar';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class])->with(['user', 'post']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('content')->label('Izoh')->limit(90)->wrap()->searchable()
                    ->description(fn (Comment $r) => '@'.$r->user?->username),
                TextColumn::make('post.content')->label('Post')->limit(50)->toggleable()
                    ->url(fn (Comment $r) => $r->post && ! $r->post->trashed() ? $r->url() : null, true),
                TextColumn::make('status')->label('Holat')->badge()
                    ->color(fn (Comment $r) => $r->status === CommentStatus::Published ? 'success' : 'danger'),
                TextColumn::make('likes_count')->label('Like')->numeric()->sortable(),
                TextColumn::make('created_at')->label('Sana')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Holat')->options(CommentStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('hide')->label('Yashirish')->icon(Heroicon::OutlinedEyeSlash)->color('warning')
                        ->visible(fn (Comment $r) => ! $r->trashed() && $r->status === CommentStatus::Published)
                        ->requiresConfirmation()
                        ->action(function (Comment $record) {
                            app(ModerationService::class)->hideComment($record, auth()->user());
                            Notification::make()->title('Izoh yashirildi')->success()->send();
                        }),
                    Action::make('delete')->label('O‘chirish')->icon(Heroicon::OutlinedTrash)->color('danger')
                        ->visible(fn (Comment $r) => ! $r->trashed())
                        ->requiresConfirmation()
                        ->action(function (Comment $record) {
                            app(AuditLogger::class)->log('comment.deleted', $record);
                            app(CommentService::class)->delete($record);
                            Notification::make()->title('Izoh o‘chirildi')->success()->send();
                        }),
                    Action::make('restore')->label('Tiklash')->icon(Heroicon::OutlinedArrowUturnLeft)
                        ->visible(fn (Comment $r) => $r->trashed())
                        ->requiresConfirmation()
                        ->action(function (Comment $record) {
                            $record->restore();
                            app(AuditLogger::class)->log('comment.restored', $record);
                            Notification::make()->title('Izoh tiklandi')->success()->send();
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListComments::route('/')];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
