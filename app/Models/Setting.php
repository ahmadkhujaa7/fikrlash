<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Admin paneldan boshqariladigan tizim sozlamalari (key => JSON value).
 */
class Setting extends Model
{
    public const CACHE_KEY = 'settings:all';

    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    /** Standart qiymatlar — DB'da yozuv bo‘lmasa ishlatiladi. */
    public const DEFAULTS = [
        'registration_open' => true,
        'ai_enabled' => true,
        'ai_auto_moderation' => true,
        'announcement' => null,
        // Brending
        'site_name' => 'Fikrlash.uz',
        'logo_light' => null,
        'logo_dark' => null,
        'favicon' => null,
        // Bosh sahifadagi "Kun savoli" — foydalanuvchilarni yozishga undaydi.
        'daily_question' => null,
    ];

    public static function read(string $key, mixed $default = null): mixed
    {
        $all = Cache::remember(self::CACHE_KEY, now()->addMinutes(10), fn () => self::query()->pluck('value', 'key')->all());

        return array_key_exists($key, $all) ? $all[$key] : ($default ?? self::DEFAULTS[$key] ?? null);
    }

    public static function write(string $key, mixed $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_KEY);
    }
}
