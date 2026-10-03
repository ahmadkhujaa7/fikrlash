<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EngagementChart extends ChartWidget
{
    protected ?string $heading = 'Engagement (14 kun: like va saqlashlar)';

    protected static ?int $sort = 3;

    protected ?string $maxHeight = '300px';

    protected ?string $pollingInterval = null;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $days = collect(range(13, 0))->map(fn ($d) => today()->subDays($d)->toDateString());
        $count = fn (string $table) => DB::table($table)->where('created_at', '>=', today()->subDays(13))
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')->groupBy('d')->pluck('c', 'd');
        $likes = $count('post_likes');
        $saves = $count('saved_posts');

        return [
            'datasets' => [
                ['label' => 'Like', 'data' => $days->map(fn ($d) => (int) ($likes[$d] ?? 0))->all(), 'backgroundColor' => '#e11d48'],
                ['label' => 'Saqlash', 'data' => $days->map(fn ($d) => (int) ($saves[$d] ?? 0))->all(), 'backgroundColor' => '#1f6f5c'],
            ],
            'labels' => $days->map(fn ($d) => Carbon::parse($d)->format('d.m'))->all(),
        ];
    }
}
