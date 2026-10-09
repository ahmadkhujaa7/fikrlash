<?php

namespace App\Filament\Resources\MarketingLinks\Pages;

use App\Filament\Resources\MarketingLinks\MarketingLinkResource;
use App\Filament\Resources\MarketingLinks\Widgets\LinkStats;
use App\Filament\Resources\MarketingLinks\Widgets\LinkTrendChart;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMarketingLink extends ViewRecord
{
    protected static string $resource = MarketingLinkResource::class;

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function getSubheading(): ?string
    {
        return $this->record->channelLabel().($this->record->partner ? ' · '.$this->record->partner : '');
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Tahrirlash'),
            DeleteAction::make()->label('Arxivlash')
                ->modalDescription('Havola endi bosishlarni hisoblamaydi, statistika saqlanadi.'),
            RestoreAction::make()->label('Qaytarish'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [LinkStats::class, LinkTrendChart::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
