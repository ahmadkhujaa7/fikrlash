<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\Account\VerificationService;
use App\Services\Social\AuditLogger;
use App\Support\PhoneNumber;
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
        $verified = (bool) ($data['is_verified'] ?? false);
        unset($data['is_verified']);

        $user = new User;
        // role va status fillable emas — admin forma orqali ataylab belgilaydi.
        $user->forceFill([
            ...$data,
            'phone' => PhoneNumber::normalize($data['phone']),
            'phone_verified_at' => now(),
        ])->save();

        app(AuditLogger::class)->log('user.created_by_admin', $user, [], $user->only(['name', 'username', 'role', 'status']));

        if ($verified) {
            app(VerificationService::class)->verify($user, auth()->user());
        }

        return $user;
    }

    protected function getRedirectUrl(): string
    {
        return UserResource::getUrl('view', ['record' => $this->record]);
    }
}
