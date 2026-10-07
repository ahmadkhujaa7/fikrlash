<?php

namespace App\Support;

/**
 * Foydalanuvchi matnini xavfsiz HTML ga aylantiradi.
 *
 * Qoida: xom matn DB'da saqlanadi. Chiqishda har bir bo‘lak alohida escape qilinadi,
 * faqat bizning o‘zimiz yaratgan <a> teglari qo‘shiladi. Xom HTML hech qachon chiqmaydi.
 */
final class ContentFormatter
{
    private const URL = '(?<url>https?:\/\/[^\s<>"]+)';

    /**
     * http(s)'siz yozilgan manzil: "sayt.uz", "www.misol.com/sahifa". Faqat ma'lum domen zonalari —
     * "Node.js" yoki "v2.0" havolaga aylanmaydi; email (info@sayt.uz) ham emas.
     */
    public const TLDS = 'uz|com|net|org|ru|io|me|info|biz|edu|gov|dev|app|ai|co|tv|kz|kg|tj|tm|az|tr|de|uk|us|eu|ua|by|xyz|online|site|store|shop|tech|pro|blog|news|club|academy|media|link|live|world|today|space|website|cloud';

    private const DOMAIN = '(?<![\p{L}\p{N}_@.\/:#-])(?<domain>(?i:(?:www\.)?(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+(?:'.self::TLDS.'))(?![\p{L}\p{N}_-])(?:\/[^\s<>"]*)?)';

    private const MENTION = '(?<![\p{L}\p{N}_@\/])@(?<mention>[A-Za-z0-9_]{3,30})';

    private const HASHTAG = '(?<![\p{L}\p{N}_&\/#])#(?<tag>[\p{L}\p{N}_‘’ʻʼ\']{2,50})';

    /**
     * @param  bool  $rich  Maqola uchun: **qalin** va *kursiv* ham ko‘rsatiladi (faqat oddiy matn bo‘laklarida).
     */
    public static function toHtml(string $text, bool $rich = false): string
    {
        $text = preg_replace("/\n{3,}/", "\n\n", str_replace(["\r\n", "\r"], "\n", trim($text))) ?? '';
        $pattern = '/'.self::URL.'|'.self::DOMAIN.'|'.self::MENTION.'|'.self::HASHTAG.'/u';

        $html = '';
        $offset = 0;
        preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL);

        $plain = fn (string $segment) => $rich ? self::emphasis(e($segment)) : e($segment);

        foreach ($matches as $match) {
            [$full, $position] = $match[0];
            $html .= $plain(substr($text, $offset, $position - $offset));
            $html .= self::renderToken($match, $full);
            $offset = $position + strlen($full);
        }

        $html .= $plain(substr($text, $offset));

        return nl2br($html, false);
    }

    /** Escape qilingan matnga qalin/kursiv teglari (yulduzchalar e() da o‘zgarmaydi). */
    private static function emphasis(string $escaped): string
    {
        $escaped = preg_replace(Article::BOLD, '<strong>$1</strong>', $escaped) ?? $escaped;

        return preg_replace(Article::ITALIC, '<em>$1</em>', $escaped) ?? $escaped;
    }

    private static function renderToken(array $match, string $full): string
    {
        if (($match['domain'][0] ?? null) !== null) {
            $domain = rtrim($full, '.,;:!?)»”');
            $tail = substr($full, strlen($domain));
            $label = mb_strimwidth(preg_replace('#^www\.#i', '', $domain) ?? $domain, 0, 48, '…');

            return '<a href="https://'.e($domain).'" class="link" target="_blank" rel="nofollow ugc noopener noreferrer">'.e($label).'</a>'.e($tail);
        }

        if (($match['url'][0] ?? null) !== null) {
            // Gap oxiridagi tinish belgilarini havoladan chiqaramiz.
            $url = rtrim($full, '.,;:!?)»”');
            $tail = substr($full, strlen($url));
            $label = mb_strimwidth(preg_replace('#^https?://(www\.)?#', '', $url), 0, 48, '…');

            return '<a href="'.e($url).'" class="link" target="_blank" rel="nofollow ugc noopener noreferrer">'.e($label).'</a>'.e($tail);
        }

        if (($match['mention'][0] ?? null) !== null) {
            $username = strtolower($match['mention'][0]);

            return '<a href="'.e(route('profile.show', $username)).'" class="mention">@'.e($match['mention'][0]).'</a>';
        }

        $slug = TextNormalizer::tagSlug($match['tag'][0]);
        if ($slug === '') {
            return e($full);
        }

        return '<a href="'.e(route('tags.show', $slug)).'" class="hashtag">#'.e($match['tag'][0]).'</a>';
    }

    /** @return list<string> Matndagi eslatilgan username'lar (kichik harfda, takrorlanmas). */
    public static function mentions(string $text, int $limit = 10): array
    {
        $text = preg_replace('/'.self::URL.'/u', ' ', $text) ?? '';
        preg_match_all('/'.self::MENTION.'/u', $text, $m);

        return array_slice(array_values(array_unique(array_map('strtolower', $m['mention']))), 0, $limit);
    }

    /** @return array<string, string> slug => ko‘rsatiladigan nom */
    public static function hashtags(string $text, int $limit = 10): array
    {
        $text = preg_replace('/'.self::URL.'/u', ' ', $text) ?? '';
        preg_match_all('/'.self::HASHTAG.'/u', $text, $m);

        $tags = [];
        foreach ($m['tag'] as $name) {
            $slug = TextNormalizer::tagSlug($name);
            if ($slug !== '' && ! isset($tags[$slug])) {
                $tags[$slug] = $name;
            }
        }

        return array_slice($tags, 0, $limit, true);
    }
}
