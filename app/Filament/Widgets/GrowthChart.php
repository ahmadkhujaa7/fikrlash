<?php

namespace App\Filament\Widgets;

use App\Models\LoginEvent;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** O‘sish va faollik: ro‘yxatdan o‘tish, kirgan foydalanuvchilar, postlar, izohlar (7/30/90 kun). */
class GrowthChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'O‘sish va faollik';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '300px';

    public ?string $filter = '30';

    protected function getFilters(): ?array
    {
        return ['7' => '7 kun', '30' => '30 kun', '90' => '90 kun'];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $n = (int) ($this->filter ?: 30);
        $since = today()->subDays($n - 1);
        $days = collect(range($n - 1, 0))->map(fn ($d) => today()->subDays($d)->toDateString());
        $count = fn (string $table) => DB::table($table)->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')->groupBy('d')->pluck('c', 'd');
        $logins = LoginEvent::query()->where('event', 'login')->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as d, COUNT(DISTINCT user_id) as c')->groupBy('d')->pluck('c', 'd');
        $series = fn ($rows) => $days->map(fn ($d) => (int) ($rows[$d] ?? 0))->all();

        return [
            'datasets' => [
                ['label' => 'Ro‘yxatdan o‘tganlar', 'data' => $series($count('users')), 'borderColor' => '#2343b8', 'backgroundColor' => 'rgba(35,67,184,.08)', 'fill' => true, 'tension' => .3],
                ['label' => 'Kirgan foydalanuvchilar', 'data' => $series($logins), 'borderColor' => '#1b7a74', 'tension' => .3],
                ['label' => 'Postlar', 'data' => $series($count('posts')), 'borderColor' => '#44403c', 'tension' => .3],
                ['label' => 'Izohlar', 'data' => $series($count('comments')), 'borderColor' => '#b8741a', 'borderDash' => [4, 4], 'tension' => .3],
            ],
            'labels' => $days->map(fn ($d) => Carbon::parse($d)->format('d.m'))->all(),
        ];
    }
}
