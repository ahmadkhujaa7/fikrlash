<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Exceptions\AiException;
use App\Jobs\AnalyzePostJob;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostAiAnalysis;
use App\Models\User;
use App\Services\Ai\Providers\ClaudeProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAnalysisTest extends TestCase
{
    public function test_post_is_analyzed_in_background_and_results_stored(): void
    {
        $this->seedCategories();
        $user = User::factory()->create();

        $this->actingAs($user)->post('/posts', ['content' => str_repeat('Laravel va PHP da backend dasturlash haqida uzun va foydali fikrlar. Kod yozish sanʼati. ', 3)]);

        $post = Post::query()->firstOrFail();
        $analysis = PostAiAnalysis::query()->where('post_id', $post->id)->firstOrFail();

        $this->assertSame('completed', $analysis->status);
        $this->assertSame('fake', $analysis->provider);
        $this->assertSame($post->content_hash, $analysis->content_hash);
        $this->assertNotNull($post->ai_analyzed_at);
        $this->assertSame('dasturlash', $post->ai_category);
        // Kategoriya tanlanmagan edi — AI taklifi qo‘yildi.
        $this->assertSame(Category::query()->where('slug', 'dasturlash')->value('id'), $post->category_id);
    }

    public function test_identical_content_is_not_analyzed_twice(): void
    {
        $post = Post::factory()->create();
        AnalyzePostJob::dispatchSync($post->id);
        AnalyzePostJob::dispatchSync($post->id);
        $this->assertSame(1, $post->aiAnalyses()->count());

        // Boshqa postda xuddi shu matn — API chaqirilmaydi, kesh ishlatiladi.
        $copy = Post::factory()->create(['content' => $post->content]);
        AnalyzePostJob::dispatchSync($copy->id);
        $this->assertSame('cache', $copy->aiAnalyses()->first()->provider);
        $this->assertSame(0, $copy->aiAnalyses()->first()->input_tokens);
    }

    public function test_toxic_post_is_sent_to_review_not_punished(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/posts', ['content' => 'Sen ahmoq va tentak odamsan, iflos idiot']);

        $post = Post::query()->firstOrFail();
        $this->assertSame(PostStatus::PendingModeration, $post->status);
        $this->assertTrue($post->ai_flagged);
        $this->assertTrue($user->fresh()->canInteract());
        $this->get('/?tab=latest')->assertDontSee('iflos idiot');
    }

    public function test_spam_post_is_flagged(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/posts', ['content' => 'Chegirma! Aksiya! Tez boyish va pul ishlash — kanalga obuna bo‘ling, reklama promo skidka']);

        $this->assertSame(PostStatus::PendingModeration, Post::query()->first()->status);
    }

    public function test_post_creation_succeeds_when_ai_fails(): void
    {
        config(['ai.provider' => 'claude', 'ai.providers.claude.api_key' => 'test-key']);
        Http::fake(['api.anthropic.com/*' => Http::response(['error' => 'overloaded'], 529)]);

        $this->actingAs(User::factory()->create())->post('/posts', ['content' => 'AI ishlamasa ham post chiqadi'])->assertRedirect();

        $post = Post::query()->firstOrFail();
        $this->assertTrue($post->isPublished());
        $this->assertNull($post->ai_analyzed_at);
    }

    public function test_claude_provider_parses_tool_use_response(): void
    {
        Http::fake(['api.anthropic.com/v1/messages' => Http::response([
            'model' => 'claude-haiku-4-5',
            'content' => [['type' => 'tool_use', 'name' => 'record_post_analysis', 'input' => [
                'topic' => 'Ta’lim', 'category' => 'talim', 'sentiment' => 'positive', 'quality_score' => 77,
                'toxicity_score' => 1, 'spam_score' => 0, 'educational_score' => 80, 'engagement_score' => 65,
                'summary' => 'Ta’lim haqida fikr.', 'keywords' => ['talim'],
            ]]],
            'usage' => ['input_tokens' => 321, 'output_tokens' => 88],
        ])]);

        $result = (new ClaudeProvider(['api_key' => 'k', 'model' => 'claude-haiku-4-5', 'base_url' => 'https://api.anthropic.com/v1', 'version' => '2023-06-01']))
            ->analyzePost('Matn', ['talim']);

        $this->assertSame('talim', $result->category);
        $this->assertSame(321, $result->inputTokens);
        Http::assertSent(fn ($r) => $r->hasHeader('x-api-key', 'k')
            && $r['tool_choice']['name'] === 'record_post_analysis'
            && str_contains($r['messages'][0]['content'], '<post>'));
    }

    public function test_claude_invalid_key_is_permanent_failure(): void
    {
        Http::fake(['*' => Http::response([], 401)]);

        try {
            (new ClaudeProvider(['api_key' => 'bad', 'model' => 'm', 'base_url' => 'https://api.anthropic.com/v1', 'version' => '2023-06-01']))->analyzePost('x', []);
            $this->fail('AiException kutilgan edi');
        } catch (AiException $e) {
            $this->assertTrue($e->permanent);
        }
    }

    public function test_disabled_ai_does_nothing(): void
    {
        config(['ai.enabled' => false]);
        $post = Post::factory()->create();
        AnalyzePostJob::dispatchSync($post->id);

        $this->assertSame(0, PostAiAnalysis::query()->count());
    }
}
