<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Tests\TestCase;

class CommentTest extends TestCase
{
    public function test_comment_reply_and_notifications(): void
    {
        $author = User::factory()->create();
        $commenter = User::factory()->create(['username' => 'izohchi']);
        $replier = User::factory()->create();
        $post = Post::factory()->for($author)->create();

        $this->actingAs($commenter)->postJson("/posts/{$post->id}/comments", ['content' => 'Zo‘r fikr!'])
            ->assertCreated()->assertJsonPath('comments_count', 1);
        $comment = Comment::query()->firstOrFail();

        $this->actingAs($replier)->postJson("/posts/{$post->id}/comments", ['content' => '@izohchi qo‘shilaman', 'parent_id' => $comment->id])
            ->assertCreated()->assertJsonPath('parent_id', $comment->id);

        $this->assertSame(2, $post->fresh()->comments_count);
        $this->assertSame(1, $comment->fresh()->replies_count);
        $this->assertDatabaseHas('notifications', ['user_id' => $author->id, 'type' => 'post_commented', 'actor_id' => $commenter->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $commenter->id, 'type' => 'comment_replied', 'actor_id' => $replier->id]);
        // Javob berilgan odam eslatilgan bo‘lsa ham bitta xabar oladi.
        $this->assertSame(1, $commenter->notifications()->count());
    }

    public function test_reply_to_reply_is_attached_to_root(): void
    {
        $post = Post::factory()->create();
        $root = Comment::factory()->for($post)->create();
        $reply = Comment::factory()->for($post)->create(['parent_id' => $root->id]);

        $this->actingAs(User::factory()->create())->postJson("/posts/{$post->id}/comments", ['content' => 'chuqur javob', 'parent_id' => $reply->id])->assertCreated();

        $this->assertSame($root->id, Comment::query()->latest('id')->first()->parent_id);
        $this->assertSame($reply->user_id, Comment::query()->latest('id')->first()->reply_to_user_id);
    }

    public function test_deleting_root_comment_removes_replies_and_updates_counter(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create();
        $this->actingAs($user)->postJson("/posts/{$post->id}/comments", ['content' => 'asosiy']);
        $root = Comment::query()->first();
        $this->actingAs(User::factory()->create())->postJson("/posts/{$post->id}/comments", ['content' => 'javob', 'parent_id' => $root->id]);

        $this->actingAs($user)->deleteJson("/comments/{$root->id}")->assertOk();

        $this->assertSame(0, $post->fresh()->comments_count);
        $this->assertSame(0, Comment::query()->count());
    }

    public function test_permissions(): void
    {
        $post = Post::factory()->create();
        $comment = Comment::factory()->for($post)->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->deleteJson("/comments/{$comment->id}")->assertForbidden();
        // Post muallifi o‘z postidagi izohni o‘chira oladi.
        $this->actingAs($post->user)->deleteJson("/comments/{$comment->id}")->assertOk();
    }

    public function test_comments_list_renders_and_escapes(): void
    {
        $post = Post::factory()->create();
        Comment::factory()->for($post)->create(['content' => '<b>qalin</b> izoh']);

        $this->get("/posts/{$post->id}/comments")->assertOk()->assertSee('&lt;b&gt;qalin&lt;/b&gt;', false);
    }

    public function test_comment_max_length(): void
    {
        $post = Post::factory()->create();
        $this->actingAs(User::factory()->create())->postJson("/posts/{$post->id}/comments", ['content' => str_repeat('a', 2001)])->assertUnprocessable();
    }
}
