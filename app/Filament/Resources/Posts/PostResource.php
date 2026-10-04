<?php

namespace App\Filament\Resources\Posts;

use App\Enums\PostStatus;
use App\Enums\PostVisibility;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Filament\Resources\Posts\Pages\ViewPost;
use App\Models\Post;
use App\Services\Moderation\ModerationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Kontent';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'post';

    protected static ?string $pluralModelLabel = 'Postlar';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->with(['user', 'category']);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Post::query()->where('status', PostStatus::PendingModeration)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Tekshiruvni kutayotgan postlar';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Post')->columns(4)->columnSpanFull()->schema([
                TextEntry::make('title')->label('Maqola sarlavhasi')->columnSpanFull()->visible(fn (Post $r) => $r->isArticle())->weight('bold')->size('lg'),
                TextEntry::make('content')->label('Matn')->columnSpanFull()->prose(),
                ImageEntry::make('image_path')->label('Rasm')->disk(config('fikrlash.media.disk'))->columnSpanFull()->visible(fn (Post $r) => (bool) $r->image_path),
                TextEntry::make('user.username')->label('Muallif')->prefix('@'),
                TextEntry::make('category.name')->label('Kategoriya')->placeholder('—'),
                TextEntry::make('status')->label('Holat')->badge()->color(fn (Post $r) => self::statusColor($r->status)),
                TextEntry::make('visibility')->label('Ko‘rinish')->badge(),
                TextEntry::make('likes_count')->label('Like'),
                TextEntry::make('comments_count')->label('Izoh'),
                TextEntry::make('views_count')->label('Ko‘rish'),
                TextEntry::make('saves_count')->label('Saqlangan'),
                TextEntry::make('published_at')->label('Chop etilgan')->dateTime('d.m.Y H:i')->placeholder('—'),
                TextEntry::make('deleted_at')->label('O‘chirilgan')->dateTime('d.m.Y H:i')->placeholder('—'),
            ]),
            Section::make('AI tahlil')->columns(4)->columnSpanFull()->schema([
                TextEntry::make('latestAiAnalysis.topic')->label('Mavzu')->placeholder('—'),
                TextEntry::make('latestAiAnalysis.category')->label('AI kategoriya')->placeholder('—'),
                TextEntry::make('latestAiAnalysis.sentiment')->label('Kayfiyat')->badge()->placeholder('—'),
                IconEntry::make('ai_flagged')->label('Xavfli belgisi')->boolean(),
                TextEntry::make('latestAiAnalysis.quality_score')->label('Sifat')->suffix('/100')->placeholder('—'),
                TextEntry::make('latestAiAnalysis.toxicity_score')->label('Toksiklik')->suffix('/100')->placeholder('—'),
                TextEntry::make('latestAiAnalysis.spam_score')->label('Spam')->suffix('/100')->placeholder('—'),
                TextEntry::make('latestAiAnalysis.educational_score')->label('Ta’limiy')->suffix('/100')->placeholder('—'),
                TextEntry::make('latestAiAnalysis.summary')->label('Xulosa')->columnSpanFull()->placeholder('—'),
                TextEntry::make('latestAiAnalysis.keywords')->label('Kalit so‘zlar')->badge()->columnSpanFull()->placeholder('—'),
                TextEntry::make('latestAiAnalysis.model')->label('Model')->placeholder('—'),
                TextEntry::make('latestAiAnalysis.created_at')->label('Tahlil vaqti')->dateTime('d.m.Y H:i')->placeholder('—'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('content')->label('Matn')->limit(80)->wrap()->searchable()
                    ->formatStateUsing(fn (Post $r, $state) => $r->isArticle() ? $r->title : $state)
                    ->description(fn (Post $r) => '@'.$r->user?->username.($r->isArticle() ? ' · maqola, '.$r->readMinutes().' daqiqa' : '')),
                TextColumn::make('type')->label('Turi')->badge()->color(fn ($state) => $state === 'article' ? 'info' : 'gray')
                    ->formatStateUsing(fn ($state) => $state === 'article' ? 'Maqola' : 'Fikr')->toggleable(),
                TextColumn::make('category.name')->label('Kategoriya')->badge()->color('gray')->toggleable(),
                TextColumn::make('status')->label('Holat')->badge()->color(fn (Post $r) => self::statusColor($r->status)),
                TextColumn::make('visibility')->label('Ko‘rinish')->badge()->color('gray')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('likes_count')->label('Like')->numeric()->sortable(),
                TextColumn::make('comments_count')->label('Izoh')->numeric()->sortable(),
                TextColumn::make('views_count')->label('Ko‘rish')->numeric()->sortable()->toggleable(),
                TextColumn::make('ai_score')->label('AI sifat')->sortable()->placeholder('—')
                    ->color(fn ($state) => $state === null ? 'gray' : ($state >= 60 ? 'success' : ($state >= 35 ? 'warning' : 'danger'))),
                IconColumn::make('ai_flagged')->label('AI belgisi')->boolean()
                    ->trueIcon(Heroicon::OutlinedFlag)->falseIcon(null)->trueColor('danger'),
                TextColumn::make('published_at')->label('Sana')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')->label('Turi')->options(['post' => 'Fikr', 'article' => 'Maqola']),
                SelectFilter::make('status')->label('Holat')->options(PostStatus::class),
                SelectFilter::make('visibility')->label('Ko‘rinish')->options(PostVisibility::class),
                SelectFilter::make('category')->label('Kategoriya')->relationship('category', 'name'),
                TernaryFilter::make('ai_flagged')->label('AI belgilagan'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    Action::make('open')->label('Saytda ochish')->icon(Heroicon::OutlinedEye)
                        ->url(fn (Post $r) => $r->trashed() ? null : $r->url(), shouldOpenInNewTab: true),
                    ...self::moderationActions(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('hideSelected')->label('Yashirish')->icon(Heroicon::OutlinedEyeSlash)->color('warning')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $records->each(fn (Post $p) => $p->trashed() ?: app(ModerationService::class)->hidePost($p, auth()->user()));
                            Notification::make()->title('Postlar yashirildi')->success()->send();
                        }),
                    BulkAction::make('deleteSelected')->label('O‘chirish')->icon(Heroicon::OutlinedTrash)->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $records->each(fn (Post $p) => $p->trashed() ?: app(ModerationService::class)->deletePost($p, auth()->user()));
                            Notification::make()->title('Postlar o‘chirildi')->success()->send();
                        }),
                ]),
            ]);
    }

    /** @return list<Action> */
    public static function moderationActions(): array
    {
        return [
            Action::make('approve')->label('Chop etish / tasdiqlash')->icon(Heroicon::OutlinedCheckCircle)->color('success')
                ->visible(fn (Post $r) => ! $r->trashed() && in_array($r->status, [PostStatus::Hidden, PostStatus::PendingModeration], true))
                ->requiresConfirmation()
                ->action(function (Post $record) {
                    app(ModerationService::class)->publishPost($record, auth()->user());
                    Notification::make()->title('Post chop etildi')->success()->send();
                }),
            Action::make('hide')->label('Yashirish')->icon(Heroicon::OutlinedEyeSlash)->color('warning')
                ->visible(fn (Post $r) => ! $r->trashed() && $r->status !== PostStatus::Hidden)
                ->schema([Textarea::make('reason')->label('Sabab (muallifga yuboriladi)')->maxLength(300)])
                ->action(function (Post $record, array $data) {
                    app(ModerationService::class)->hidePost($record, auth()->user(), $data['reason'] ?? null);
                    Notification::make()->title('Post yashirildi')->success()->send();
                }),
            Action::make('delete')->label('O‘chirish')->icon(Heroicon::OutlinedTrash)->color('danger')
                ->visible(fn (Post $r) => ! $r->trashed())
                ->requiresConfirmation()
                ->schema([Textarea::make('reason')->label('Sabab (ichki)')->maxLength(300)])
                ->action(function (Post $record, array $data) {
                    app(ModerationService::class)->deletePost($record, auth()->user(), $data['reason'] ?? null);
                    Notification::make()->title('Post o‘chirildi')->success()->send();
                }),
            Action::make('restore')->label('Tiklash')->icon(Heroicon::OutlinedArrowUturnLeft)->color('gray')
                ->visible(fn (Post $r) => $r->trashed())
                ->requiresConfirmation()
                ->action(function (Post $record) {
                    app(ModerationService::class)->restorePost($record, auth()->user());
                    Notification::make()->title('Post tiklandi')->success()->send();
                }),
        ];
    }

    public static function statusColor(PostStatus $status): string
    {
        return match ($status) {
            PostStatus::Published => 'success',
            PostStatus::Draft => 'gray',
            PostStatus::Hidden => 'danger',
            PostStatus::PendingModeration => 'warning',
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosts::route('/'),
            'view' => ViewPost::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
