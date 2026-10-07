<?php

namespace App\Filament\Resources\Announcements\Widgets;

use App\Models\Announcement;
use App\Services\Social\AnnouncementService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/** E'lon statistikasi: yuborildi / ro‘yxatda ko‘rdi / bosib ochdi / hali ko‘rmagan. */
class AnnouncementStats extends StatsOverviewWidget
{
    public ?Announcement $record = null;

    protected ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        if (! $this->record) {
            return [];
        }

        $s = app(AnnouncementService::class)->stats($this->record);

        return [
            Stat::make('Yuborildi', Number::format($s['recipients']))
                ->description('foydalanuvchiga')->descriptionIcon(Heroicon::OutlinedPaperAirplane)->color('gray'),
            Stat::make('Ro‘yxatda ko‘rdi', Number::format($s['seen']))
                ->description($s['seen_rate'].'% — bildirishnomalar sahifasida ko‘rsatildi')->descriptionIcon(Heroicon::OutlinedEye)->color('info'),
            Stat::make('Bosib ochdi', Number::format($s['opened']))
                ->description($s['open_rate'].'% — to‘liq matnini o‘qidi')->descriptionIcon(Heroicon::OutlinedCursorArrowRays)->color('success'),
            Stat::make('Hali ko‘rmagan', Number::format(max(0, $s['recipients'] - $s['seen'])))
                ->description('bildirishnomalarni ochmagan')->descriptionIcon(Heroicon::OutlinedClock)->color('warning'),
        ];
    }
}
