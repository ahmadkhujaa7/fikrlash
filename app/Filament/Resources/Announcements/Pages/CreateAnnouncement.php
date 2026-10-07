<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Models\Announcement;
use App\Services\Social\AnnouncementService;
use App\Services\Social\AuditLogger;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Yaratilishi bilan yuboriladi: har bir qabul qiluvchiga bildirishnoma qatori qo‘shiladi. */
class CreateAnnouncement extends CreateRecord
{
    protected static string $resource = AnnouncementResource::class;

    protected static ?string $title = 'Yangi e’lon';

    protected function handleRecordCreation(array $data): Model
    {
        $userIds = array_map('intval', $data['user_ids'] ?? []);
        unset($data['user_ids']);

        return DB::transaction(function () use ($data, $userIds) {
            $announcement = Announcement::query()->create($data + ['created_by' => auth()->id()]);
            $count = app(AnnouncementService::class)->publish($announcement, $userIds);
            app(AuditLogger::class)->log('announcement.sent', $announcement, [], ['title' => $announcement->title, 'audience' => $announcement->audience, 'recipients' => $count]);

            return $announcement;
        });
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'E’lon '.number_format((int) $this->record->recipients_count, 0, '.', ' ').' ta foydalanuvchiga yuborildi';
    }

    protected function getRedirectUrl(): string
    {
        return AnnouncementResource::getUrl('view', ['record' => $this->record]);
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Yuborish');
    }
}
