<?php

namespace App\Services\Auth;

use App\Enums\OtpPurpose;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PasswordResetService
{
    public function __construct(private OtpService $otp) {}

    /**
     * Raqam ro‘yxatdan o‘tmagan bo‘lsa ham bir xil javob qaytariladi (account enumeration himoyasi).
     */
    public function sendCode(string $phone, ?string $ip): void
    {
        $user = User::query()->where('phone', $phone)->first();

        if ($user && $user->canSignIn()) {
            $this->otp->send($phone, OtpPurpose::PasswordReset, $ip);
        }
    }

    public function reset(string $phone, string $code, string $password): User
    {
        $this->otp->verify($phone, OtpPurpose::PasswordReset, $code);

        $user = User::query()->where('phone', $phone)->firstOrFail();

        DB::transaction(function () use ($user, $password) {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            // Parol tiklanganda barcha sessiya va API tokenlar bekor qilinadi.
            $user->tokens()->delete();
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
        });

        Log::channel('security')->info('Parol tiklandi', ['user_id' => $user->id]);

        return $user;
    }
}
