<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Resources\Announcements\AnnouncementResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

/** Tahrirlangan matn/rasm barcha qabul qiluvchilarda darhol yangilanadi (qayta yuborilmaydi). */
class EditAnnouncement extends EditRecord
{
    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->label('Statistika'),
            DeleteAction::make()->modalDescription('E’lon barcha foydalanuvchilarning bildirishnomalaridan o‘chiriladi.'),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Saqlandi — foydalanuvchilarda yangilandi';
    }

    protected function getRedirectUrl(): string
    {
        return AnnouncementResource::getUrl('view', ['record' => $this->record]);
    }
}
