<?php

namespace App\Filament\Resources\MarketingLinks\Widgets;

use App\Models\MarketingLink;
use App\Services\Marketing\MarketingService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/** Havola bo‘yicha kunlik bosishlar va ro‘yxatdan o‘tishlar. */
class LinkTrendChart extends ChartWidget
{
    public ?MarketingLink $record = null;

    protected ?string $heading = 'Kunlar bo‘yicha';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

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
        if (! $this->record) {
            return ['datasets' => [], 'labels' => []];
        }

        $d = app(MarketingService::class)->daily((int) ($this->filter ?: 30), $this->record);

        return [
            'datasets' => [
                ['label' => 'Bosishlar', 'data' => $d['clicks'], 'borderColor' => '#2343b8', 'backgroundColor' => 'rgba(35,67,184,.08)', 'fill' => true, 'tension' => .3, 'borderWidth' => 2, 'pointRadius' => 0, 'pointHoverRadius' => 4],
                ['label' => 'Ro‘yxatdan o‘tdi', 'data' => $d['signups'], 'borderColor' => '#1b7a74', 'backgroundColor' => '#1b7a74', 'tension' => .3, 'borderWidth' => 2, 'pointRadius' => 0, 'pointHoverRadius' => 4],
            ],
            'labels' => array_map(fn ($day) => Carbon::parse($day)->format('d.m'), $d['dates']),
        ];
    }
}
