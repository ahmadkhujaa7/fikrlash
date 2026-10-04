<?php

namespace App\Filament\Pages;

use App\Filament\Resources\LoginEvents\LoginEventResource;
use App\Filament\Resources\Sessions\SessionResource;
use App\Filament\Widgets\LatestLogins;
use App\Filament\Widgets\SecurityStats;
use App\Filament\Widgets\SuspiciousIps;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Xavfsizlik markazi: kirishlar, shubhali IP'lar, adminlar faolligi. */
class Security extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Kuzatuv';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Xavfsizlik';

    protected static ?string $title = 'Xavfsizlik markazi';

    protected static ?string $slug = 'security';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('logins')->label('Kirishlar tarixi')->icon(Heroicon::OutlinedFingerPrint)->color('gray')
                ->url(LoginEventResource::getUrl('index')),
            Action::make('sessions')->label('Faol sessiyalar')->icon(Heroicon::OutlinedComputerDesktop)->color('gray')
                ->url(SessionResource::getUrl('index')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [SecurityStats::class, SuspiciousIps::class, LatestLogins::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
