<?php

namespace App\Filament\Resources\MarketingLinks\Pages;

use App\Filament\Resources\MarketingLinks\MarketingLinkResource;
use App\Services\Social\AuditLogger;
use Filament\Resources\Pages\CreateRecord;

class CreateMarketingLink extends CreateRecord
{
    protected static string $resource = MarketingLinkResource::class;

    protected static ?string $title = 'Yangi reklama havolasi';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data + ['created_by' => auth()->id()];
    }

    protected function afterCreate(): void
    {
        app(AuditLogger::class)->log('marketing.link_created', $this->record, [], ['name' => $this->record->name, 'code' => $this->record->code]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Havola tayyor: '.$this->record->url();
    }

    protected function getRedirectUrl(): string
    {
        return MarketingLinkResource::getUrl('view', ['record' => $this->record]);
    }
}
