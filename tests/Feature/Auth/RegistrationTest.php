<?php

namespace Tests\Feature\Auth;

use App\Models\PhoneVerification;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    private array $data = [
        'name' => 'Aziz Karimov',
        'username' => 'Aziz_K',
        'phone' => '90 123 45 67',
        'password' => 'parol1234',
        'password_confirmation' => 'parol1234',
        'terms' => '1',
    ];

    public function test_user_registers_with_sms_code(): void
    {
        $this->post('/register', $this->data)->assertRedirect(route('register.verify'));

        // Telefon tasdiqlanmaguncha akkaunt yaratilmaydi.
        $this->assertDatabaseCount('users', 0);
        $code = $this->sms()->lastCodeFor('+998901234567');
        $this->assertNotNull($code);

        // Kod DB'da ochiq saqlanmaydi.
        $this->assertNotSame($code, PhoneVerification::query()->first()->code_hash);

        $this->post('/register/verify', ['code' => $code])->assertRedirect(route('home'));

        $user = User::query()->firstOrFail();
        $this->assertSame('aziz_k', $user->username);
        $this->assertSame('+998901234567', $user->phone);
        $this->assertNotNull($user->phone_verified_at);
        $this->assertTrue(Hash::check('parol1234', $user->password));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('notifications', ['user_id' => $user->id, 'type' => 'system']);
    }

    public function test_wrong_code_is_rejected_and_attempts_are_limited(): void
    {
        $this->post('/register', $this->data);
        $code = $this->sms()->lastCodeFor('+998901234567');
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->post('/register/verify', ['code' => $wrong])->assertSessionHasErrors('code');
        }

        // 5 urinishdan keyin hatto to‘g‘ri kod ham ishlamaydi.
        $this->post('/register/verify', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_expired_code_is_rejected(): void
    {
        $this->post('/register', $this->data);
        $code = $this->sms()->lastCodeFor('+998901234567');

        $this->travel(config('fikrlash.otp.ttl_minutes') + 1)->minutes();
        $this->post('/register/verify', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_resend_respects_cooldown(): void
    {
        $this->post('/register', $this->data);
        $this->post('/register/resend')->assertSessionHasErrors('code');
        $this->assertCount(1, $this->sms()->messages);

        $this->travel(61)->seconds();
        $this->post('/register/resend')->assertSessionHasNoErrors();
        $this->assertCount(2, $this->sms()->messages);
    }

    public function test_old_code_cannot_be_used_after_resend(): void
    {
        $this->post('/register', $this->data);
        $first = $this->sms()->lastCodeFor('+998901234567');
        $this->travel(61)->seconds();
        $this->post('/register/resend');
        $second = $this->sms()->lastCodeFor('+998901234567');

        if ($first !== $second) {
            $this->post('/register/verify', ['code' => $first])->assertSessionHasErrors('code');
        }
        $this->post('/register/verify', ['code' => $second])->assertRedirect(route('home'));
    }

    public function test_validation_rules(): void
    {
        User::factory()->create(['username' => 'band', 'phone' => '+998901111111']);

        $this->post('/register', array_merge($this->data, ['username' => 'band']))->assertSessionHasErrors('username');
        $this->post('/register', array_merge($this->data, ['username' => 'admin']))->assertSessionHasErrors('username');
        $this->post('/register', array_merge($this->data, ['username' => '12345']))->assertSessionHasErrors('username');
        $this->post('/register', array_merge($this->data, ['phone' => '+998 90 111 11 11']))->assertSessionHasErrors('phone');
        $this->post('/register', array_merge($this->data, ['phone' => '123']))->assertSessionHasErrors('phone');
        $this->post('/register', array_merge($this->data, ['password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
        $this->post('/register', array_merge($this->data, ['terms' => null]))->assertSessionHasErrors('terms');
        $this->assertEmpty($this->sms()->messages);
    }

    public function test_single_password_field_is_enough(): void
    {
        $data = $this->data;
        unset($data['password_confirmation']);

        $this->post('/register', $data)->assertRedirect(route('register.verify'));
        $this->assertNotNull($this->sms()->lastCodeFor('+998901234567'));
    }

    public function test_live_check_endpoint(): void
    {
        User::factory()->create(['username' => 'band', 'phone' => '+998901111111']);

        $this->getJson('/register/check?field=username&value=band')->assertOk()->assertJson(['ok' => false]);
        $this->getJson('/register/check?field=username&value=@Yangi_nom')->assertOk()->assertJson(['ok' => true]);
        $this->getJson('/register/check?field=username&value=admin')->assertJson(['ok' => false]);
        $this->getJson('/register/check?field=phone&value=90 111 11 11')->assertJson(['ok' => false, 'login' => true]);
        $this->getJson('/register/check?field=phone&value=90 222 33 44')->assertJson(['ok' => true]);
        $this->getJson('/register/check?field=phone&value=12')->assertJson(['ok' => false]);
        $this->getJson('/register/check?field=boshqa&value=x')->assertStatus(422);
    }

    public function test_step_two_shows_masked_phone_and_change_link_prefills_step_one(): void
    {
        $this->post('/register', $this->data);

        $this->get(route('register.verify'))
            ->assertOk()
            ->assertSee('Raqamni o‘zgartirish', false)
            ->assertDontSee('901234567');

        $this->get(route('register', ['edit' => 1]))
            ->assertOk()
            ->assertSee('Aziz Karimov')
            ->assertSee('aziz_k')
            ->assertSee('90 123 45 67');
    }

    public function test_verify_page_requires_step_one(): void
    {
        $this->get(route('register.verify'))->assertRedirect(route('register'));
    }

    public function test_registration_can_be_closed_by_admin(): void
    {
        Setting::write('registration_open', false);

        $this->post('/register', $this->data)->assertForbidden();
    }
}
