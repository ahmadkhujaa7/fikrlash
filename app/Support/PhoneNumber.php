<?php

namespace App\Support;

/**
 * O‘zbekiston telefon raqamlarini E.164 formatga keltirish: +998XXXXXXXXX.
 * Bir raqamning turli yozilishlari unique cheklovni chetlab o‘tmasligi uchun majburiy.
 */
final class PhoneNumber
{
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $input) ?? '';
        $code = (string) config('fikrlash.phone.country_code', '998');
        $length = (int) config('fikrlash.phone.national_length', 9);

        if (strlen($digits) === $length) {
            $digits = $code.$digits;
        }

        if (strlen($digits) !== strlen($code) + $length || ! str_starts_with($digits, $code)) {
            return null;
        }

        return '+'.$digits;
    }

    public static function isValid(?string $input): bool
    {
        return self::normalize($input) !== null;
    }

    /** +998 90 123 45 67 */
    public static function format(string $e164): string
    {
        $d = ltrim($e164, '+');

        return sprintf('+%s %s %s %s %s', substr($d, 0, 3), substr($d, 3, 2), substr($d, 5, 3), substr($d, 8, 2), substr($d, 10, 2));
    }

    /** +998 90 *** ** 67 — ekranda ko‘rsatish uchun. */
    public static function mask(string $e164): string
    {
        $d = ltrim($e164, '+');

        return sprintf('+%s %s *** ** %s', substr($d, 0, 3), substr($d, 3, 2), substr($d, 10, 2));
    }
}
