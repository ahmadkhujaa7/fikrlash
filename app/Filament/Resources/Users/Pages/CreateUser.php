<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Services\Account\AdminUserService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin tomonidan foydalanuvchi yaratish (masalan, taniqli muallif, tashkilot yoki xodim uchun).
 * Telefon admin tomonidan kiritilgani uchun tasdiqlangan hisoblanadi.
 */
class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'Yangi foydalanuvchi';

    protected function handleRecordCreation(array $data): Model
    {
        return app(AdminUserService::class)->create($data, auth()->user());
    }

    protected function getRedirectUrl(): string
    {
        return UserResource::getUrl('view', ['record' => $this->record]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Foydalanuvchi yaratildi';
    }
}
