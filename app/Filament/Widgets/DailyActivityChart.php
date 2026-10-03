<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DailyActivityChart extends ChartWidget
{
    protected ?string $heading = 'Kunlik faollik (30 kun)';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

    protected ?string $pollingInterval = null;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $days = collect(range(29, 0))->map(fn ($d) => today()->subDays($d)->toDateString());
        $count = fn (string $table, string $column = 'created_at') => DB::table($table)
            ->where($column, '>=', today()->subDays(29))
            ->selectRaw("DATE({$column}) as d, COUNT(*) as c")->groupBy('d')->pluck('c', 'd');

        $users = $count('users');
        $posts = $count('posts');
        $comments = $count('comments');
        $active = $count('users', 'last_active_at');

        $series = fn ($rows) => $days->map(fn ($d) => (int) ($rows[$d] ?? 0))->all();

        return [
            'datasets' => [
                ['label' => 'Yangi foydalanuvchilar', 'data' => $series($users), 'borderColor' => '#1f6f5c', 'backgroundColor' => 'rgba(31,111,92,.1)', 'tension' => .3],
                ['label' => 'Postlar', 'data' => $series($posts), 'borderColor' => '#d97706', 'backgroundColor' => 'rgba(217,119,6,.1)', 'tension' => .3],
                ['label' => 'Izohlar', 'data' => $series($comments), 'borderColor' => '#6366f1', 'backgroundColor' => 'rgba(99,102,241,.1)', 'tension' => .3],
                ['label' => 'Faol foydalanuvchilar (oxirgi faollik)', 'data' => $series($active), 'borderColor' => '#94a3b8', 'borderDash' => [4, 4], 'tension' => .3],
            ],
            'labels' => $days->map(fn ($d) => Carbon::parse($d)->format('d.m'))->all(),
        ];
    }
}
