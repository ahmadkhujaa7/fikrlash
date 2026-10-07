<?php

namespace App\Services\Social;

use App\Enums\NotificationType;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\AnnouncementReceipt;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Admin e'lonlari: yuborish (har bir qabul qiluvchiga bildirishnoma qatori) va statistika:
 *  - "ko‘rdi"  — e'lon foydalanuvchining bildirishnomalar sahifasida ko‘rsatildi;
 *  - "ochdi"   — foydalanuvchi e'lonni bosib, to‘liq matnini ochdi.
 */
class AnnouncementService
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * Faol foydalanuvchilarga (yoki tanlanganlarga) yuboradi. Katta auditoriya — bo‘laklab, bulk insert.
     *
     * @param  list<int>|null  $userIds  audience = users bo‘lganda
     */
    public function publish(Announcement $announcement, ?array $userIds = null): int
    {
        $now = now();
        $count = 0;

        User::query()
            ->where('status', UserStatus::Active)
            ->when($announcement->audience === Announcement::AUDIENCE_USERS, fn ($q) => $q->whereKey($userIds ?? []))
            ->select('id')
            ->chunkById(1000, function ($users) use ($announcement, $now, &$count) {
                Notification::query()->insert($users->map(fn (User $u) => [
                    'user_id' => $u->id,
                    'type' => NotificationType::Announcement->value,
                    'subject_type' => $announcement->getMorphClass(),
                    'subject_id' => $announcement->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
                $this->notifications->forgetUnreadCounts($users->pluck('id')->all());
                $count += $users->count();
            });

        $announcement->forceFill(['recipients_count' => $count, 'sent_at' => $now])->save();

        return $count;
    }

    /** Bildirishnomalar sahifasida ko‘rsatilgan e'lonlar — birinchi ko‘rilgan vaqt saqlanadi. */
    public function markSeen(User $user, iterable $announcementIds): void
    {
        $now = now();
        $rows = collect($announcementIds)->unique()->map(fn ($id) => [
            'announcement_id' => (int) $id,
            'user_id' => $user->id,
            'seen_at' => $now,
        ])->values()->all();

        if ($rows) {
            DB::table('announcement_receipts')->insertOrIgnore($rows);
        }
    }

    /** Foydalanuvchi e'lonni bosib ochdi. Qabul qiluvchi bo‘lmasa — false. */
    public function markOpened(Announcement $announcement, User $user): bool
    {
        if (! $this->isRecipient($announcement, $user)) {
            return false;
        }

        $now = now();
        $receipt = AnnouncementReceipt::query()->createOrFirst(
            ['announcement_id' => $announcement->id, 'user_id' => $user->id],
            ['seen_at' => $now, 'opened_at' => $now],
        );

        if ($receipt->opened_at === null) {
            $receipt->forceFill(['opened_at' => $now, 'seen_at' => $receipt->seen_at ?? $now])->save();
        }

        return true;
    }

    public function isRecipient(Announcement $announcement, User $user): bool
    {
        return Notification::query()
            ->where('user_id', $user->id)
            ->where('type', NotificationType::Announcement->value)
            ->where('subject_type', $announcement->getMorphClass())
            ->where('subject_id', $announcement->id)
            ->exists();
    }

    /** @return array{recipients: int, seen: int, opened: int, seen_rate: float, open_rate: float} */
    public function stats(Announcement $announcement): array
    {
        $counts = $announcement->receipts()
            ->selectRaw('COUNT(seen_at) as seen, COUNT(opened_at) as opened')
            ->toBase()->first();

        $recipients = (int) $announcement->recipients_count;
        $seen = (int) ($counts->seen ?? 0);
        $opened = (int) ($counts->opened ?? 0);

        return [
            'recipients' => $recipients,
            'seen' => $seen,
            'opened' => $opened,
            'seen_rate' => $recipients ? round($seen / $recipients * 100, 1) : 0.0,
            'open_rate' => $recipients ? round($opened / $recipients * 100, 1) : 0.0,
        ];
    }
}
