<?php

namespace App\Filament\Widgets\Marketing;

use App\Services\Marketing\MarketingService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/** Yangi a’zolar qayerdan keldi: kampaniya havolasi, do‘st taklifi, UTM, boshqa sayt, to‘g‘ridan-to‘g‘ri. */
class MarketingSourcesChart extends ChartWidget
{
    use InteractsWithPageFilters;
    use MarketingPeriod;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Yangi a’zolar qayerdan keldi';

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = app(MarketingService::class)->sources($this->periodStart());
        $labels = MarketingService::SOURCES + ['unknown' => 'Aniqlanmagan (tizim oldidan)'];

        return [
            'datasets' => [
                ['label' => 'A’zolar', 'data' => $rows->values()->all(), 'backgroundColor' => '#1b7a74', 'borderRadius' => 4, 'maxBarThickness' => 22],
            ],
            'labels' => $rows->keys()->map(fn ($k) => $labels[$k] ?? $k)->all(),
        ];
    }

    protected function getOptions(): array
    {
        return ['indexAxis' => 'y', 'plugins' => ['legend' => ['display' => false]]];
    }
}
