<?php

namespace App\Services\Ai;

use App\Exceptions\AiException;
use Illuminate\Support\Facades\Validator;

/**
 * Provayderdan kelgan javobni tekshirilgan, normallashtirilgan ko‘rinishi.
 * AI javobiga hech qachon ko‘r-ko‘rona ishonilmaydi: turi, oralig‘i, uzunligi tekshiriladi.
 */
final class AiAnalysisResult
{
    public const SENTIMENTS = ['positive', 'neutral', 'negative', 'mixed'];

    /**
     * @param  list<string>  $keywords
     */
    public function __construct(
        public readonly ?string $topic,
        public readonly ?string $category,
        public readonly string $sentiment,
        public readonly int $qualityScore,
        public readonly int $toxicityScore,
        public readonly int $spamScore,
        public readonly int $educationalScore,
        public readonly int $engagementScore,
        public readonly ?string $summary,
        public readonly array $keywords,
        public readonly string $model,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly array $raw = [],
    ) {}

    /**
     * @param  list<string>  $categorySlugs
     *
     * @throws AiException
     */
    public static function fromArray(array $data, array $categorySlugs, string $model, int $inputTokens = 0, int $outputTokens = 0): self
    {
        $score = ['required', 'numeric', 'between:0,100'];
        $validator = Validator::make($data, [
            'topic' => ['nullable', 'string', 'max:500'],
            'category' => ['nullable', 'string', 'max:100'],
            'sentiment' => ['required', 'string'],
            'quality_score' => $score,
            'toxicity_score' => $score,
            'spam_score' => $score,
            'educational_score' => $score,
            'engagement_score' => ['nullable', 'numeric', 'between:0,100'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'keywords' => ['nullable', 'array', 'max:20'],
            'keywords.*' => ['string', 'max:60'],
        ]);

        if ($validator->fails()) {
            throw new AiException('AI javobi formati noto‘g‘ri: '.implode('; ', $validator->errors()->all()));
        }

        $category = isset($data['category']) ? mb_strtolower(trim((string) $data['category'])) : null;
        $sentiment = mb_strtolower(trim((string) $data['sentiment']));

        return new self(
            topic: self::clip($data['topic'] ?? null, 120),
            category: in_array($category, $categorySlugs, true) ? $category : null,
            sentiment: in_array($sentiment, self::SENTIMENTS, true) ? $sentiment : 'neutral',
            qualityScore: (int) round($data['quality_score']),
            toxicityScore: (int) round($data['toxicity_score']),
            spamScore: (int) round($data['spam_score']),
            educationalScore: (int) round($data['educational_score']),
            engagementScore: (int) round($data['engagement_score'] ?? $data['quality_score']),
            summary: self::clip($data['summary'] ?? null, 500),
            keywords: array_values(array_slice(array_unique(array_map(fn ($k) => mb_strtolower(trim($k)), $data['keywords'] ?? [])), 0, 10)),
            model: $model,
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            raw: $data,
        );
    }

    private static function clip(?string $value, int $max): ?string
    {
        $value = $value !== null ? trim(strip_tags($value)) : null;

        return $value ? mb_substr($value, 0, $max) : null;
    }
}
