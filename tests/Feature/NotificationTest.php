<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    public function test_mention_creates_notification(): void
    {
        $author = User::factory()->create();
        $mentioned = User::factory()->create(['username' => 'sevara']);

        $this->actingAs($author)->post('/posts', ['content' => 'Fikringiz qanday, @Sevara?']);

        $this->assertDatabaseHas('notifications', ['user_id' => $mentioned->id, 'type' => 'mentioned', 'actor_id' => $author->id]);
    }

    public function test_page_lists_and_marks_read(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create();
        $this->actingAs(User::factory()->create(['name' => 'Bobur Aliyev']))->postJson("/api/v1/posts/{$post->id}/like");

        $this->actingAs($user)->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.unread', 1);
        $this->actingAs($user)->get('/notifications')->assertOk()->assertSee('Bobur Aliyev')->assertSee('fikringizni yoqtirdi');
        $this->assertSame(0, $user->notifications()->unread()->count());
    }

    public function test_api_notifications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($other)->postJson("/api/v1/users/{$user->id}/follow");

        $this->actingAs($user)->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.type', 'followed')
            ->assertJsonPath('data.0.read', false)
            ->assertJsonPath('meta.unread', 1);

        $id = $user->notifications()->first()->id;
        $this->actingAs($other)->postJson("/api/v1/notifications/{$id}/read")->assertNotFound();
        $this->actingAs($user)->postJson("/api/v1/notifications/{$id}/read")->assertOk();
    }
}
