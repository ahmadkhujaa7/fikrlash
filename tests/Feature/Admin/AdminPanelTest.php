<?php

namespace Tests\Feature\Admin;

use App\Enums\PostStatus;
use App\Enums\UserStatus;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Services\Moderation\ModerationService;
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
            '/admin/tags', '/admin/audit-logs', '/admin/api-tokens', '/admin/ai-analytics', '/admin/system-settings', '/admin/broadcast-notification'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
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
}
