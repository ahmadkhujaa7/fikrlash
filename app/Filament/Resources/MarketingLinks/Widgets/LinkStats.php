<?php

namespace App\Filament\Resources\MarketingLinks\Widgets;

use App\Models\MarketingLink;
use App\Services\Marketing\MarketingService;
use App\Services\Monetization\MonetizationService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

/** Bitta havola voronkasi: bosish → ro‘yxatni boshlash → ro‘yxatdan o‘tish → faollik → qaytish; CPA. */
class LinkStats extends StatsOverviewWidget
{
    public ?MarketingLink $record = null;

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        if (! $this->record) {
            return [];
        }

        $service = app(MarketingService::class);
        $f = $service->funnel(Carbon::create(2000), $this->record);
        $daily = $service->daily(14, $this->record);
        $pct = fn (int $part, int $of) => $of ? round($part / $of * 100, 1).'%' : '—';
        $cpa = $this->record->costPerSignup();

        return [
            Stat::make('Bosishlar', Number::format($f['clicks']))
                ->description(Number::format($f['visitors']).' noyob tashrifchi'.($f['members'] ? ' · '.Number::format($f['members']).' tasi mavjud a’zo' : ''))
                ->descriptionIcon(Heroicon::OutlinedCursorArrowRays)->chart($daily['clicks'])->color('info'),
            Stat::make('Ro‘yxatni boshladi', Number::format($f['starts']))
                ->description('ma’lumot kiritib, SMS bosqichiga o‘tdi · '.$pct($f['starts'], $f['visitors']))->descriptionIcon(Heroicon::OutlinedPencilSquare)
                ->color('gray'),
            Stat::make('Ro‘yxatdan o‘tdi', Number::format($f['signups']))
                ->description('konversiya '.($f['conversion'] !== null ? $f['conversion'].'%' : '—').' (noyob tashrifchidan)')->descriptionIcon(Heroicon::OutlinedUserPlus)
                ->chart($daily['signups'])->color('success'),
            Stat::make('Faol bo‘ldi', Number::format($f['activated']))
                ->description('post yoki izoh yozgan · '.$pct($f['activated'], $f['signups']))->descriptionIcon(Heroicon::OutlinedChatBubbleLeftRight)
                ->color('primary'),
            Stat::make('Qaytib keldi', Number::format($f['returned']))
                ->description('ertasi kuni yoki keyinroq kirgan · '.$pct($f['returned'], $f['signups']))->descriptionIcon(Heroicon::OutlinedArrowPath)
                ->color('primary'),
            Stat::make('1 a’zo narxi', $cpa !== null ? MonetizationService::money(round($cpa)) : '—')
                ->description($this->record->cost !== null ? 'xarajat: '.MonetizationService::money((float) $this->record->cost) : 'xarajat kiritilmagan')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)->color('warning'),
        ];
    }
}
