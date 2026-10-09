<?php

namespace App\Filament\Pages;

use App\Filament\Resources\MarketingLinks\MarketingLinkResource;
use App\Filament\Widgets\Marketing\MarketingChannelsChart;
use App\Filament\Widgets\Marketing\MarketingClicksChart;
use App\Filament\Widgets\Marketing\MarketingFunnelStats;
use App\Filament\Widgets\Marketing\MarketingSourcesChart;
use App\Filament\Widgets\Marketing\MarketingTrendChart;
use App\Filament\Widgets\Marketing\TopMarketingLinks;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Marketing paneli: tanlangan davr uchun voronka (bosish → ro‘yxatdan o‘tish → faollik),
 * yangi a’zolar manbalari, kanal turlari va eng samarali reklama havolalari.
 */
class MarketingDashboard extends Dashboard
{
    use HasFiltersForm;

    protected static string $routePath = 'marketing';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Marketing paneli';

    protected static ?string $title = 'Marketing';

    public static function getNavigationLabel(): string
    {
        return static::$navigationLabel;
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return static::$navigationIcon;
    }

    public function getSubheading(): ?string
    {
        return 'Reklama havolalari, do‘st takliflari va yangi a’zolar qayerdan kelayotgani.';
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('period')->label('Davr')->selectablePlaceholder(false)->default('30')
                ->options(['7' => 'Oxirgi 7 kun', '30' => 'Oxirgi 30 kun', '90' => 'Oxirgi 90 kun', '365' => 'Oxirgi 1 yil']),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newLink')->label('Yangi havola')->icon(Heroicon::OutlinedPlus)
                ->url(MarketingLinkResource::getUrl('create')),
            Action::make('settings')->label('Sozlamalar')->icon(Heroicon::OutlinedCog6Tooth)->color('gray')
                ->url(MarketingSettings::getUrl()),
        ];
    }

    public function getWidgets(): array
    {
        return [
            MarketingFunnelStats::class,
            MarketingTrendChart::class,
            MarketingClicksChart::class,
            MarketingSourcesChart::class,
            MarketingChannelsChart::class,
            TopMarketingLinks::class,
        ];
    }

    public function getColumns(): int|array
    {
        return ['default' => 1, 'lg' => 2];
    }
}
