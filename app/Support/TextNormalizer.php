<?php

namespace App\Support;

/**
 * O‘zbek lotin matnini qidiruv va taqqoslash uchun normallashtirish.
 *
 * Muammo: "o‘qish", "oʻqish", "o'qish", "o`qish" — bir so‘z, lekin turli belgilar.
 * MySQL FULLTEXT ‘ (U+2018) ni so‘z ajratuvchi deb hisoblaydi va "o‘qish" ni "o" + "qish" ga bo‘ladi.
 * Shuning uchun qidiruv matnida barcha apostrof variantlari olib tashlanadi (indeksda ham, so‘rovda ham).
 */
final class TextNormalizer
{
    public const APOSTROPHES = ["'", '`', '´', '‘', '’', 'ʻ', 'ʼ', 'ʹ', '′'];

    public static function forSearch(string $text): string
    {
        $text = mb_strtolower($text);
        $text = str_replace(self::APOSTROPHES, '', $text);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /** Dublikat kontentni aniqlash uchun (AI cache, spam). */
    public static function forHash(string $text): string
    {
        return self::forSearch($text);
    }

    /** Hashtag/tag nomidan slug: kichik harf, apostrofsiz, faqat harf/raqam/_ . */
    public static function tagSlug(string $name): string
    {
        $slug = self::forSearch(ltrim($name, '#'));

        return preg_replace('/[^\p{L}\p{N}_]/u', '', $slug) ?? '';
    }
}
