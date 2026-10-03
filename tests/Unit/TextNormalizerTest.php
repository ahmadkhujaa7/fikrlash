<?php

namespace Tests\Unit;

use App\Support\TextNormalizer;
use PHPUnit\Framework\TestCase;

class TextNormalizerTest extends TestCase
{
    public function test_unifies_uzbek_apostrophe_variants(): void
    {
        $variants = ['O‘qish', 'Oʻqish', "O'qish", 'O`qish', 'O’qish'];

        foreach ($variants as $variant) {
            $this->assertSame('oqish', TextNormalizer::forSearch($variant));
        }
    }

    public function test_tag_slug_strips_hash_and_symbols(): void
    {
        $this->assertSame('talim', TextNormalizer::tagSlug('#Ta’lim'));
        $this->assertSame('ai_2026', TextNormalizer::tagSlug('#AI_2026!'));
    }
}
