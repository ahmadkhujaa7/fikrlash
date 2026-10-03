<?php

namespace App\Services\Sms;

use App\Contracts\SmsProvider;
use App\Support\PhoneNumber;
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
    }
}
