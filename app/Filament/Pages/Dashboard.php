<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CategoryPopularityChart;
use App\Filament\Widgets\DailyActivityChart;
use App\Filament\Widgets\EngagementChart;
use App\Filament\Widgets\PlatformStats;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Boshqaruv paneli';

    public function getWidgets(): array
    {
        return [PlatformStats::class, DailyActivityChart::class, CategoryPopularityChart::class, EngagementChart::class];
    }

    public function getColumns(): int|array
    {
        return 2;
    }
}
