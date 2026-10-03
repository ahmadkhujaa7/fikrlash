<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Tests\TestCase;

class ViewTrackingTest extends TestCase
{
    public function test_views_are_deduplicated_per_viewer(): void
    {
        $post = Post::factory()->create();
        $viewer = User::factory()->create();

        $this->actingAs($viewer)->get("/posts/{$post->id}");
        $this->actingAs($viewer)->get("/posts/{$post->id}");
        $this->actingAs($viewer)->postJson('/api/v1/views', ['post_ids' => [$post->id]])->assertJsonPath('data.counted', 0);

        $this->assertSame(1, $post->fresh()->views_count);
        $this->assertDatabaseHas('post_views', ['user_id' => $viewer->id, 'post_id' => $post->id]);

        $this->travel(31)->minutes();
        $this->actingAs($viewer)->get("/posts/{$post->id}");
        $this->assertSame(2, $post->fresh()->views_count);
    }

    public function test_author_views_are_not_counted(): void
    {
        $post = Post::factory()->create();
        $this->actingAs($post->user)->get("/posts/{$post->id}");

        $this->assertSame(0, $post->fresh()->views_count);
    }

    public function test_read_time_is_recorded_and_capped(): void
    {
        $post = Post::factory()->create();
        $viewer = User::factory()->create();
        $this->actingAs($viewer)->get("/posts/{$post->id}");

        $this->actingAs($viewer)->postJson("/api/v1/posts/{$post->id}/read", ['seconds' => 45])->assertOk();
        $this->actingAs($viewer)->postJson("/api/v1/posts/{$post->id}/read", ['seconds' => 3600])->assertOk();

        $this->assertDatabaseHas('post_views', ['user_id' => $viewer->id, 'post_id' => $post->id, 'read_seconds' => config('fikrlash.views.max_read_seconds')]);
    }
}
