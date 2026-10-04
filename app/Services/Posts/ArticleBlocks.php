<?php

namespace App\Services\Posts;

use App\Models\MediaUpload;
use App\Models\Post;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Brauzerdan kelgan maqola bloklarini tekshiradi va tozalaydi.
 *
 * Mijozga ishonilmaydi: noma'lum maydonlar tashlanadi, matn uzunligi cheklanadi,
 * rasm faqat muallifning o‘zi yuklagan (va boshqa postga biriktirilmagan) fayl bo‘lishi mumkin,
 * o‘lchamlari ham DB'dan olinadi.
 */
class ArticleBlocks
{
    /**
     * @return list<array<string, mixed>>
     *
     * @throws ValidationException
     */
    public function normalize(mixed $raw, User $author, ?Post $post = null): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (! is_array($raw) || ! array_is_list($raw)) {
            $this->fail('Maqola tuzilmasi noto‘g‘ri. Sahifani yangilab, qayta urinib ko‘ring.');
        }

        $cfg = config('fikrlash.articles');
        if (count($raw) > $cfg['max_blocks']) {
            $this->fail("Maqola juda ko‘p bo‘lakdan iborat (ko‘pi bilan {$cfg['max_blocks']} ta).");
        }

        $blocks = [];
        foreach ($raw as $block) {
            if (! is_array($block)) {
                continue;
            }

            $clean = match ($block['type'] ?? null) {
                'p', 'quote' => $this->textBlock($block, multiline: true),
                'h' => $this->textBlock($block, multiline: false, max: 200),
                'list' => $this->listBlock($block),
                'image' => ['type' => 'image', 'path' => (string) ($block['path'] ?? ''), 'caption' => mb_substr($this->text($block['caption'] ?? '', false), 0, $cfg['caption_max'])],
                'hr' => ['type' => 'hr'],
                default => $this->fail('Maqolada noma’lum bo‘lak bor.'),
            };

            // Bo‘sh paragraf, ketma-ket yoki boshidagi ajratgichlar tashlanadi.
            if ($clean === null || ($clean['type'] === 'hr' && ($blocks === [] || end($blocks)['type'] === 'hr'))) {
                continue;
            }
            $blocks[] = $clean;
        }

        while ($blocks !== [] && end($blocks)['type'] === 'hr') {
            array_pop($blocks);
        }

        if ($blocks === []) {
            $this->fail('Maqola matnini yozing.');
        }

        return $this->resolveImages($blocks, $author, $post);
    }

    private function textBlock(array $block, bool $multiline, ?int $max = null): ?array
    {
        $text = $this->text($block['text'] ?? '', $multiline);
        if ($text === '') {
            return null;
        }

        $max ??= (int) config('fikrlash.articles.block_max');
        if (mb_strlen($text) > $max) {
            $this->fail($block['type'] === 'h'
                ? "Kichik sarlavha {$max} belgidan oshmasligi kerak."
                : "Bitta paragraf {$max} belgidan oshmasligi kerak — uni ikkiga bo‘ling.");
        }

        return ['type' => $block['type'], 'text' => $text];
    }

    private function listBlock(array $block): ?array
    {
        $items = is_array($block['items'] ?? null) ? $block['items'] : explode("\n", (string) ($block['text'] ?? ''));
        $items = array_values(array_filter(
            array_map(fn ($item) => preg_replace('/^\s*(?:[-*•]|\d+[.)])\s+/u', '', $this->text($item, false)) ?? '', $items),
            fn ($item) => $item !== '',
        ));

        if ($items === []) {
            return null;
        }
        if (count($items) > 50 || max(array_map('mb_strlen', $items)) > 1000) {
            $this->fail('Ro‘yxat juda uzun: ko‘pi bilan 50 band, har biri 1000 belgigacha.');
        }

        return ['type' => 'list', 'ordered' => filter_var($block['ordered'] ?? false, FILTER_VALIDATE_BOOL), 'items' => $items];
    }

    /** Rasmlar: faqat muallifning o‘z yuklamalari; o‘lcham DB'dan. */
    private function resolveImages(array $blocks, User $author, ?Post $post): array
    {
        $paths = array_values(array_unique(array_column(array_filter($blocks, fn ($b) => $b['type'] === 'image'), 'path')));
        if ($paths === []) {
            return $blocks;
        }

        $max = (int) config('fikrlash.articles.max_images');
        if (count($paths) > $max) {
            $this->fail("Maqolada ko‘pi bilan {$max} ta rasm bo‘lishi mumkin.");
        }

        $media = MediaUpload::query()
            ->where('user_id', $author->id)
            ->whereIn('path', $paths)
            ->where(fn ($q) => $q->whereNull('post_id')->when($post?->exists, fn ($q) => $q->orWhere('post_id', $post->id)))
            ->get()
            ->keyBy('path');

        foreach ($blocks as $i => $block) {
            if ($block['type'] !== 'image') {
                continue;
            }
            $upload = $media->get($block['path']);
            if (! $upload) {
                $this->fail('Rasmlardan biri topilmadi — uni o‘chirib, qaytadan yuklang.');
            }
            $blocks[$i] += ['w' => $upload->width, 'h' => $upload->height];
        }

        return $blocks;
    }

    private function text(mixed $value, bool $multiline): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", is_scalar($value) ? (string) $value : '');
        $text = preg_replace('/[\x{0}-\x{8}\x{B}\x{C}\x{E}-\x{1F}\x{7F}\x{200B}\x{FEFF}]/u', '', $text) ?? '';
        $text = $multiline
            ? preg_replace("/[ \t]*\n[ \t]*/", "\n", preg_replace("/\n{2,}/", "\n", $text) ?? '')
            : preg_replace('/\s*\n\s*/u', ' ', $text);

        return trim((string) $text);
    }

    /** @throws ValidationException */
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['blocks' => $message]);
    }
}
