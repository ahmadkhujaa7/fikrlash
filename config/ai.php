<?php

return [

    'enabled' => (bool) env('AI_ENABLED', true),

    /*
    | Provayderlar: "claude", "openai", "fake" (kalitsiz development/test),
    | "null" (AI o‘chirilgan).
    */
    'provider' => env('AI_PROVIDER', 'fake'),

    'queue' => env('AI_QUEUE', 'ai'),

    'max_input_chars' => 6000,
    'max_output_tokens' => 600,
    'timeout' => 30,
    'tries' => 3,

    // Xarajat nazorati.
    'requests_per_minute' => (int) env('AI_REQUESTS_PER_MINUTE', 30),
    'daily_request_limit' => (int) env('AI_DAILY_LIMIT', 5000),

    // Moderatsiya signallari (0-100). Avtomatik jazo yo‘q — faqat admin tekshiruviga yuboriladi.
    'thresholds' => [
        'toxicity_review' => 70,
        'spam_review' => 80,
        'auto_category_min_quality' => 30,
    ],

    'providers' => [
        'claude' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('AI_CLAUDE_MODEL', 'claude-haiku-4-5'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
            'version' => '2023-06-01',
            // 1M token uchun USD (statistika uchun taxminiy).
            'price_input' => (float) env('AI_CLAUDE_PRICE_INPUT', 1.0),
            'price_output' => (float) env('AI_CLAUDE_PRICE_OUTPUT', 5.0),
        ],
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('AI_OPENAI_MODEL', 'gpt-4o-mini'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'price_input' => (float) env('AI_OPENAI_PRICE_INPUT', 0.15),
            'price_output' => (float) env('AI_OPENAI_PRICE_OUTPUT', 0.6),
        ],
    ],
];
