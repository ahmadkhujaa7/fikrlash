<?php

namespace App\Filament\Widgets\Marketing;

use Illuminate\Support\Carbon;

/** Marketing paneli vidjetlari uchun tanlangan davr (sahifa filtri: 7 / 30 / 90 / 365 kun). */
trait MarketingPeriod
{
    protected function periodDays(): int
    {
        $days = (int) ($this->pageFilters['period'] ?? 30);

        return in_array($days, [7, 30, 90, 365], true) ? $days : 30;
    }

    protected function periodStart(): Carbon
    {
        return today()->subDays($this->periodDays() - 1);
    }

    /** @param list<string> $dates */
    protected function dayLabels(array $dates): array
    {
        return array_map(fn ($d) => Carbon::parse($d)->format('d.m'), $dates);
    }
}
