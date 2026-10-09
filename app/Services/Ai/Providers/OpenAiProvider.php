<?php

namespace App\Services\Ai\Providers;

use App\Contracts\AiProvider;
use App\Exceptions\AiException;
use App\Services\Ai\AiAnalysisResult;
use App\Services\Ai\AnalysisPrompt;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class OpenAiProvider implements AiProvider
{
    public function __construct(private array $config) {}

    public function name(): string
    {
        return 'openai';
    }

    public function analyzePost(string $content, array $categorySlugs): AiAnalysisResult
    {
        if (empty($this->config['api_key'])) {
            throw new AiException('OpenAI API kaliti sozlanmagan (Admin → AI → AI sozlamalari yoki .env OPENAI_API_KEY).', permanent: true);
        }

        $system = AnalysisPrompt::system($categorySlugs)
            ."\nRespond with a single JSON object with keys: ".implode(', ', AnalysisPrompt::jsonSchema()['required']).'.';

        try {
            $response = Http::baseUrl($this->config['base_url'])
                ->timeout(config('ai.timeout'))
                ->withToken($this->config['api_key'])
                ->post('/chat/completions', [
                    'model' => $this->config['model'],
                    'max_tokens' => config('ai.max_output_tokens'),
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => AnalysisPrompt::userMessage($content)],
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw new AiException('OpenAI API bilan aloqa yo‘q: '.$e->getMessage());
        }

        if ($response->failed()) {
            throw new AiException('OpenAI API xatosi: HTTP '.$response->status(), permanent: in_array($response->status(), [400, 401, 403, 404], true));
        }

        $data = json_decode((string) $response->json('choices.0.message.content'), true);
        if (! is_array($data)) {
            throw new AiException('OpenAI javobi JSON emas.');
        }

        return AiAnalysisResult::fromArray(
            $data,
            $categorySlugs,
            (string) $response->json('model', $this->config['model']),
            (int) $response->json('usage.prompt_tokens', 0),
            (int) $response->json('usage.completion_tokens', 0),
        );
    }
}
