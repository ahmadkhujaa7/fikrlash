<?php

namespace App\Services\Ai;

use App\Contracts\AiProvider;
use App\Services\Ai\Providers\ClaudeProvider;
use App\Services\Ai\Providers\FakeProvider;
use App\Services\Ai\Providers\OpenAiProvider;
use InvalidArgumentException;

/** Config bo‘yicha AI provayderni tanlaydi. Yangi provayder = yangi klass + shu yerda bitta qator. */
class AiManager
{
    public function provider(?string $name = null): ?AiProvider
    {
        $name ??= config('ai.provider');

        return match ($name) {
            'claude' => new ClaudeProvider(config('ai.providers.claude')),
            'openai' => new OpenAiProvider(config('ai.providers.openai')),
            'fake' => new FakeProvider,
            'null', null, '' => null,
            default => throw new InvalidArgumentException("Noma'lum AI provayder: {$name}"),
        };
    }
}
