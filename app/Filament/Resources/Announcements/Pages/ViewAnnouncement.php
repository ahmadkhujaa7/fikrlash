<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Filament\Resources\Announcements\Widgets\AnnouncementStats;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAnnouncement extends ViewRecord
{
    protected static string $resource = AnnouncementResource::class;

    public function getTitle(): string
    {
        return $this->record->title;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Tahrirlash'),
            DeleteAction::make()->modalDescription('E’lon barcha foydalanuvchilarning bildirishnomalaridan o‘chiriladi.'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [AnnouncementStats::class];
    }
}
