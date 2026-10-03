<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class CategoryPopularityChart extends ChartWidget
{
    protected ?string $heading = 'Kategoriyalar (30 kun: postlar va like)';

    protected static ?int $sort = 2;

    protected ?string $maxHeight = '300px';

    protected ?string $pollingInterval = null;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = DB::table('categories')
            ->leftJoin('posts', fn ($j) => $j->on('posts.category_id', '=', 'categories.id')
                ->where('posts.published_at', '>=', now()->subDays(30))->whereNull('posts.deleted_at'))
            ->groupBy('categories.id', 'categories.name')
            ->selectRaw('categories.name, COUNT(posts.id) as posts, COALESCE(SUM(posts.likes_count), 0) as likes')
            ->orderByDesc('posts')->get();

        return [
            'datasets' => [
                ['label' => 'Postlar', 'data' => $rows->pluck('posts')->map(fn ($v) => (int) $v)->all(), 'backgroundColor' => '#1f6f5c'],
                ['label' => 'Like', 'data' => $rows->pluck('likes')->map(fn ($v) => (int) $v)->all(), 'backgroundColor' => '#f59e0b'],
            ],
            'labels' => $rows->pluck('name')->all(),
        ];
    }
}
