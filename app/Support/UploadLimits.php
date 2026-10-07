<?php

namespace App\Support;

/**
 * PHP sozlamalaridagi yuklash chegaralari (upload_max_filesize, post_max_size) baytlarda.
 * Brauzer katta faylni yuborishdan oldin ogohlantirishi uchun — server rad etishini kutib o‘tirmaydi.
 */
final class UploadLimits
{
    /** Bitta fayl uchun. */
    public static function perFile(): int
    {
        return self::parse((string) ini_get('upload_max_filesize'));
    }

    /** Bitta so‘rov (barcha fayllar + forma) uchun. */
    public static function perRequest(): int
    {
        $post = self::parse((string) ini_get('post_max_size'));

        return $post > 0 ? $post : PHP_INT_MAX;
    }

    /** "64M", "2G", "512K", "1048576" → bayt. 0 yoki bo‘sh — cheklovsiz. */
    public static function parse(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '0' || $value === '-1') {
            return PHP_INT_MAX;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
