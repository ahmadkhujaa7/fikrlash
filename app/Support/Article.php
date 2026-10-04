<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Maqola bloklari bilan ishlash (sof funksiyalar).
 *
 * Blok turlari:
 *   p     — paragraf        {type, text}
 *   h     — kichik sarlavha {type, text}
 *   quote — iqtibos         {type, text}
 *   list  — ro‘yxat         {type, ordered, items[]}
 *   image — rasm            {type, path, caption, w, h}
 *   hr    — ajratgich       {type}
 *
 * Matnda **qalin** va *kursiv* belgilari ishlatiladi; HTML hech qachon saqlanmaydi.
 */
final class Article
{
    public const TYPES = ['p', 'h', 'quote', 'list', 'image', 'hr'];

    /** Sarlavha va bloklardan oddiy matn — qidiruv, AI tahlili, #teg va @eslatmalar shu orqali ishlaydi. */
    public static function plainText(string $title, array $blocks): string
    {
        $parts = [trim($title)];

        foreach ($blocks as $block) {
            $parts[] = match ($block['type'] ?? null) {
                'p', 'h', 'quote' => self::stripMarks((string) $block['text']),
                'list' => implode("\n", array_map(fn ($item) => self::stripMarks((string) $item), $block['items'] ?? [])),
                'image' => trim((string) ($block['caption'] ?? '')),
                default => '',
            };
        }

        return implode("\n\n", array_filter($parts, fn ($part) => trim($part) !== ''));
    }

    /** Maqola tanasi (sarlavhasiz) — lenta kartochkasidagi qisqa mazmun uchun. */
    public static function bodyText(array $blocks): string
    {
        return self::plainText('', array_values(array_filter($blocks, fn ($b) => in_array($b['type'] ?? null, ['p', 'quote', 'list'], true))));
    }

    public static function excerpt(array $blocks, int $length = 220): string
    {
        return Str::limit(preg_replace('/\s+/u', ' ', self::bodyText($blocks)) ?? '', $length);
    }

    /** @return list<string> Maqoladagi rasmlar (yo‘llari, takrorlanmas). */
    public static function images(array $blocks): array
    {
        return array_values(array_unique(array_map(
            fn ($b) => (string) $b['path'],
            array_filter($blocks, fn ($b) => ($b['type'] ?? null) === 'image' && ! empty($b['path'])),
        )));
    }

    /** Muqova — birinchi rasm (lentada kartochka tepasida ko‘rinadi). */
    public static function cover(array $blocks): ?string
    {
        return self::images($blocks)[0] ?? null;
    }

    public static function readMinutes(string $text): int
    {
        return max(1, (int) round(count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []) / 200));
    }

    /** **qalin** va *kursiv* belgilarini olib tashlaydi (matn o‘zi qoladi). */
    public static function stripMarks(string $text): string
    {
        $text = preg_replace(self::BOLD, '$1', $text) ?? $text;

        return preg_replace(self::ITALIC, '$1', $text) ?? $text;
    }

    public const BOLD = '/\*\*(?=\S)(.+?)(?<=\S)\*\*/u';

    public const ITALIC = '/(?<![*\p{L}\p{N}])\*(?=[^\s*])([^*\n]*?[^\s*])\*(?![*\p{L}\p{N}])/u';
}
