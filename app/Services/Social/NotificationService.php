<?php

namespace App\Services\Social;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use App\Services\Push\PushMessage;
use App\Services\Push\PushService;
use App\Support\NotificationPresenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class NotificationService
{
    /** Bu turlar takrorlanmaydi (masalan like → unlike → like spam bo‘lmasin). */
    private const DEDUPED = [NotificationType::Followed, NotificationType::PostLiked, NotificationType::Mentioned];

    public function __construct(private PushService $push) {}

    public function notify(User $recipient, NotificationType $type, ?User $actor = null, ?Model $subject = null, array $data = []): ?Notification
    {
        if ($actor && $actor->is($recipient)) {
            return null; // o‘ziga o‘zi bildirishnoma yubormaydi
        }

        if (! $recipient->wantsNotification($type)) {
            return null; // foydalanuvchi sozlamalarda bu turni o‘chirgan
        }

        $attributes = [
            'user_id' => $recipient->id,
            'actor_id' => $actor?->id,
            'type' => $type,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
        ];

        if (in_array($type, self::DEDUPED, true) && Notification::query()->where($attributes)->exists()) {
            return null;
        }

        $notification = Notification::query()->create($attributes + ['data' => $data ?: null]);
        $this->forgetUnreadCount($recipient->id);
        $this->pushToPhone($notification, $actor, $subject);

        return $notification;
    }

    /** Mobil ilovaga: "Aziz — fikringizni yoqtirdi". */
    private function pushToPhone(Notification $notification, ?User $actor, ?Model $subject): void
    {
        if (! $this->push->enabled()) {
            return;
        }

        $notification->setRelation('actor', $actor)->setRelation('subject', $subject);
        $p = NotificationPresenter::present($notification);
        $body = $actor ? $p['text'] : Str::limit($p['text'], 140);
        if ($p['excerpt']) {
            $body .= ': «'.Str::limit($p['excerpt'], 90).'»';
        }

        $this->push->toUsers($notification->user_id, new PushMessage(
            title: $actor?->name ?? config('app.name'),
            body: $body,
            url: $p['url'] ?? route('notifications.index'),
            tag: 'n-'.$notification->type->value.'-'.($notification->subject_id ?? $notification->id),
        ));
    }

    public function unreadCount(User $user): int
    {
        return Cache::remember($this->unreadKey($user->id), now()->addMinutes(5), fn () => $user->notifications()->unread()->count());
    }

    public function markAllRead(User $user): int
    {
        $updated = $user->notifications()->unread()->update(['read_at' => now()]);
        $this->forgetUnreadCount($user->id);

        return $updated;
    }

    public function markRead(Notification $notification): void
    {
        if (! $notification->isRead()) {
            $notification->forceFill(['read_at' => now()])->save();
            $this->forgetUnreadCount($notification->user_id);
        }
    }

    public function forgetUnreadCount(int $userId): void
    {
        Cache::forget($this->unreadKey($userId));
    }

    /** @param  list<int>  $userIds */
    public function forgetUnreadCounts(array $userIds): void
    {
        if ($userIds) {
            Cache::deleteMultiple(array_map(fn (int $id) => $this->unreadKey($id), $userIds));
        }
    }

    private function unreadKey(int $userId): string
    {
        return "notifications:unread:{$userId}";
    }
}
