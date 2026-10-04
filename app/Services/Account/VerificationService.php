<?php

namespace App\Services\Account;

use App\Enums\NotificationType;
use App\Models\User;
use App\Services\Social\AuditLogger;
use App\Services\Social\NotificationService;
use Illuminate\Support\Facades\Cache;

/**
 * Tasdiqlangan akkaunt belgisi. Faqat admin beradi/oladi; har bir o‘zgarish audit log'ga yoziladi
 * va foydalanuvchiga bildirishnoma yuboriladi.
 */
class VerificationService
{
    public function __construct(private AuditLogger $audit, private NotificationService $notifications) {}

    public function verify(User $user, User $admin): bool
    {
        if ($user->isVerified()) {
            return false;
        }

        $user->forceFill(['verified_at' => now(), 'verified_by' => $admin->id])->save();
        $this->audit->log('user.verified', $user, ['verified' => false], ['verified' => true], $admin);
        $this->notifications->notify($user, NotificationType::System, null, $user, [
            'message' => 'Akkauntingiz tasdiqlandi — endi ismingiz yonida tasdiqlangan belgisi ko‘rinadi.',
        ]);
        $this->forgetCaches();

        return true;
    }

    public function unverify(User $user, User $admin): bool
    {
        if (! $user->isVerified()) {
            return false;
        }

        $user->forceFill(['verified_at' => null, 'verified_by' => null])->save();
        $this->audit->log('user.unverified', $user, ['verified' => true], ['verified' => false], $admin);
        $this->forgetCaches();

        return true;
    }

    /** Yon paneldagi tavsiyalar keshlangan — belgi darhol ko‘rinishi uchun tozalanadi. */
    private function forgetCaches(): void
    {
        Cache::forget('sidebar:suggested:guest');
    }
}
