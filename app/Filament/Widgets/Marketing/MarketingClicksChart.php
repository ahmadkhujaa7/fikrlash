<?php

namespace App\Filament\Widgets\Marketing;

use App\Services\Marketing\MarketingService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/** Barcha reklama havolalari bosishlari — kunlar bo‘yicha. */
class MarketingClicksChart extends ChartWidget
{
    use InteractsWithPageFilters;
    use MarketingPeriod;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Havola bosishlari';

    protected ?string $description = 'Barcha reklama havolalari bo‘yicha, kunlar kesimida (botlar hisobga olinmaydi).';

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $d = app(MarketingService::class)->daily($this->periodDays(), null);

        return [
            'datasets' => [
                ['label' => 'Bosishlar', 'data' => $d['clicks'], 'backgroundColor' => '#2343b8', 'borderRadius' => 4, 'maxBarThickness' => 28],
            ],
            'labels' => $this->dayLabels($d['dates']),
        ];
    }

    protected function getOptions(): array
    {
        return ['plugins' => ['legend' => ['display' => false]]];
    }
}
