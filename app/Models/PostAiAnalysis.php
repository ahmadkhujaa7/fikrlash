<?php

namespace App\Models;

use App\Services\Ai\AiConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostAiAnalysis extends Model
{
    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'raw_response' => 'array',
            'quality_score' => 'integer',
            'toxicity_score' => 'integer',
            'spam_score' => 'integer',
            'educational_score' => 'integer',
            'engagement_score' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class)->withTrashed();
    }

    /** Taxminiy narx (USD) — admin paneldagi (yoki config/ai.php dagi) narxlar asosida. */
    public function estimatedCost(): float
    {
        $prices = AiConfig::providerConfig((string) $this->provider);

        return ($this->input_tokens * ($prices['price_input'] ?? 0) + $this->output_tokens * ($prices['price_output'] ?? 0)) / 1_000_000;
    }
}
