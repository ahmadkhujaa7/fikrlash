<?php

namespace App\Services\Sms;

use App\Contracts\SmsProvider;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/** Development drayveri: SMS yuborilmaydi, matn storage/logs/laravel.log ga yoziladi. */
class LogSmsProvider implements SmsProvider
{
    public function send(string $phone, string $message): void
    {
        if (app()->isProduction()) {
            // Productionda OTP kodini logga yozish — xavfsizlik teshigi.
            Log::warning('SMS log drayveri productionda ishlatilmoqda — xabar yuborilmadi.', ['phone' => PhoneNumber::mask($phone)]);

            return;
        }

        Log::info("[SMS] {$phone}: {$message}");

        // Lokal sinov uchun: oxirgi SMS tasdiqlash sahifasida ko‘rsatiladi (faqat log drayverida, productionda emas).
        if (preg_match('/\b(\d{6})\b/', $message, $m)) {
            Cache::put(self::cacheKey($phone), $m[1], now()->addMinutes(10));
        }
    }

    public static function lastCode(string $phone): ?string
    {
        if (app()->isProduction() || config('sms.driver') !== 'log') {
            return null;
        }

        return Cache::get(self::cacheKey($phone));
    }

    private static function cacheKey(string $phone): string
    {
        return 'dev-sms:'.hash('sha256', $phone);
    }
}
