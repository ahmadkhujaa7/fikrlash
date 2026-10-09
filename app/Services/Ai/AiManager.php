<?php

namespace App\Services\Ai;

use App\Contracts\AiProvider;
use App\Services\Ai\Providers\ClaudeProvider;
use App\Services\Ai\Providers\FakeProvider;
use App\Services\Ai\Providers\OpenAiProvider;
use InvalidArgumentException;

/** Sozlamalar bo‘yicha AI provayderni tanlaydi (admin panel → .env). Yangi provayder = yangi klass + shu yerda bitta qator. */
class AiManager
{
    public function provider(?string $name = null): ?AiProvider
    {
        $name ??= AiConfig::provider();

        return match ($name) {
            'claude' => new ClaudeProvider(AiConfig::providerConfig('claude')),
            'openai' => new OpenAiProvider(AiConfig::providerConfig('openai')),
            'fake' => new FakeProvider,
            'null', null, '' => null,
            default => throw new InvalidArgumentException("Noma'lum AI provayder: {$name}"),
        };
    }
}
