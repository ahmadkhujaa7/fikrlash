<?php

namespace Tests\Feature\Api;

use App\Models\Post;
use App\Models\User;
use Tests\TestCase;

class ApiTest extends TestCase
{
    public function test_full_api_registration_and_token_flow(): void
    {
        $start = $this->postJson('/api/v1/auth/register', [
            'name' => 'Madina', 'username' => 'madina', 'phone' => '+998935554433',
            'password' => 'parol1234', 'password_confirmation' => 'parol1234', 'terms' => true,
        ])->assertStatus(202)->assertJsonPath('success', true);

        $code = $this->sms()->lastCodeFor('+998935554433');
        $token = $this->postJson('/api/v1/auth/register/verify', [
            'registration_token' => $start->json('data.registration_token'), 'code' => $code, 'device_name' => 'iPhone',
        ])->assertCreated()->assertJsonPath('data.token_type', 'Bearer')->json('data.token');

        $this->assertDatabaseCount('users_tokens', 1);
        // Token DB'da ochiq holda saqlanmaydi.
        $this->assertDatabaseMissing('users_tokens', ['token' => explode('|', $token)[1]]);

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()
            ->assertJsonPath('data.username', 'madina')
            ->assertJsonPath('data.phone', '+998 93 *** ** 33');

        $this->withToken($token)->postJson('/api/v1/posts', ['content' => 'API orqali post #api'])
            ->assertCreated()->assertJsonPath('data.content', 'API orqali post #api')->assertJsonPath('data.author.username', 'madina');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseCount('users_tokens', 0);
    }

    public function test_login_returns_token_and_wrong_password_fails(): void
    {
        User::factory()->create(['username' => 'ali', 'phone' => '+998901112233']);

        $this->postJson('/api/v1/auth/login', ['login' => '+998901112233', 'password' => 'password', 'device_name' => 'cli'])
            ->assertOk()->assertJsonStructure(['success', 'message', 'data' => ['token', 'expires_at', 'user']]);

        $this->postJson('/api/v1/auth/login', ['login' => 'ali', 'password' => 'xato'])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors' => ['login']]);
    }

    public function test_error_envelopes(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized()->assertExactJson(['success' => false, 'message' => 'Avtorizatsiya talab qilinadi.', 'errors' => []]);
        $this->getJson('/api/v1/posts/999999')->assertNotFound()->assertJsonPath('success', false);
        $this->getJson('/api/v1/yoq-endpoint')->assertNotFound()->assertJsonPath('message', 'Endpoint topilmadi.');

        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/posts', [])->assertUnprocessable()->assertJsonStructure(['errors' => ['content']]);
    }

    public function test_tokens_can_be_listed_and_revoked(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('joriy')->plainTextToken;
        $user->createToken('eski');
        $user->createToken('boshqa');

        $this->withToken($current)->getJson('/api/v1/tokens')->assertOk()->assertJsonCount(3, 'data');
        $this->withToken($current)->deleteJson('/api/v1/tokens/others')->assertOk()->assertJsonPath('data.revoked', 2);
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('qisqa', ['*'], now()->addMinute())->plainTextToken;

        $this->travel(2)->minutes();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_blocked_users_token_stops_working(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('t')->plainTextToken;
        $user->forceFill(['status' => 'blocked'])->save();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertForbidden();
    }

    public function test_feed_and_post_endpoints(): void
    {
        $post = Post::factory()->create();
        $user = User::factory()->create();

        $this->getJson('/api/v1/feed?tab=latest')->assertOk()
            ->assertJsonPath('meta.tab', 'latest')
            ->assertJsonStructure(['data' => [['id', 'content', 'content_html', 'author', 'stats', 'url']], 'meta' => ['pagination' => ['next_cursor', 'has_more']]]);

        $this->getJson('/api/v1/feed?tab=following')->assertUnauthorized();

        $this->actingAs($user, 'sanctum')->getJson("/api/v1/posts/{$post->id}")->assertOk()
            ->assertJsonPath('data.viewer.liked', false)
            ->assertJsonPath('data.viewer.can_edit', false);

        $this->actingAs($user, 'sanctum')->patchJson("/api/v1/posts/{$post->id}", ['content' => 'x'])->assertForbidden();
    }

    public function test_profile_update_and_account_deletion(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create();

        $this->actingAs($user, 'sanctum')->patchJson('/api/v1/me', ['name' => 'Yangi Ism', 'bio' => 'Salom'])
            ->assertOk()->assertJsonPath('data.name', 'Yangi Ism');

        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/me', ['password' => 'xato'])->assertUnprocessable();
        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/me', ['password' => 'password'])->assertOk();

        $this->assertSoftDeleted($user);
        $this->assertSoftDeleted($post);
    }
}
