<?php

namespace App\Filament\Widgets\Marketing;

use App\Models\MarketingLink;
use App\Models\User;
use App\Services\Marketing\MarketingService;
use App\Services\Monetization\MonetizationService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/** Marketing voronkasi tanlangan davr uchun: bosish → boshlash → a’zo → faol → qaytgan; takliflar; CPA. */
class MarketingFunnelStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;
    use MarketingPeriod;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $service = app(MarketingService::class);
        $since = $this->periodStart();
        $days = $this->periodDays();
        $f = $service->funnel($since, null);
        $daily = $service->daily(min($days, 30), null);
        $pct = fn (int $part, int $of) => $of ? round($part / $of * 100, 1).'%' : '—';

        $members = User::query()->where('created_at', '>=', $since)->count();
        $tracked = User::query()->where('created_at', '>=', $since)->whereIn('acquisition_source', ['link', 'invite', 'utm'])->count();
        $invited = User::query()->where('created_at', '>=', $since)->whereNotNull('referred_by');
        $invitedCount = (clone $invited)->count();
        $inviters = (clone $invited)->distinct()->count('referred_by');

        $paid = MarketingLink::query()->whereNotNull('cost')->where('cost', '>', 0);
        $cost = (float) (clone $paid)->sum('cost');
        $paidSignups = (int) (clone $paid)->sum('signups_count');
        $live = MarketingLink::query()->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count();

        return [
            Stat::make('Yangi a’zolar', Number::format($members))
                ->description($pct($tracked, $members).' — havola, taklif yoki reklama orqali')->descriptionIcon(Heroicon::OutlinedUsers)
                ->chart($daily['all'])->color('gray'),
            Stat::make('Havola bosishlari', Number::format($f['clicks']))
                ->description(Number::format($f['visitors']).' noyob tashrifchi')->descriptionIcon(Heroicon::OutlinedCursorArrowRays)
                ->chart($daily['clicks'])->color('info'),
            Stat::make('Ro‘yxatni boshladi', Number::format($f['starts']))
                ->description('havola orqali kelib, SMS bosqichiga yetdi · '.$pct($f['starts'], $f['visitors']))->descriptionIcon(Heroicon::OutlinedPencilSquare)
                ->color('gray'),
            Stat::make('Havolalar orqali a’zo', Number::format($f['signups']))
                ->description('konversiya '.($f['conversion'] !== null ? $f['conversion'].'%' : '—'))->descriptionIcon(Heroicon::OutlinedUserPlus)
                ->chart($daily['signups'])->color('success'),
            Stat::make('Faol bo‘ldi', Number::format($f['activated']))
                ->description('post yoki izoh yozdi · '.$pct($f['activated'], $f['signups']))->descriptionIcon(Heroicon::OutlinedChatBubbleLeftRight)
                ->color('primary'),
            Stat::make('Qaytib keldi', Number::format($f['returned']))
                ->description('ertasi kuni yoki keyin kirdi · '.$pct($f['returned'], $f['signups']))->descriptionIcon(Heroicon::OutlinedArrowPath)
                ->color('primary'),
            Stat::make('Do‘st taklifi bilan', Number::format($invitedCount))
                ->description(Number::format($inviters).' kishi taklif qildi')->descriptionIcon(Heroicon::OutlinedGift)
                ->color('warning'),
            Stat::make('O‘rtacha 1 a’zo narxi', $paidSignups ? MonetizationService::money(round($cost / $paidSignups)) : '—')
                ->description('faol havolalar: '.$live.($cost ? ' · xarajat: '.MonetizationService::money($cost) : ''))
                ->descriptionIcon(Heroicon::OutlinedBanknotes)->color('warning'),
        ];
    }
}
