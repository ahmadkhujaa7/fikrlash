<?php

namespace App\Services\Sms;

use App\Contracts\SmsProvider;
use App\Exceptions\SmsDeliveryException;
use App\Support\PhoneNumber;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Eskiz.uz SMS shlyuzi (https://documenter.getpostman.com/view/663428/RzfmES4z).
 * Token 30 kun amal qiladi — keshda saqlanadi, 401 kelsa yangilanadi.
 */
class EskizSmsProvider implements SmsProvider
{
    private const TOKEN_CACHE_KEY = 'sms:eskiz:token';

    public function send(string $phone, string $message): void
    {
        $response = $this->request()->post('/message/sms/send', $this->payload($phone, $message));

        if ($response->status() === 401) {
            Cache::forget(self::TOKEN_CACHE_KEY);
            $response = $this->request()->post('/message/sms/send', $this->payload($phone, $message));
        }

        if ($response->failed()) {
            Log::error('Eskiz SMS yuborilmadi', ['phone' => PhoneNumber::mask($phone), 'status' => $response->status()]);
            throw new SmsDeliveryException('SMS yuborishda xatolik.');
        }
    }

    private function payload(string $phone, string $message): array
    {
        return [
            'mobile_phone' => ltrim($phone, '+'),
            'message' => $message,
            'from' => config('sms.sender_name'),
        ];
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(config('sms.eskiz.base_url'))
            ->timeout(config('sms.eskiz.timeout'))
            ->acceptJson()
            ->withToken($this->token());
    }

    private function token(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, now()->addDays(25), function () {
            $response = Http::baseUrl(config('sms.eskiz.base_url'))
                ->timeout(config('sms.eskiz.timeout'))
                ->asForm()
                ->post('/auth/login', [
                    'email' => config('sms.eskiz.email'),
                    'password' => config('sms.eskiz.password'),
                ]);

            $token = $response->json('data.token');
            if ($response->failed() || ! is_string($token)) {
                Log::error('Eskiz autentifikatsiya xatosi', ['status' => $response->status()]);
                throw new SmsDeliveryException('SMS shlyuziga ulanib bo‘lmadi.');
            }

            return $token;
        });
    }
}
