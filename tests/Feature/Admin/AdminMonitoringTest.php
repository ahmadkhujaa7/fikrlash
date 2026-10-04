<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers;
use App\Filament\Widgets\GrowthChart;
use App\Filament\Widgets\LatestLogins;
use App\Filament\Widgets\LiveStats;
use App\Filament\Widgets\NewUsers;
use App\Filament\Widgets\OnlineUsers;
use App\Filament\Widgets\SecurityStats;
use App\Filament\Widgets\SuspiciousIps;
use App\Models\AuditLog;
use App\Models\LoginEvent;
use App\Models\Post;
use App\Models\User;
use App\Models\UserSession;
use App\Services\Security\SessionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AdminMonitoringTest extends TestCase
{
    public function test_monitoring_pages_and_user_360_view_render(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        Post::factory()->for($user)->create();
        LoginEvent::query()->create(['user_id' => $user->id, 'event' => 'login', 'ip' => '10.0.0.1', 'device' => 'Chrome · Windows']);
        foreach (range(1, 6) as $_) {
            LoginEvent::query()->create(['event' => 'failed', 'identifier' => 'nomalum', 'ip' => '10.9.9.9']);
        }

        foreach (['/admin', '/admin/users', '/admin/users?tab=online', '/admin/users?tab=restricted', '/admin/users/create',
            "/admin/users/{$user->id}", "/admin/users/{$user->id}/edit", '/admin/security', '/admin/login-events',
            '/admin/sessions', '/admin/audit-logs'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
        // Vidjetlar (Livewire, lazy) alohida tekshiriladi.
        foreach ([LiveStats::class, GrowthChart::class, OnlineUsers::class,
            LatestLogins::class, NewUsers::class, SecurityStats::class] as $widget) {
            Livewire::actingAs($admin)->test($widget)->assertSuccessful();
        }
        Livewire::actingAs($admin)->test(SuspiciousIps::class)->assertSee('10.9.9.9')->assertSee('6');

        foreach ([RelationManagers\PostsRelationManager::class, RelationManagers\CommentsRelationManager::class,
            RelationManagers\LoginEventsRelationManager::class, RelationManagers\SessionsRelationManager::class,
            RelationManagers\ReportsRelationManager::class, RelationManagers\AuditTrailRelationManager::class] as $manager) {
            Livewire::actingAs($admin)->test($manager, ['ownerRecord' => $user, 'pageClass' => ViewUser::class])->assertSuccessful();
        }

        // "Yangi foydalanuvchi" tugmasi ro‘yxatda ko‘rinadi.
        Livewire::actingAs($admin)->test(ListUsers::class)->assertActionVisible('create');
    }

    public function test_logins_logouts_and_failed_attempts_are_tracked(): void
    {
        $user = User::factory()->create(['username' => 'kuzatuv', 'password' => 'togri-parol']);
        $ua = ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36'];

        $this->post('/login', ['login' => 'kuzatuv', 'password' => 'xato'], $ua)->assertSessionHasErrors('login');
        $this->post('/login', ['login' => 'yoq_odam', 'password' => 'xato'], $ua);
        $this->post('/login', ['login' => 'kuzatuv', 'password' => 'togri-parol'], $ua)->assertRedirect();
        $this->post('/logout', [], $ua);
        $this->postJson('/api/v1/auth/login', ['login' => 'kuzatuv', 'password' => 'togri-parol', 'device_name' => 'iPhone'])->assertOk();

        $this->assertDatabaseHas('login_events', ['user_id' => $user->id, 'event' => 'failed', 'identifier' => 'kuzatuv']);
        $this->assertDatabaseHas('login_events', ['user_id' => null, 'event' => 'failed', 'identifier' => 'yoq_odam']);
        $this->assertDatabaseHas('login_events', ['user_id' => $user->id, 'event' => 'login', 'channel' => 'web', 'device' => 'Chrome · Windows']);
        $this->assertDatabaseHas('login_events', ['user_id' => $user->id, 'event' => 'logout']);
        $this->assertDatabaseHas('login_events', ['user_id' => $user->id, 'event' => 'login', 'channel' => 'api']);

        $user->forceFill(['status' => UserStatus::Blocked])->save();
        $this->post('/login', ['login' => 'kuzatuv', 'password' => 'togri-parol']);
        $this->assertDatabaseHas('login_events', ['user_id' => $user->id, 'event' => 'blocked']);
    }

    public function test_admin_can_edit_any_user_field_and_changes_are_audited(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['name' => 'Eski Ism', 'username' => 'eski_user']);
        DB::table('sessions')->insert(['id' => 'sess-1', 'user_id' => $user->id, 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => now()->timestamp]);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm([
                'name' => 'Yangi Ism', 'username' => 'yangi_user', 'email' => 'yangi@example.uz',
                'phone' => '93 111 22 33', 'password' => 'admin-bergan-parol', 'gender' => 'female',
                'birth_date' => '1995-05-17', 'bio' => 'Admin yozdi', 'role' => 'user', 'status' => 'active',
                'is_verified' => true, 'admin_note' => 'Maxfiy izoh',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertSame('Yangi Ism', $user->name);
        $this->assertSame('yangi_user', $user->username);
        $this->assertSame('+998931112233', $user->phone);
        $this->assertSame('1995-05-17', $user->birth_date->toDateString());
        $this->assertSame('Maxfiy izoh', $user->admin_note);
        $this->assertNotSame('Maxfiy izoh', DB::table('users')->where('id', $user->id)->value('admin_note')); // shifrlangan
        $this->assertTrue($user->isVerified());
        $this->assertTrue(Hash::check('admin-bergan-parol', $user->password));
        $this->assertSame(0, UserSession::query()->where('user_id', $user->id)->count()); // parol o‘zgardi — qurilmalardan chiqarildi

        $log = AuditLog::query()->where('action', 'user.updated_by_admin')->where('target_id', $user->id)->firstOrFail();
        $this->assertSame('Eski Ism', $log->old_values['name']);
        $this->assertSame('Yangi Ism', $log->new_values['name']);
        $this->assertTrue($log->new_values['password_changed']);
        $this->assertArrayNotHasKey('password', $log->new_values);

        // Holatni formadan "bloklangan"ga o‘tkazish — moderatsiya xizmati orqali.
        Livewire::actingAs($admin)->test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['status' => 'blocked'])->call('save')->assertHasNoFormErrors();
        $this->assertSame(UserStatus::Blocked, $user->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.blocked', 'target_id' => $user->id]);

        // Telefon boshqa akkauntda bo‘lsa — rad etiladi.
        $other = User::factory()->create(['phone' => '+998901234567']);
        Livewire::actingAs($admin)->test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['phone' => '+998 90 123 45 67'])->call('save')->assertHasFormErrors(['phone']);
    }

    public function test_admin_cannot_demote_self_or_remove_last_admin(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(EditUser::class, ['record' => $admin->getRouteKey()])
            ->assertFormFieldIsDisabled('role')
            ->assertFormFieldIsDisabled('status');

        $other = User::factory()->create(['role' => UserRole::Admin]);
        $other->forceFill(['role' => UserRole::Admin])->save();
        Livewire::actingAs($admin)->test(EditUser::class, ['record' => $other->getRouteKey()])
            ->fillForm(['role' => 'user'])->call('save')->assertHasNoFormErrors();
        $this->assertSame(UserRole::User, $other->fresh()->role);
    }

    public function test_impersonation_is_audited_restricted_and_reversible(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['username' => 'oddiy']);
        $otherAdmin = User::factory()->create();
        $otherAdmin->forceFill(['role' => UserRole::Admin])->save();

        Livewire::actingAs($admin)->test(ViewUser::class, ['record' => $otherAdmin->getRouteKey()])
            ->assertActionHidden('impersonate');

        Livewire::actingAs($admin)->test(ViewUser::class, ['record' => $user->getRouteKey()])
            ->callAction('impersonate')
            ->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('login_events', ['user_id' => $user->id, 'event' => 'impersonate', 'actor_id' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.impersonated', 'target_id' => $user->id, 'user_id' => $admin->id]);

        $this->get('/')->assertSee('nomidan ko‘ryapsiz', false);
        $this->from('/settings/security')->put('/settings/password', ['current_password' => 'x', 'password' => 'yangiparol1', 'password_confirmation' => 'yangiparol1'])
            ->assertRedirect('/settings/security')->assertSessionHas('toast');
        $this->get('/admin')->assertForbidden();

        $this->post('/impersonate/stop')->assertRedirect("/admin/users/{$user->id}");
        $this->assertAuthenticatedAs($admin);
        $this->assertDatabaseHas('login_events', ['user_id' => $user->id, 'event' => 'impersonate_end']);
    }

    public function test_sessions_can_be_terminated(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $user->createToken('telefon');
        foreach (['a', 'b'] as $id) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'ip_address' => '2.2.2.2', 'user_agent' => 'Mozilla (iPhone) Safari/1', 'payload' => '', 'last_activity' => now()->timestamp]);
        }

        $session = UserSession::query()->findOrFail('a');
        $this->assertSame('Safari · iPhone', $session->device());
        $this->assertTrue($session->isOnline());

        app(SessionService::class)->terminate($session, $admin);
        $this->assertSame(1, UserSession::query()->where('user_id', $user->id)->count());

        $this->assertSame(2, app(SessionService::class)->terminateAll($user, $admin));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseHas('login_events', ['user_id' => $user->id, 'event' => 'sessions_revoked', 'actor_id' => $admin->id]);
    }
}
