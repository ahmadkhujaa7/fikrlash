<?php

namespace App\Filament\Widgets\Marketing;

use App\Services\Marketing\MarketingService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/** Kunlik yangi a’zolar: hammasi va shundan reklama havolalari orqali kelganlar. */
class MarketingTrendChart extends ChartWidget
{
    use InteractsWithPageFilters;
    use MarketingPeriod;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Yangi a’zolar';

    protected ?string $description = 'Kunlar bo‘yicha: barcha ro‘yxatdan o‘tganlar va shundan havolalar orqali kelganlar.';

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $d = app(MarketingService::class)->daily($this->periodDays(), null);

        return [
            'datasets' => [
                ['label' => 'Barcha yangi a’zolar', 'data' => $d['all'], 'borderColor' => '#78716c', 'backgroundColor' => '#78716c', 'tension' => .3, 'borderWidth' => 2, 'pointRadius' => 0, 'pointHoverRadius' => 4],
                ['label' => 'Havolalar orqali', 'data' => $d['signups'], 'borderColor' => '#1b7a74', 'backgroundColor' => 'rgba(27,122,116,.10)', 'fill' => true, 'tension' => .3, 'borderWidth' => 2, 'pointRadius' => 0, 'pointHoverRadius' => 4],
            ],
            'labels' => $this->dayLabels($d['dates']),
        ];
    }
}
