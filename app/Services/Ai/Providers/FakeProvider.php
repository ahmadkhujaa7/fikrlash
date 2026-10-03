<?php

namespace App\Services\Ai\Providers;

use App\Contracts\AiProvider;
use App\Services\Ai\AiAnalysisResult;
use App\Support\TextNormalizer;

/**
 * API kalitsiz development va testlar uchun deterministik "AI".
 * Oddiy kalit so‘z qoidalari bilan ishlaydi — haqiqiy model o‘rnini bosmaydi.
 */
class FakeProvider implements AiProvider
{
    private const SPAM = ['chegirma', 'aksiya', 'sotiladi', 'reklama', 'kanalga obuna', 'pul ishlash', 'tez boyish', 'promo', 'skidka'];

    private const TOXIC = ['ahmoq', 'tentak', 'jinni', 'iflos', 'yomon odam', 'nafratlanaman', 'idiot'];

    private const CATEGORY_HINTS = [
        'dasturlash' => ['kod', 'dastur', 'php', 'laravel', 'python', 'javascript', 'backend', 'frontend'],
        'texnologiya' => ['texnologiya', 'telefon', 'sun\'iy intellekt', 'ai', 'internet', 'gadjet'],
        'talim' => ['talim', 'maktab', 'universitet', 'oqish', 'dars', 'imtihon', 'ustoz'],
        'biznes' => ['biznes', 'startap', 'tadbirkor', 'savdo', 'marketing', 'investitsiya'],
        'kitoblar' => ['kitob', 'roman', 'muallif', 'oqidim', 'asar'],
        'falsafa' => ['falsafa', 'mano', 'hayot mazmuni', 'haqiqat', 'ong'],
        'ilm-fan' => ['ilm', 'fan', 'tadqiqot', 'fizika', 'kimyo', 'biologiya'],
        'shaxsiy-rivojlanish' => ['odat', 'motivatsiya', 'maqsad', 'intizom', 'rivojlanish'],
        'jamiyat' => ['jamiyat', 'odamlar', 'shahar', 'muammo', 'qonun'],
        'hayot' => ['hayot', 'oila', 'dost', 'baxt', 'kun'],
    ];

    public function name(): string
    {
        return 'fake';
    }

    public function analyzePost(string $content, array $categorySlugs): AiAnalysisResult
    {
        $text = TextNormalizer::forSearch($content);
        $length = mb_strlen($text);

        $spam = $this->hits($text, self::SPAM) * 30 + (substr_count($text, 'http') > 2 ? 40 : 0);
        $toxic = $this->hits($text, self::TOXIC) * 40;
        $quality = min(100, 20 + (int) ($length / 12) - (int) ($spam / 2) - (int) ($toxic / 2));

        $category = null;
        $best = 0;
        foreach (self::CATEGORY_HINTS as $slug => $words) {
            $hits = $this->hits($text, array_map([TextNormalizer::class, 'forSearch'], $words));
            if ($hits > $best && in_array($slug, $categorySlugs, true)) {
                [$best, $category] = [$hits, $slug];
            }
        }

        $words = array_count_values(array_filter(preg_split('/[^\p{L}\p{N}]+/u', $text) ?: [], fn ($w) => mb_strlen($w) > 4));
        arsort($words);

        return AiAnalysisResult::fromArray([
            'topic' => $category ? ucfirst(str_replace('-', ' ', $category)) : 'Umumiy fikr',
            'category' => $category,
            'sentiment' => $toxic > 0 ? 'negative' : 'neutral',
            'quality_score' => max(0, $quality),
            'toxicity_score' => min(100, $toxic),
            'spam_score' => min(100, $spam),
            'educational_score' => min(100, (int) ($length / 20)),
            'engagement_score' => max(0, min(100, $quality + 5)),
            'summary' => mb_strimwidth(preg_replace('/\s+/u', ' ', trim($content)), 0, 160, '…'),
            'keywords' => array_slice(array_keys($words), 0, 5),
        ], $categorySlugs, 'fake-rules-v1', (int) ceil($length / 4), 60);
    }

    private function hits(string $text, array $needles): int
    {
        return count(array_filter($needles, fn ($n) => str_contains($text, $n)));
    }
}
