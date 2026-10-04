<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountTest extends TestCase
{
    public function test_profile_page_and_update(): void
    {
        $user = User::factory()->create(['username' => 'oldname']);

        $this->get('/@oldname')->assertOk()->assertSee($user->name);

        $this->actingAs($user)->put('/settings/profile', ['name' => 'Yangi Ism', 'username' => 'NewName', 'bio' => 'Salom'])->assertSessionHasNoErrors();
        $this->assertSame('newname', $user->fresh()->username);
        $this->get('/@newname')->assertOk()->assertSee('Yangi Ism');
        $this->get('/@oldname')->assertNotFound();
    }

    public function test_avatar_is_reencoded_to_webp(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/settings/avatar', ['avatar' => UploadedFile::fake()->image('me.png', 800, 600)])->assertSessionHasNoErrors();

        $path = $user->fresh()->avatar_path;
        $this->assertStringStartsWith('avatars/', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame([400, 400], array_slice(getimagesizefromstring(Storage::disk('public')->get($path)), 0, 2));
    }

    public function test_password_change_requires_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/settings/password', ['current_password' => 'xato', 'password' => 'yangi1234', 'password_confirmation' => 'yangi1234'])
            ->assertSessionHasErrors('current_password');
        $this->actingAs($user)->put('/settings/password', ['current_password' => 'password', 'password' => 'yangi1234', 'password_confirmation' => 'yangi1234'])
            ->assertSessionHasNoErrors();
    }

    public function test_phone_change_with_otp(): void
    {
        $user = User::factory()->create(['phone' => '+998901111111']);

        $this->actingAs($user)->post('/settings/phone', ['phone' => '+998 93 222 22 22'])->assertSessionHasNoErrors();
        $code = $this->sms()->lastCodeFor('+998932222222');
        $this->actingAs($user)->post('/settings/phone/verify', ['code' => $code])->assertSessionHasNoErrors();

        $this->assertSame('+998932222222', $user->fresh()->phone);
    }

    public function test_account_deletion_and_purge(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user)->create(['content' => 'O‘chiriladigan post']);
        Comment::factory()->for($user)->create();

        $this->actingAs($user)->delete('/settings/account', ['password' => 'password', 'confirm' => $user->username])->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertSoftDeleted($user);
        $this->get('/?tab=latest')->assertDontSee('O‘chiriladigan post');
        $this->post('/login', ['login' => $user->username, 'password' => 'password'])->assertSessionHasErrors('login');

        $this->travel(config('fikrlash.accounts.deletion_grace_days') + 1)->days();
        $this->artisan('accounts:purge-deleted')->assertSuccessful();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_data_export(): void
    {
        $user = User::factory()->create();
        Post::factory()->for($user)->create(['content' => 'Eksport posti']);

        $response = $this->actingAs($user)->get('/settings/export')->assertOk();
        $this->assertStringContainsString('Eksport posti', $response->streamedContent());
    }

    public function test_users_have_no_api_token_page_and_api_docs_are_admin_only(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/settings/tokens')->assertNotFound();
        $this->actingAs($user)->get('/settings')->assertOk()->assertDontSee('API token');
        $this->actingAs($user)->get('/docs/api')->assertNotFound();
        $this->actingAs($user)->get('/docs/openapi.json')->assertNotFound();
        $this->actingAs($user)->get('/')->assertDontSee('/docs/api');

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/docs/api')->assertOk();
    }
}
