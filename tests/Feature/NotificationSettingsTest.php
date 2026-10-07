<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Post;
use App\Models\User;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class NotificationSettingsTest extends TestCase
{
    public function test_user_can_mute_notification_types(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $post = Post::factory()->for($user)->create();

        $this->actingAs($user)->get('/settings/notifications')->assertOk()->assertSee('Fikrlarimni yoqtirishganda');
        $this->actingAs($user)->get('/settings/permissions')->assertOk()->assertSee('Mikrofon')->assertSee('Joylashuv');

        // Faqat obuna va eslatish yoqiq qoladi.
        $this->actingAs($user)->put('/settings/notifications', ['enabled' => ['followed', 'mentioned']])->assertRedirect();
        $this->assertSame(['post_liked', 'post_commented', 'comment_replied'], $user->fresh()->notification_settings['muted']);

        $this->actingAs($other)->postJson("/api/v1/posts/{$post->id}/like")->assertOk();
        $this->actingAs($other)->postJson("/api/v1/users/{$user->id}/follow")->assertOk();
        $this->assertSame(['followed'], $user->notifications()->pluck('type')->map->value->all());

        // Noto‘g‘ri tur qabul qilinmaydi; hammasi o‘chirilsa — hammasi "muted".
        $this->actingAs($user)->put('/settings/notifications', ['enabled' => ['system']])->assertSessionHasErrors('enabled.0');
        $this->actingAs($user)->put('/settings/notifications', [])->assertRedirect();
        $this->assertCount(5, $user->fresh()->notification_settings['muted']);
    }

    public function test_badges_return_latest_items_for_browser_notifications(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        [$me, $aziz] = User::factory()->count(2)->create();
        $post = Post::factory()->for($me)->create();

        $this->actingAs($me)->getJson('/badges?latest=1')->assertOk()
            ->assertJsonPath('notifications', 0)->assertJsonPath('latest.notification', null)->assertJsonPath('latest.message', null);

        $this->actingAs($aziz)->postJson("/api/v1/posts/{$post->id}/like");
        $this->actingAs($aziz)->get("/messages/with/{$me->username}");
        $conversation = Conversation::query()->where('pair_key', Conversation::pairKey($me->id, $aziz->id))->firstOrFail();
        $this->actingAs($aziz)->postJson("/messages/{$conversation->id}", ['body' => 'Salom, qalaysan?'])->assertCreated();

        $this->actingAs($me)->getJson('/badges?latest=1')->assertOk()
            ->assertJsonPath('notifications', 1)
            ->assertJsonPath('messages', 1)
            ->assertJsonPath('latest.notification.title', $aziz->name)
            ->assertJsonPath('latest.notification.body', 'fikringizni yoqtirdi')
            ->assertJsonPath('latest.message.title', $aziz->name)
            ->assertJsonPath('latest.message.body', 'Salom, qalaysan?')
            ->assertJsonPath('latest.message.url', route('messages.show', $conversation));

        // Oddiy so‘rovda "latest" yo‘q.
        $this->actingAs($me)->getJson('/badges')->assertJsonMissingPath('latest');
    }

    public function test_post_can_be_deleted_in_place_from_feed(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create();

        $this->actingAs(User::factory()->create())->deleteJson("/posts/{$post->id}")->assertForbidden();
        $this->actingAs($user)->deleteJson("/posts/{$post->id}")->assertOk()
            ->assertJsonPath('message', 'Post o‘chirildi.')
            ->assertJsonPath('redirect', route('profile.show', $user->username));
        $this->assertSoftDeleted($post);
    }
}
