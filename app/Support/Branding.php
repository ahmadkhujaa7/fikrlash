<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * Sayt brendingi — admin paneldagi "Tizim sozlamalari → Brending" bo‘limidan boshqariladi.
 * Logo yuklanmagan bo‘lsa — standart serif so‘z belgisi ("fikrlash.") ko‘rsatiladi.
 */
final class Branding
{
    public static function name(): string
    {
        return trim((string) Setting::read('site_name')) ?: 'Fikrlash.uz';
    }

    public static function logoUrl(bool $dark = false): ?string
    {
        return self::url(Setting::read($dark ? 'logo_dark' : 'logo_light'));
    }

    public static function faviconUrl(): ?string
    {
        return self::url(Setting::read('favicon'));
    }

    private static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        // Fayl yangilanganda brauzer keshi eskisini ko‘rsatmasligi uchun versiya qo‘shiladi.
        return Storage::disk(config('fikrlash.media.disk'))->url($path).'?v='.substr(md5($path), 0, 8);
    }
}
