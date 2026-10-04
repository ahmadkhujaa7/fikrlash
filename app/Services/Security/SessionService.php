<?php

namespace App\Services\Security;

use App\Models\User;
use App\Models\UserSession;
use App\Services\Social\AuditLogger;
use Illuminate\Support\Str;

/** Faol sessiyalar va qurilmalardan chiqarish (admin kuzatuvi). */
class SessionService
{
    public function __construct(private AuditLogger $audit, private LoginTracker $tracker) {}

    public function terminate(UserSession $session, ?User $admin = null): void
    {
        $user = $session->user;
        $session->delete();
        $this->audit->log('session.terminated', $user, [], ['device' => $session->device(), 'ip' => $session->ip_address], $admin);
    }

    /** Barcha brauzer sessiyalari va API tokenlari bekor qilinadi, "meni eslab qol" kukisi ham ishlamay qoladi. */
    public function terminateAll(User $user, ?User $admin = null): int
    {
        $sessions = UserSession::query()->where('user_id', $user->id)->delete();
        $tokens = $user->tokens()->delete();
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

        $this->audit->log('user.sessions_revoked', $user, [], ['sessions' => $sessions, 'tokens' => $tokens], $admin);
        $this->tracker->record('sessions_revoked', $user, actor: $admin);

        return $sessions + $tokens;
    }
}
