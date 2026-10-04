<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostLike;
use App\Models\User;
use Tests\TestCase;

class SystemTest extends TestCase
{
    public function test_security_headers_are_present(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("'nonce-", $response->headers->get('Content-Security-Policy'));
    }

    public function test_reconcile_counters_fixes_drift(): void
    {
        $post = Post::factory()->create(['likes_count' => 99, 'comments_count' => 5]);
        PostLike::query()->create(['post_id' => $post->id, 'user_id' => User::factory()->create()->id]);

        $this->artisan('fikrlash:reconcile-counters')->assertSuccessful();

        $this->assertSame(1, $post->fresh()->likes_count);
        $this->assertSame(0, $post->fresh()->comments_count);
    }

    public function test_scheduled_commands_run(): void
    {
        Post::factory()->count(3)->create();

        $this->artisan('posts:refresh-scores')->assertSuccessful();
        $this->artisan('fikrlash:prune')->assertSuccessful();
        $this->artisan('views:flush')->assertSuccessful();
        $this->artisan('interests:decay')->assertSuccessful();
        $this->artisan('users:lift-suspensions')->assertSuccessful();
        $this->artisan('ai:retry-pending')->assertSuccessful();

        $this->assertGreaterThan(0, Post::query()->first()->score);
    }

    public function test_public_pages_render(): void
    {
        $this->seedCategories();
        Post::factory()->create(['content' => 'Teg bilan #sinov']);

        foreach (['/about', '/terms', '/privacy', '/t/sinov', '/search', '/login', '/register', '/password/forgot', '/up'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/c/mavjud-emas')->assertNotFound();
    }
}
