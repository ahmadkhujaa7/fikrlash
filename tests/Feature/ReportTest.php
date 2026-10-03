<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Tests\TestCase;

class ReportTest extends TestCase
{
    public function test_user_reports_post_once(): void
    {
        $post = Post::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/reports', ['type' => 'post', 'id' => $post->id, 'reason' => 'spam'])->assertCreated();
        $this->actingAs($user)->postJson('/api/v1/reports', ['type' => 'post', 'id' => $post->id, 'reason' => 'spam'])->assertUnprocessable();
        $this->assertDatabaseCount('reports', 1);
    }

    public function test_cannot_report_own_content_or_invalid_reason(): void
    {
        $post = Post::factory()->create();

        $this->actingAs($post->user)->postJson('/api/v1/reports', ['type' => 'post', 'id' => $post->id, 'reason' => 'spam'])->assertUnprocessable();
        $this->actingAs(User::factory()->create())->postJson('/api/v1/reports', ['type' => 'post', 'id' => $post->id, 'reason' => 'yoqmadi'])->assertUnprocessable();
    }

    public function test_many_reports_send_post_to_review_without_punishing_author(): void
    {
        $post = Post::factory()->create();
        $reporters = User::factory()->count(config('fikrlash.moderation.auto_review_reports'))->create();

        foreach ($reporters as $reporter) {
            $this->actingAs($reporter)->postJson('/api/v1/reports', ['type' => 'post', 'id' => $post->id, 'reason' => 'abuse']);
        }

        $this->assertSame(PostStatus::PendingModeration, $post->fresh()->status);
        $this->assertTrue($post->user->fresh()->canInteract());
        $this->assertDatabaseHas('audit_logs', ['action' => 'post.sent_to_review', 'target_id' => $post->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $post->user_id, 'type' => 'post_moderated']);
    }

    public function test_report_comment_and_user(): void
    {
        $comment = Comment::factory()->create();
        $target = User::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/reports', ['type' => 'comment', 'id' => $comment->id, 'reason' => 'hate'])->assertCreated();
        $this->actingAs($user)->postJson('/api/v1/reports', ['type' => 'user', 'id' => $target->id, 'reason' => 'other', 'description' => 'Soxta profil'])->assertCreated();
    }
}
