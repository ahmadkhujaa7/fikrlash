<?php

namespace App\Services\Auth;

use App\Enums\OtpPurpose;
use App\Exceptions\OtpException;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PhoneChangeService
{
    public function __construct(private OtpService $otp) {}

    public function start(User $user, string $newPhone, ?string $ip): void
    {
        Cache::put($this->key($user), $newPhone, now()->addMinutes(config('fikrlash.otp.ttl_minutes') * 2));
        $this->otp->send($newPhone, OtpPurpose::PhoneChange, $ip);
    }

    public function pendingPhone(User $user): ?string
    {
        return Cache::get($this->key($user));
    }

    public function complete(User $user, string $code): void
    {
        $phone = $this->pendingPhone($user) ?? throw new OtpException('So‘rov muddati tugadi. Qaytadan boshlang.');
        $this->otp->verify($phone, OtpPurpose::PhoneChange, $code);

        if (User::withTrashed()->where('phone', $phone)->whereKeyNot($user->id)->exists()) {
            throw ValidationException::withMessages(['phone' => 'Bu raqam boshqa akkauntga bog‘langan.']);
        }

        $user->forceFill(['phone' => $phone, 'phone_verified_at' => now()])->save();
        Cache::forget($this->key($user));
        Log::channel('security')->info('Telefon raqam almashtirildi', ['user_id' => $user->id]);
    }

    private function key(User $user): string
    {
        return "phone-change:{$user->id}";
    }
}
