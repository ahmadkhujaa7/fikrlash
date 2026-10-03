<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Tests\TestCase;

class LoginTest extends TestCase
{
    public function test_login_with_phone_or_username(): void
    {
        $user = User::factory()->create(['username' => 'jasur', 'phone' => '+998901234567']);

        $this->post('/login', ['login' => '90 123 45 67', 'password' => 'password'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        $this->post('/logout');
        $this->assertGuest();

        $this->post('/login', ['login' => '@Jasur', 'password' => 'password'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_wrong_credentials_give_generic_error(): void
    {
        User::factory()->create(['username' => 'jasur']);

        $wrongPassword = $this->post('/login', ['login' => 'jasur', 'password' => 'xato'])->assertSessionHasErrors('login');
        $unknownUser = $this->post('/login', ['login' => 'yoqodam', 'password' => 'xato'])->assertSessionHasErrors('login');

        $this->assertSame(session('errors')->first('login'), 'Telefon/username yoki parol noto‘g‘ri.');
        $this->assertGuest();
    }

    public function test_blocked_user_cannot_login_and_is_logged_out(): void
    {
        $user = User::factory()->blocked()->create(['username' => 'blok']);
        $this->post('/login', ['login' => 'blok', 'password' => 'password'])->assertSessionHasErrors('login');
        $this->assertGuest();

        $active = User::factory()->create();
        $this->actingAs($active)->get('/')->assertOk();
        $active->forceFill(['status' => UserStatus::Blocked])->save();
        $this->get('/saved')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_deactivated_user_is_reactivated_on_login(): void
    {
        User::factory()->create(['username' => 'dam', 'status' => UserStatus::Deactivated]);

        $this->post('/login', ['login' => 'dam', 'password' => 'password'])->assertRedirect();
        $this->assertSame(UserStatus::Active, User::query()->where('username', 'dam')->first()->status);
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create(['username' => 'jasur']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['login' => 'jasur', 'password' => 'xato']);
        }

        $this->post('/login', ['login' => 'jasur', 'password' => 'password'])->assertStatus(429);
    }
}
