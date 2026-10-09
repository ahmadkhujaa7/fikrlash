<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Admin kiritgan analitika ID'lari — tozalangan holda (sahifaga va CSP sarlavhasiga xavfsiz qo‘yiladi).
 * Noto‘g‘ri formatdagi qiymat e'tiborga olinmaydi.
 */
final class TrackingIds
{
    /** @return array{pixel: ?string, ga4: ?string, metrika: ?string} */
    public static function all(): array
    {
        $pixel = preg_replace('/\D/', '', (string) Setting::read('marketing_meta_pixel'));
        $ga4 = strtoupper(trim((string) Setting::read('marketing_ga4')));
        $metrika = preg_replace('/\D/', '', (string) Setting::read('marketing_yandex_metrika'));

        return [
            'pixel' => strlen($pixel) >= 6 && strlen($pixel) <= 20 ? $pixel : null,
            'ga4' => preg_match('/^G-[A-Z0-9]{4,20}$/', $ga4) ? $ga4 : null,
            'metrika' => strlen($metrika) >= 4 && strlen($metrika) <= 12 ? $metrika : null,
        ];
    }

    /** CSP uchun qo‘shimcha manbalar (faqat yoqilgan xizmatlar). */
    public static function csp(): array
    {
        $ids = self::all();
        $add = ['script' => [], 'img' => [], 'connect' => []];

        if ($ids['pixel']) {
            $add['script'][] = 'https://connect.facebook.net';
            $add['img'][] = 'https://www.facebook.com';
            $add['connect'] = [...$add['connect'], 'https://www.facebook.com', 'https://connect.facebook.net'];
        }
        if ($ids['ga4']) {
            $add['script'][] = 'https://www.googletagmanager.com';
            $add['img'] = [...$add['img'], 'https://www.googletagmanager.com', 'https://*.google-analytics.com'];
            $add['connect'] = [...$add['connect'], 'https://www.googletagmanager.com', 'https://*.google-analytics.com', 'https://*.analytics.google.com'];
        }
        if ($ids['metrika']) {
            $add['script'] = [...$add['script'], 'https://mc.yandex.ru', 'https://mc.yandex.com', 'https://yastatic.net'];
            $add['img'] = [...$add['img'], 'https://mc.yandex.ru', 'https://mc.yandex.com'];
            $add['connect'] = [...$add['connect'], 'https://mc.yandex.ru', 'https://mc.yandex.com'];
        }

        return array_map(fn ($hosts) => implode(' ', array_unique($hosts)), $add);
    }
}
