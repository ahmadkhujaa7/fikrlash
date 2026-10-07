<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Firebase Cloud Messaging (HTTP v1) — qo‘shimcha paketsiz.
 * Kirish: Firebase "service account" JSON fayli (config fikrlash.push.credentials).
 * OAuth tokeni JWT (RS256) bilan olinadi va ~55 daqiqa keshlanadi.
 */
class FcmClient
{
    public const OK = 'ok';

    public const INVALID_TOKEN = 'invalid';

    public const FAILED = 'failed';

    private ?array $credentials = null;

    public function configured(): bool
    {
        return $this->credentials() !== null;
    }

    public function projectId(): ?string
    {
        return $this->credentials()['project_id'] ?? null;
    }

    /** @return self::OK|self::INVALID_TOKEN|self::FAILED */
    public function send(string $token, array $message): string
    {
        $credentials = $this->credentials();
        if (! $credentials) {
            return self::FAILED;
        }

        try {
            $response = Http::withToken($this->accessToken($credentials))
                ->acceptJson()->timeout(10)
                ->post("https://fcm.googleapis.com/v1/projects/{$credentials['project_id']}/messages:send", [
                    'message' => ['token' => $token] + $message,
                ]);
        } catch (Throwable $e) {
            Log::warning('FCM: yuborib bo‘lmadi', ['error' => $e->getMessage()]);

            return self::FAILED;
        }

        if ($response->successful()) {
            return self::OK;
        }

        $status = (string) $response->json('error.status');
        $code = (string) collect($response->json('error.details', []))->pluck('errorCode')->filter()->first();
        $text = (string) $response->json('error.message');

        if ($response->status() === 401) {
            Cache::forget($this->cacheKey($credentials));
        }

        // Ilova o‘chirilgan, token eskirgan yoki boshqa loyihaniki — token o‘chiriladi.
        if (in_array($code ?: $status, ['UNREGISTERED', 'SENDER_ID_MISMATCH'], true)
            || ($response->status() === 404)
            || (($code ?: $status) === 'INVALID_ARGUMENT' && str_contains(strtolower($text), 'registration token'))) {
            return self::INVALID_TOKEN;
        }

        Log::warning('FCM: xato javob', ['status' => $response->status(), 'error' => $code ?: $status, 'message' => $text]);

        return self::FAILED;
    }

    private function accessToken(array $credentials): string
    {
        return Cache::remember($this->cacheKey($credentials), now()->addMinutes(55), function () use ($credentials) {
            $now = time();
            $tokenUri = $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token';
            $jwt = $this->jwt([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $tokenUri,
                'iat' => $now,
                'exp' => $now + 3600,
            ], $credentials['private_key']);

            $token = Http::asForm()->timeout(10)->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ])->throw()->json('access_token');

            if (! is_string($token) || $token === '') {
                throw new RuntimeException('FCM: kirish tokeni olinmadi.');
            }

            return $token;
        });
    }

    private function jwt(array $claims, string $privateKey): string
    {
        $encode = fn (array $part) => rtrim(strtr(base64_encode((string) json_encode($part)), '+/', '-_'), '=');
        $input = $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode($claims);

        if (! openssl_sign($input, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('FCM: service account kaliti noto‘g‘ri.');
        }

        return $input.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    private function cacheKey(array $credentials): string
    {
        return 'fcm:access-token:'.md5($credentials['client_email']);
    }

    /** @return array{project_id: string, client_email: string, private_key: string, token_uri?: string}|null */
    private function credentials(): ?array
    {
        if ($this->credentials !== null) {
            return $this->credentials ?: null;
        }

        $source = (string) config('fikrlash.push.credentials');
        $json = match (true) {
            $source === '' => null,
            str_starts_with(ltrim($source), '{') => $source,                         // .env ichida JSON
            is_file($source) => (string) file_get_contents($source),               // fayl yo‘li
            default => null,
        };
        $data = $json ? json_decode($json, true) : null;

        $valid = is_array($data) && ! empty($data['project_id']) && ! empty($data['client_email']) && ! empty($data['private_key']);
        $this->credentials = $valid ? $data : [];

        return $valid ? $data : null;
    }
}
