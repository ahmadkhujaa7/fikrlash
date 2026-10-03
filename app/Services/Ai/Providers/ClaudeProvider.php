<?php

namespace App\Services\Ai\Providers;

use App\Contracts\AiProvider;
use App\Exceptions\AiException;
use App\Services\Ai\AiAnalysisResult;
use App\Services\Ai\AnalysisPrompt;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Anthropic Messages API. Tuzilgan javob majburiy tool_use orqali olinadi —
 * model faqat JSON sxemaga mos obyekt qaytaradi.
 */
class ClaudeProvider implements AiProvider
{
    private const TOOL = 'record_post_analysis';

    public function __construct(private array $config) {}

    public function name(): string
    {
        return 'claude';
    }

    public function analyzePost(string $content, array $categorySlugs): AiAnalysisResult
    {
        if (empty($this->config['api_key'])) {
            throw new AiException('ANTHROPIC_API_KEY sozlanmagan.', permanent: true);
        }

        try {
            $response = Http::baseUrl($this->config['base_url'])
                ->timeout(config('ai.timeout'))
                ->withHeaders([
                    'x-api-key' => $this->config['api_key'],
                    'anthropic-version' => $this->config['version'],
                ])
                ->post('/messages', [
                    'model' => $this->config['model'],
                    'max_tokens' => config('ai.max_output_tokens'),
                    'system' => AnalysisPrompt::system($categorySlugs),
                    'messages' => [['role' => 'user', 'content' => AnalysisPrompt::userMessage($content)]],
                    'tools' => [[
                        'name' => self::TOOL,
                        'description' => 'Record the structured analysis of the post.',
                        'input_schema' => AnalysisPrompt::jsonSchema(),
                    ]],
                    'tool_choice' => ['type' => 'tool', 'name' => self::TOOL],
                ]);
        } catch (ConnectionException $e) {
            throw new AiException('Claude API bilan aloqa yo‘q: '.$e->getMessage());
        }

        if ($response->failed()) {
            throw new AiException('Claude API xatosi: HTTP '.$response->status(), permanent: in_array($response->status(), [400, 401, 403, 404], true));
        }

        $input = collect($response->json('content', []))->firstWhere('type', 'tool_use')['input'] ?? null;
        if (! is_array($input)) {
            throw new AiException('Claude javobida tuzilgan natija yo‘q.');
        }

        return AiAnalysisResult::fromArray(
            $input,
            $categorySlugs,
            (string) $response->json('model', $this->config['model']),
            (int) $response->json('usage.input_tokens', 0),
            (int) $response->json('usage.output_tokens', 0),
        );
    }
}
