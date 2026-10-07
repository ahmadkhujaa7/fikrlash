<?php

namespace Tests\Feature\Admin;

use App\Enums\PostStatus;
use App\Enums\UserStatus;
use App\Filament\Pages\SystemSettings;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\Post;
use App\Models\Report;
use App\Models\Setting;
use App\Models\User;
use App\Services\Account\VerificationService;
use App\Services\Moderation\ModerationService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    public function test_guest_is_redirected_and_regular_user_is_forbidden(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_admin_pages_render(): void
    {
        $this->seedCategories();
        Post::factory()->count(3)->create();
        Report::factory()->create();
        $admin = $this->admin();

        foreach (['/admin', '/admin/users', '/admin/posts', '/admin/comments', '/admin/reports', '/admin/categories',
            '/admin/tags', '/admin/audit-logs', '/admin/api-tokens', '/admin/ai-analytics', '/admin/system-settings', '/admin/announcements', '/admin/announcements/create'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
        // Eski "Bildirishnoma yuborish" sahifasi — E'lonlarga yo‘naltiradi.
        $this->actingAs($admin)->get('/admin/broadcast-notification')->assertRedirect('/admin/announcements/create');
    }

    public function test_moderation_actions_are_audited(): void
    {
        $admin = $this->admin();
        $post = Post::factory()->create();
        $user = User::factory()->create();
        $moderation = app(ModerationService::class);
        $this->actingAs($admin);

        $moderation->hidePost($post, $admin, 'Spam');
        $this->assertSame(PostStatus::Hidden, $post->fresh()->status);

        $moderation->suspendUser($user, now()->addDay(), $admin, 'Qoidabuzarlik');
        $this->assertFalse($user->fresh()->canInteract());

        $user->createToken('t');
        $moderation->blockUser($user, $admin);
        $this->assertSame(UserStatus::Blocked, $user->fresh()->status);
        $this->assertSame(0, $user->tokens()->count());

        $this->assertDatabaseHas('audit_logs', ['action' => 'post.hidden', 'user_id' => $admin->id, 'target_type' => 'post']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.suspended', 'target_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.blocked', 'target_id' => $user->id]);
    }

    public function test_expired_suspension_is_lifted_automatically(): void
    {
        $user = User::factory()->suspended(now()->subMinute())->create();
        $this->actingAs($user)->get('/')->assertOk();
        $this->assertSame(UserStatus::Active, $user->fresh()->status);
    }

    public function test_admin_creates_user_and_verified_badge_shows_everywhere(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/users/create')->assertOk();

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Rasmiy Kanal', 'username' => 'rasmiy', 'role' => 'user',
                'phone' => '90 123 45 67', 'password' => 'maxfiy-parol-1', 'status' => 'active', 'is_verified' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::query()->where('username', 'rasmiy')->firstOrFail();
        $this->assertSame('+998901234567', $user->phone);
        $this->assertNotNull($user->phone_verified_at);
        $this->assertTrue($user->isVerified());
        $this->assertTrue(Hash::check('maxfiy-parol-1', $user->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.created_by_admin', 'target_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.verified', 'target_id' => $user->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $user->id, 'type' => 'system']);

        // Admin yaratgan foydalanuvchi o‘z paroli bilan kira oladi.
        auth()->logout();
        $this->post('/login', ['login' => 'rasmiy', 'password' => 'maxfiy-parol-1'])->assertRedirect();
        $this->assertAuthenticatedAs($user);

        // Belgi: profil, post kartochkasi, API, qidiruv.
        $post = Post::factory()->for($user)->create(['content' => 'Rasmiy e’lon matni']);
        $this->get('/@rasmiy')->assertSee('Tasdiqlangan akkaunt');
        $this->get("/posts/{$post->id}")->assertSee('aria-label="Tasdiqlangan akkaunt"', false);
        $this->getJson('/api/v1/users/'.$user->id)->assertJsonPath('data.is_verified', true);
        User::factory()->create(['username' => 'rasmiyfan', 'name' => 'Rasmiy Fan', 'followers_count' => 999]);
        $this->get('/search/suggest?q=rasmiy')->assertSeeInOrder(['@rasmiy', '@rasmiyfan']);
    }

    public function test_admin_can_verify_and_unverify_and_users_cannot_fake_badge(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $service = app(VerificationService::class);

        $this->assertTrue($service->verify($user, $admin));
        $this->assertFalse($service->verify($user, $admin)); // ikkinchi marta — o‘zgarmaydi
        $this->assertSame($admin->id, $user->fresh()->verified_by);
        $this->assertTrue($service->unverify($user, $admin));
        $this->assertFalse($user->fresh()->isVerified());
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.unverified', 'target_id' => $user->id]);

        // Foydalanuvchi ismiga ✓ qo‘shib soxtalashtira olmaydi.
        $this->actingAs($user)->from('/settings')->put('/settings/profile', ['name' => 'Ali ✓', 'username' => $user->username])
            ->assertSessionHasErrors('name');
    }

    public function test_branding_and_daily_question_are_managed_from_admin(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(SystemSettings::class)
            ->fillForm(['site_name' => 'Fikr Maydoni', 'daily_question' => 'Qaysi kitob sizni o‘zgartirdi?'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Fikr Maydoni', Setting::read('site_name'));
        $this->actingAs($admin)->get('/')->assertSee('Qaysi kitob sizni o‘zgartirdi?', false)->assertSee('<title>Fikr Maydoni', false);

        // Logo yuklanmagan — standart so‘z belgisi; yuklangan — rasm (tungi varianti bilan), favicon ham.
        auth()->logout();
        $this->get('/login')->assertOk()->assertSee('fikrlash')->assertDontSee('branding/', false);
        Storage::fake('public');
        Setting::write('logo_light', 'branding/logo.png');
        Setting::write('logo_dark', 'branding/logo-dark.png');
        Setting::write('favicon', 'branding/icon.png');
        $html = $this->get('/login')->assertOk()->getContent();
        $this->assertStringContainsString('branding/logo.png', $html);
        $this->assertStringContainsString('branding/logo-dark.png', $html);
        $this->assertStringContainsString('alt="Fikr Maydoni"', $html);
        $this->assertStringContainsString('branding/icon.png', $html);
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('branding/logo.png', false);
    }
}
