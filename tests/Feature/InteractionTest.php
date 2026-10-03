<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Tests\TestCase;

class InteractionTest extends TestCase
{
    public function test_like_is_idempotent_and_counts_are_consistent(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();

        $this->actingAs($user)->postJson("/api/v1/posts/{$post->id}/like")->assertOk()->assertJsonPath('data.likes_count', 1);
        $this->actingAs($user)->postJson("/api/v1/posts/{$post->id}/like")->assertOk()->assertJsonPath('data.likes_count', 1);
        $this->assertSame(1, $post->fresh()->likes_count);
        $this->assertDatabaseCount('post_likes', 1);

        $this->actingAs($user)->deleteJson("/api/v1/posts/{$post->id}/like")->assertOk()->assertJsonPath('data.liked', false);
        $this->actingAs($user)->deleteJson("/api/v1/posts/{$post->id}/like")->assertOk();
        $this->assertSame(0, $post->fresh()->likes_count);
    }

    public function test_like_notifies_author_once_and_never_self(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->for($author)->create();
        $fan = User::factory()->create();

        $this->actingAs($fan)->postJson("/api/v1/posts/{$post->id}/like");
        $this->actingAs($fan)->deleteJson("/api/v1/posts/{$post->id}/like");
        $this->actingAs($fan)->postJson("/api/v1/posts/{$post->id}/like");
        $this->actingAs($author)->postJson("/api/v1/posts/{$post->id}/like");

        $this->assertSame(1, $author->notifications()->where('type', 'post_liked')->count());
    }

    public function test_save_and_saved_page(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['content' => 'Saqlanadigan fikr']);

        $this->actingAs($user)->postJson("/api/v1/posts/{$post->id}/save")->assertOk()->assertJsonPath('data.saved', true);
        $this->assertSame(1, $post->fresh()->saves_count);
        $this->actingAs($user)->get('/saved')->assertSee('Saqlanadigan fikr');

        $this->actingAs($user)->deleteJson("/api/v1/posts/{$post->id}/save");
        $this->assertSame(0, $post->fresh()->saves_count);
    }

    public function test_follow_unfollow_counters_and_rules(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create(['username' => 'bobur']);

        $this->actingAs($a)->postJson('/api/v1/users/bobur/follow')->assertOk()->assertJsonPath('data.followers_count', 1);
        $this->actingAs($a)->postJson("/api/v1/users/{$b->id}/follow")->assertOk();
        $this->assertSame(1, $b->fresh()->followers_count);
        $this->assertSame(1, $a->fresh()->following_count);
        $this->assertDatabaseHas('notifications', ['user_id' => $b->id, 'type' => 'followed', 'actor_id' => $a->id]);

        $this->actingAs($a)->postJson("/api/v1/users/{$a->id}/follow")->assertForbidden();

        $this->actingAs($a)->deleteJson('/api/v1/users/bobur/follow')->assertOk()->assertJsonPath('data.following', false);
        $this->assertSame(0, $b->fresh()->followers_count);
    }

    public function test_cannot_like_invisible_post(): void
    {
        $post = Post::factory()->followersOnly()->create();
        $this->actingAs(User::factory()->create())->postJson("/api/v1/posts/{$post->id}/like")->assertForbidden();
    }

    public function test_guest_interactions_require_auth(): void
    {
        $post = Post::factory()->create();
        $this->postJson("/api/v1/posts/{$post->id}/like")->assertUnauthorized()->assertJsonPath('success', false);
    }
}
