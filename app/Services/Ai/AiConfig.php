<?php

namespace App\Services\Ai;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * AI sozlamalarining yagona manbai.
 *
 * Admin paneldagi qiymat (settings jadvali) ustun; u bo‘lmasa — config/ai.php (.env).
 * Shu sababli .env orqali sozlangan eski o‘rnatishlar o‘zgarishsiz ishlaydi, admin esa
 * provayder, kalit, model va chegaralarni serverga kirmasdan almashtira oladi.
 * API kalitlar bazada shifrlangan holda saqlanadi.
 */
final class AiConfig
{
    public const PROVIDERS = [
        'claude' => 'Claude (Anthropic)',
        'openai' => 'OpenAI',
        'fake' => 'Sinov rejimi — kalitsiz, kalit so‘zlar bo‘yicha',
        'null' => 'O‘chirilgan',
    ];

    /** Kalit talab qiladigan provayderlar va ularning sozlanadigan maydonlari. */
    public const REMOTE = ['claude', 'openai'];

    public const FIELDS = ['model', 'base_url', 'price_input', 'price_output'];

    public static function provider(): string
    {
        $name = (string) (self::setting('ai_provider') ?? config('ai.provider'));

        return array_key_exists($name, self::PROVIDERS) ? $name : 'null';
    }

    /** Server darajasida (.env AI_ENABLED) va admin paneldagi tugma — ikkalasi yoqiq bo‘lsa. */
    public static function enabled(): bool
    {
        return (bool) config('ai.enabled') && (bool) Setting::read('ai_enabled');
    }

    /** Provayder konfiguratsiyasi: config/ai.php ustiga admin paneldagi qiymatlar. */
    public static function providerConfig(string $name): array
    {
        $config = (array) config("ai.providers.{$name}", []);
        if (! in_array($name, self::REMOTE, true)) {
            return $config;
        }

        foreach (self::FIELDS as $field) {
            $value = self::setting("ai_{$name}_{$field}");
            if ($value !== null) {
                $config[$field] = in_array($field, ['price_input', 'price_output'], true) ? (float) $value : (string) $value;
            }
        }

        $key = self::storedKey($name);
        if ($key !== null) {
            $config['api_key'] = $key;
        }

        return $config;
    }

    /** @return array{toxicity_review: int, spam_review: int, auto_category_min_quality: int} */
    public static function thresholds(): array
    {
        $defaults = (array) config('ai.thresholds');

        return collect(['toxicity_review', 'spam_review', 'auto_category_min_quality'])
            ->mapWithKeys(fn ($k) => [$k => (int) (self::setting("ai_{$k}") ?? $defaults[$k] ?? 0)])
            ->all();
    }

    public static function requestsPerMinute(): int
    {
        return max(1, (int) (self::setting('ai_requests_per_minute') ?? config('ai.requests_per_minute')));
    }

    public static function dailyLimit(): int
    {
        return max(0, (int) (self::setting('ai_daily_limit') ?? config('ai.daily_request_limit')));
    }

    /* ---------------- API kalitlar ---------------- */

    /** Admin paneldan saqlangan (shifrlangan) kalit; bo‘lmasa yoki ochilmasa — null. */
    public static function storedKey(string $name): ?string
    {
        $encrypted = self::setting("ai_{$name}_api_key");
        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException) {
            return null; // APP_KEY almashgan — kalitni qayta kiritish kerak
        }
    }

    public static function storeKey(string $name, ?string $key): void
    {
        $key = trim((string) $key);
        Setting::write("ai_{$name}_api_key", $key === '' ? null : Crypt::encryptString($key));
    }

    /** Kalit qayerdan olinmoqda: admin panel, .env yoki hech qayerdan. */
    public static function keySource(string $name): ?string
    {
        if (self::storedKey($name) !== null) {
            return 'admin';
        }

        return filled(config("ai.providers.{$name}.api_key")) ? 'env' : null;
    }

    /** "sk-ant-…a1b2" — kalitning o‘zi hech qayerda ko‘rsatilmaydi. */
    public static function maskedKey(string $name): ?string
    {
        $key = self::providerConfig($name)['api_key'] ?? null;
        if (! filled($key)) {
            return null;
        }

        return mb_substr($key, 0, min(7, (int) floor(mb_strlen($key) / 3))).'…'.mb_substr($key, -4);
    }

    /** Bo‘sh satr ham "sozlanmagan" deb hisoblanadi — .env qiymatiga qaytiladi. */
    private static function setting(string $key): mixed
    {
        $value = Setting::read($key);

        return $value === '' ? null : $value;
    }
}
