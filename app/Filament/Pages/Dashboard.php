<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\CategoryPopularityChart;
use App\Filament\Widgets\EngagementChart;
use App\Filament\Widgets\GrowthChart;
use App\Filament\Widgets\LatestLogins;
use App\Filament\Widgets\LiveStats;
use App\Filament\Widgets\NewUsers;
use App\Filament\Widgets\OnlineUsers;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * Boshqaruv paneli: jonli ko‘rsatkichlar (30 soniyada yangilanadi), o‘sish grafigi,
 * hozir onlayn foydalanuvchilar, so‘nggi kirishlar va yangi a’zolar.
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Boshqaruv paneli';

    public function getSubheading(): ?string
    {
        return Str::ucfirst(mb_strtolower(now()->translatedFormat('l, j-F Y'))).' · ma’lumotlar jonli yangilanadi';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newUser')->label('Yangi foydalanuvchi')->icon(Heroicon::OutlinedUserPlus)
                ->url(UserResource::getUrl('create')),
            Action::make('broadcast')->label('Ommaviy xabar')->icon(Heroicon::OutlinedMegaphone)->color('gray')
                ->url(BroadcastNotification::getUrl()),
        ];
    }

    public function getWidgets(): array
    {
        return [
            LiveStats::class,
            GrowthChart::class,
            OnlineUsers::class,
            LatestLogins::class,
            NewUsers::class,
            EngagementChart::class,
            CategoryPopularityChart::class,
        ];
    }

    public function getColumns(): int|array
    {
        return ['default' => 1, 'lg' => 2];
    }
}
