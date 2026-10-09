<?php

namespace App\Filament\Widgets\Marketing;

use App\Models\MarketingLink;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/** Havolalar orqali ro‘yxatdan o‘tganlar — kanal turlari bo‘yicha (Telegram, Instagram, bloger...). */
class MarketingChannelsChart extends ChartWidget
{
    use InteractsWithPageFilters;
    use MarketingPeriod;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Kanal turlari bo‘yicha a’zolar';

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = User::query()->where('users.created_at', '>=', $this->periodStart())
            ->join('marketing_links', 'marketing_links.id', '=', 'users.acquisition_link_id')
            ->selectRaw('marketing_links.channel as channel, COUNT(*) as total')
            ->groupBy('marketing_links.channel')->orderByDesc('total')
            ->pluck('total', 'channel');

        return [
            'datasets' => [
                ['label' => 'A’zolar', 'data' => $rows->values()->map(fn ($n) => (int) $n)->all(), 'backgroundColor' => '#1b7a74', 'borderRadius' => 4, 'maxBarThickness' => 22],
            ],
            'labels' => $rows->keys()->map(fn ($k) => MarketingLink::CHANNELS[$k] ?? $k)->all(),
        ];
    }

    protected function getOptions(): array
    {
        return ['indexAxis' => 'y', 'plugins' => ['legend' => ['display' => false]]];
    }
}
