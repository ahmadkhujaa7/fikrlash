<?php

namespace App\Services\Auth;

use App\Enums\OtpPurpose;
use App\Exceptions\OtpException;
use App\Jobs\SendOtpJob;
use App\Models\PhoneVerification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * SMS orqali bir martalik kodlar (OTP).
 *
 * Himoyalar: kod HMAC bilan hash qilinadi, TTL, maksimal urinishlar (atomik),
 * qayta yuborish cooldown, telefon va IP bo‘yicha kunlik limit, kodni qayta ishlatib bo‘lmaydi.
 */
class OtpService
{
    private const DAY = 86_400;

    public function send(string $phone, OtpPurpose $purpose, ?string $ip = null): void
    {
        $wait = $this->secondsUntilResend($phone, $purpose);
        if ($wait > 0) {
            throw new OtpException("Yangi kodni {$wait} soniyadan keyin so‘rashingiz mumkin.", $wait);
        }

        $phoneKey = 'otp:daily:phone:'.$phone;
        $ipKey = 'otp:daily:ip:'.($ip ?? 'none');

        if (RateLimiter::tooManyAttempts($phoneKey, config('fikrlash.otp.daily_limit_per_phone'))
            || ($ip && RateLimiter::tooManyAttempts($ipKey, config('fikrlash.otp.daily_limit_per_ip')))) {
            Log::channel('security')->warning('OTP kunlik limitga yetdi', ['purpose' => $purpose->value, 'ip' => $ip]);

            throw new OtpException('Bugungi SMS limiti tugadi. Ertaga qayta urinib ko‘ring.', self::DAY);
        }

        RateLimiter::hit($phoneKey, self::DAY);
        if ($ip) {
            RateLimiter::hit($ipKey, self::DAY);
        }

        $code = $this->generateCode();

        DB::transaction(function () use ($phone, $purpose, $ip, $code) {
            // Avvalgi faol kodlar bekor qilinadi — bir vaqtda faqat bitta kod amal qiladi.
            PhoneVerification::query()
                ->where('phone', $phone)->where('purpose', $purpose)->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            PhoneVerification::query()->create([
                'phone' => $phone,
                'purpose' => $purpose,
                'code_hash' => $this->hash($phone, $purpose, $code),
                'expires_at' => now()->addMinutes(config('fikrlash.otp.ttl_minutes')),
                'ip_address' => $ip,
            ]);

            // Log drayveri (lokal ishlab chiqish): navbat ishchisi ishlamasa ham kod darhol logga va
            // tasdiqlash sahifasidagi "sinov rejimi" yozuviga tushadi. Haqiqiy SMS — navbat orqali.
            if (config('sms.driver') === 'log') {
                DB::afterCommit(fn () => SendOtpJob::dispatchSync($phone, $code));
            } else {
                SendOtpJob::dispatch($phone, $code)->afterCommit();
            }
        });
    }

    /**
     * @throws OtpException
     */
    public function verify(string $phone, OtpPurpose $purpose, string $code): void
    {
        $max = (int) config('fikrlash.otp.max_attempts');

        $record = PhoneVerification::query()
            ->where('phone', $phone)->where('purpose', $purpose)
            ->whereNull('consumed_at')->where('expires_at', '>', now())
            ->latest('id')->first();

        if (! $record) {
            throw new OtpException('Kod muddati tugagan yoki topilmadi. Yangi kod so‘rang.');
        }

        // Urinishni atomik hisoblaymiz: parallel so‘rovlar limitni chetlab o‘ta olmaydi.
        $counted = PhoneVerification::query()->whereKey($record->id)
            ->where('attempts', '<', $max)->increment('attempts');

        if ($counted === 0) {
            throw new OtpException('Urinishlar soni tugadi. Yangi kod so‘rang.');
        }

        if (! hash_equals($record->code_hash, $this->hash($phone, $purpose, trim($code)))) {
            $left = $max - $record->attempts - 1;
            Log::channel('security')->notice('Noto‘g‘ri OTP', ['purpose' => $purpose->value, 'left' => $left]);

            throw new OtpException($left > 0
                ? "Kod noto‘g‘ri. Yana {$left} ta urinish qoldi."
                : 'Kod noto‘g‘ri. Urinishlar tugadi — yangi kod so‘rang.');
        }

        $consumed = PhoneVerification::query()->whereKey($record->id)
            ->whereNull('consumed_at')->update(['consumed_at' => now()]);

        if ($consumed !== 1) {
            throw new OtpException('Bu kod allaqachon ishlatilgan.');
        }
    }

    public function secondsUntilResend(string $phone, OtpPurpose $purpose): int
    {
        $last = PhoneVerification::query()
            ->where('phone', $phone)->where('purpose', $purpose)
            ->latest('id')->value('created_at');

        if (! $last) {
            return 0;
        }

        $availableAt = Carbon::parse($last)->addSeconds(config('fikrlash.otp.resend_cooldown_seconds'));

        return max(0, (int) ceil(now()->diffInSeconds($availableAt, false)));
    }

    private function generateCode(): string
    {
        $length = (int) config('fikrlash.otp.length');

        return str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);
    }

    private function hash(string $phone, OtpPurpose $purpose, string $code): string
    {
        return hash_hmac('sha256', "{$phone}|{$purpose->value}|{$code}", (string) config('app.key'));
    }
}
