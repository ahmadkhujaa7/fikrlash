<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Enums\PostStatus;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPosts extends ListRecords
{
    protected static string $resource = PostResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Barchasi'),
            'review' => Tab::make('Tekshiruvda')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', PostStatus::PendingModeration))
                ->badge(fn () => Post::query()->where('status', PostStatus::PendingModeration)->count() ?: null)
                ->badgeColor('warning'),
            'flagged' => Tab::make('AI belgilagan')->modifyQueryUsing(fn (Builder $q) => $q->where('ai_flagged', true)),
            'hidden' => Tab::make('Yashirilgan')->modifyQueryUsing(fn (Builder $q) => $q->where('status', PostStatus::Hidden)),
        ];
    }
}
