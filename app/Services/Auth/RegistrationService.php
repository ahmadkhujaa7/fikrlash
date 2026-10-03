<?php

namespace App\Services\Auth;

use App\Enums\OtpPurpose;
use App\Events\UserRegistered;
use App\Exceptions\OtpException;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Ro‘yxatdan o‘tish 2 bosqichda:
 *  1) ma'lumotlar tekshiriladi, keshda vaqtincha saqlanadi (parol allaqachon hash), SMS yuboriladi;
 *  2) OTP tasdiqlangach foydalanuvchi yaratiladi.
 * Telefon tasdiqlanmaguncha akkaunt yaratilmaydi — begona raqamni "band qilib qo‘yish" imkonsiz.
 */
class RegistrationService
{
    public function __construct(private OtpService $otp) {}

    /** @param array{name: string, username: string, phone: string, password: string} $data */
    public function start(array $data, ?string $ip): string
    {
        $token = Str::random(48);

        Cache::put($this->key($token), [
            'name' => $data['name'],
            'username' => $data['username'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
        ], now()->addMinutes(config('fikrlash.registration.pending_ttl_minutes')));

        $this->otp->send($data['phone'], OtpPurpose::Register, $ip);

        return $token;
    }

    public function pending(?string $token): ?array
    {
        return $token ? Cache::get($this->key($token)) : null;
    }

    public function resend(string $token, ?string $ip): void
    {
        $data = $this->pendingOrFail($token);
        $this->otp->send($data['phone'], OtpPurpose::Register, $ip);
    }

    public function complete(string $token, string $code): User
    {
        $data = $this->pendingOrFail($token);
        $this->otp->verify($data['phone'], OtpPurpose::Register, $code);

        $user = DB::transaction(function () use ($data) {
            // Kod kutilayotgan paytda boshqa birov shu username/telefonni olgan bo‘lishi mumkin.
            if (User::withTrashed()->where('username', $data['username'])->exists()) {
                throw ValidationException::withMessages(['username' => 'Bu username band bo‘lib qoldi. Boshqasini tanlang.']);
            }
            if (User::withTrashed()->where('phone', $data['phone'])->exists()) {
                throw ValidationException::withMessages(['phone' => 'Bu telefon raqam allaqachon ro‘yxatdan o‘tgan.']);
            }

            $user = new User;
            $user->forceFill([
                'name' => $data['name'],
                'username' => $data['username'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'phone_verified_at' => now(),
            ])->save();

            return $user;
        });

        Cache::forget($this->key($token));
        UserRegistered::dispatch($user);

        return $user;
    }

    private function pendingOrFail(string $token): array
    {
        return $this->pending($token)
            ?? throw new OtpException('Ro‘yxatdan o‘tish sessiyasi tugadi. Qaytadan boshlang.');
    }

    private function key(string $token): string
    {
        return 'registration:'.hash('sha256', $token);
    }
}
