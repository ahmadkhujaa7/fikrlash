<?php

namespace Tests\Unit;

use App\Exceptions\AiException;
use App\Services\Ai\AiAnalysisResult;
use Tests\TestCase;

class AiAnalysisResultTest extends TestCase
{
    private function valid(array $overrides = []): array
    {
        return array_merge([
            'topic' => 'Dasturlash', 'category' => 'Dasturlash', 'sentiment' => 'Positive',
            'quality_score' => 81.6, 'toxicity_score' => 2, 'spam_score' => 0, 'educational_score' => 70, 'engagement_score' => 60,
            'summary' => '<b>Laravel</b> haqida', 'keywords' => ['Laravel', 'laravel', 'PHP'],
        ], $overrides);
    }

    public function test_normalizes_valid_response(): void
    {
        $r = AiAnalysisResult::fromArray($this->valid(), ['dasturlash'], 'model-x');

        $this->assertSame('dasturlash', $r->category);
        $this->assertSame('positive', $r->sentiment);
        $this->assertSame(82, $r->qualityScore);
        $this->assertSame('Laravel haqida', $r->summary);
        $this->assertSame(['laravel', 'php'], $r->keywords);
    }

    public function test_unknown_category_and_sentiment_are_neutralized(): void
    {
        $r = AiAnalysisResult::fromArray($this->valid(['category' => 'siyosat', 'sentiment' => 'angry']), ['dasturlash'], 'm');

        $this->assertNull($r->category);
        $this->assertSame('neutral', $r->sentiment);
    }

    public function test_out_of_range_scores_are_rejected(): void
    {
        $this->expectException(AiException::class);
        AiAnalysisResult::fromArray($this->valid(['toxicity_score' => 150]), ['dasturlash'], 'm');
    }

    public function test_missing_fields_are_rejected(): void
    {
        $this->expectException(AiException::class);
        AiAnalysisResult::fromArray(['topic' => 'x'], [], 'm');
    }
}
