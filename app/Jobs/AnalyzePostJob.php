<?php

namespace App\Jobs;

use App\Exceptions\AiException;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostAiAnalysis;
use App\Models\Setting;
use App\Services\Ai\AiAnalysisResult;
use App\Services\Ai\AiManager;
use App\Services\Moderation\ModerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Postni AI orqali fon rejimida tahlil qiladi.
 *
 * Post yaratish AI'ga bog‘liq emas: API ishlamasa ham post chop etiladi.
 * Xarajat nazorati: bitta post uchun bitta vaqtda bitta job (unique), bir xil matn qayta tahlil qilinmaydi
 * (content hash), daqiqalik va kunlik limit, cheklangan qayta urinishlar.
 */
class AnalyzePostJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $maxExceptions;

    public int $timeout = 90;

    public int $uniqueFor = 900;

    public function __construct(public int $postId)
    {
        $this->onQueue(config('ai.queue'));
        $this->maxExceptions = (int) config('ai.tries');
    }

    /**
     * Xavfsiz navbatga qo‘yish: AI/queue xatosi chaqiruvchi jarayonni (post yaratish) buzmaydi.
     * (sync queue'da job darhol bajariladi — shuning uchun istisno shu yerda ushlanadi.)
     */
    public static function dispatchSafely(int $postId): void
    {
        rescue(function () use ($postId) {
            static::dispatch($postId);
        }, report: true);
    }

    public function uniqueId(): string
    {
        return (string) $this->postId;
    }

    /** Rate limit tufayli kechiktirilgan job ham 6 soat ichida bajariladi. */
    public function retryUntil(): Carbon
    {
        return now()->addHours(6);
    }

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function middleware(): array
    {
        return [new RateLimited('ai')];
    }

    public function handle(AiManager $ai, ModerationService $moderation): void
    {
        if (! config('ai.enabled') || ! Setting::read('ai_enabled')) {
            return;
        }

        $post = Post::query()->find($this->postId);
        $provider = $ai->provider();
        if (! $post || ! $post->isPublished() || ! $provider) {
            return;
        }

        if ($this->alreadyAnalyzed($post)) {
            return;
        }

        // Bir xil matn boshqa postda tahlil qilingan bo‘lsa (copy-paste) — API chaqirilmaydi.
        $cached = PostAiAnalysis::query()
            ->where('content_hash', $post->content_hash)->where('status', PostAiAnalysis::STATUS_COMPLETED)
            ->latest('id')->first();

        if ($cached) {
            $analysis = $this->store($post, $cached->replicate(['post_id', 'created_at', 'updated_at'])->fill([
                'input_tokens' => 0, 'output_tokens' => 0, 'latency_ms' => 0, 'provider' => 'cache',
            ])->toArray());
            $moderation->evaluateAiAnalysis($post, $analysis);

            return;
        }

        if (RateLimiter::tooManyAttempts('ai:daily', (int) config('ai.daily_request_limit'))) {
            Log::channel('ai')->warning('AI kunlik limitga yetdi — tahlil keyinga qoldirildi', ['post_id' => $post->id]);

            return; // scheduler "ai:retry-pending" keyinroq qayta yuboradi
        }
        RateLimiter::hit('ai:daily', 86_400);

        $categories = Category::cachedActive()->pluck('slug')->all();
        $started = hrtime(true);

        try {
            $result = $provider->analyzePost($post->content, $categories);
        } catch (AiException $e) {
            Log::channel('ai')->warning('AI tahlil xatosi', ['post_id' => $post->id, 'provider' => $provider->name(), 'error' => $e->getMessage()]);
            if ($e->permanent) {
                $this->fail($e);

                return;
            }
            throw $e;
        }

        $analysis = $this->store($post, $this->attributes($result, $provider->name(), $post->content_hash, $started));
        $this->applyToPost($post, $result);
        $moderation->evaluateAiAnalysis($post, $analysis);
    }

    public function failed(?Throwable $e): void
    {
        PostAiAnalysis::query()->create([
            'post_id' => $this->postId,
            'status' => PostAiAnalysis::STATUS_FAILED,
            'provider' => (string) config('ai.provider'),
            'error' => mb_substr((string) $e?->getMessage(), 0, 500),
        ]);

        Log::channel('ai')->error('AI tahlil butunlay muvaffaqiyatsiz', ['post_id' => $this->postId, 'error' => $e?->getMessage()]);
    }

    private function alreadyAnalyzed(Post $post): bool
    {
        return $post->aiAnalyses()
            ->where('status', PostAiAnalysis::STATUS_COMPLETED)
            ->where('content_hash', $post->content_hash)
            ->exists();
    }

    private function attributes(AiAnalysisResult $r, string $provider, ?string $hash, int $started): array
    {
        return [
            'status' => PostAiAnalysis::STATUS_COMPLETED,
            'provider' => $provider,
            'model' => $r->model,
            'content_hash' => $hash,
            'topic' => $r->topic,
            'category' => $r->category,
            'sentiment' => $r->sentiment,
            'quality_score' => $r->qualityScore,
            'toxicity_score' => $r->toxicityScore,
            'spam_score' => $r->spamScore,
            'educational_score' => $r->educationalScore,
            'engagement_score' => $r->engagementScore,
            'summary' => $r->summary,
            'keywords' => $r->keywords,
            'raw_response' => $r->raw,
            'input_tokens' => $r->inputTokens,
            'output_tokens' => $r->outputTokens,
            'latency_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
        ];
    }

    private function store(Post $post, array $attributes): PostAiAnalysis
    {
        return DB::transaction(function () use ($post, $attributes) {
            $analysis = $post->aiAnalyses()->create($attributes);

            if ($attributes['provider'] === 'cache') {
                $this->applyToPost($post, null, $analysis);
            }

            return $analysis;
        });
    }

    private function applyToPost(Post $post, ?AiAnalysisResult $r, ?PostAiAnalysis $a = null): void
    {
        $quality = $r?->qualityScore ?? $a?->quality_score;
        $category = $r?->category ?? $a?->category;

        $post->forceFill([
            'ai_score' => $quality,
            'ai_category' => $category,
            'ai_sentiment' => $r?->sentiment ?? $a?->sentiment,
            'ai_topic' => $r?->topic ?? $a?->topic,
            'ai_summary' => $r?->summary ?? $a?->summary,
            'ai_flagged' => ($r?->toxicityScore ?? $a?->toxicity_score ?? 0) >= config('ai.thresholds.toxicity_review')
                || ($r?->spamScore ?? $a?->spam_score ?? 0) >= config('ai.thresholds.spam_review'),
            'ai_analyzed_at' => now(),
        ]);

        // Foydalanuvchi kategoriya tanlamagan bo‘lsa — AI taklifi qo‘yiladi.
        if (! $post->category_id && $category && $quality >= config('ai.thresholds.auto_category_min_quality')) {
            $post->category_id = Category::cachedActive()->firstWhere('slug', $category)?->id;
        }

        $post->saveQuietly();
    }
}
