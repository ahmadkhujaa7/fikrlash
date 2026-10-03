<?php

namespace Tests\Feature\Auth;

use App\Enums\OtpPurpose;
use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    public function test_user_resets_password_with_sms_code_and_tokens_are_revoked(): void
    {
        $user = User::factory()->create(['phone' => '+998901234567']);
        $user->createToken('mobil');

        $this->post('/password/forgot', ['phone' => '+998 90 123 45 67'])->assertRedirect(route('password.reset'));
        $code = $this->sms()->lastCodeFor('+998901234567');

        $this->post('/password/reset', [
            'phone' => '+998901234567', 'code' => $code, 'password' => 'yangiParol1', 'password_confirmation' => 'yangiParol1',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('yangiParol1', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_unknown_phone_gets_same_response_without_sms(): void
    {
        $this->post('/password/forgot', ['phone' => '+998907777777'])
            ->assertRedirect(route('password.reset'))
            ->assertSessionHas('status');

        $this->assertEmpty($this->sms()->messages);
    }

    public function test_code_for_registration_cannot_reset_password(): void
    {
        $user = User::factory()->create(['phone' => '+998901234567']);
        // Boshqa maqsad uchun yuborilgan kod parolni tiklay olmaydi.
        app(OtpService::class)->send('+998901234567', OtpPurpose::PhoneChange, '127.0.0.1');
        $code = $this->sms()->lastCodeFor('+998901234567');

        $this->withSession(['password_reset_phone' => '+998901234567'])->post('/password/reset', [
            'phone' => '+998901234567', 'code' => $code, 'password' => 'yangiParol1', 'password_confirmation' => 'yangiParol1',
        ])->assertSessionHasErrors('code');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
