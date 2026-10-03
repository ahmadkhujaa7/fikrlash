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

    private const MENTION = '(?<![\p{L}\p{N}_@\/])@(?<mention>[A-Za-z0-9_]{3,30})';

    private const HASHTAG = '(?<![\p{L}\p{N}_&\/#])#(?<tag>[\p{L}\p{N}_‘’ʻʼ\']{2,50})';

    public static function toHtml(string $text): string
    {
        $text = preg_replace("/\n{3,}/", "\n\n", str_replace(["\r\n", "\r"], "\n", trim($text))) ?? '';
        $pattern = '/'.self::URL.'|'.self::MENTION.'|'.self::HASHTAG.'/u';

        $html = '';
        $offset = 0;
        preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL);

        foreach ($matches as $match) {
            [$full, $position] = $match[0];
            $html .= e(substr($text, $offset, $position - $offset));
            $html .= self::renderToken($match, $full);
            $offset = $position + strlen($full);
        }

        $html .= e(substr($text, $offset));

        return nl2br($html, false);
    }

    private static function renderToken(array $match, string $full): string
    {
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
