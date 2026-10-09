<?php

namespace App\Filament\Resources\MarketingLinks\Pages;

use App\Filament\Resources\MarketingLinks\MarketingLinkResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditMarketingLink extends EditRecord
{
    protected static string $resource = MarketingLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()->label('Statistika')];
    }

    protected function getRedirectUrl(): string
    {
        return MarketingLinkResource::getUrl('view', ['record' => $this->record]);
    }
}
