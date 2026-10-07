<?php

namespace App\Services\Push;

use App\Jobs\SendPushNotification;
use App\Models\DeviceToken;

/**
 * Mobil ilovaga push bildirishnoma. Firebase sozlanmagan bo‘lsa — jim (hech narsa yuborilmaydi).
 * Yuborish so‘rovni sekinlashtirmaydi: navbatda (PUSH_DISPATCH=queue, queue:work ishlasa)
 * yoki javob brauzerga ketgandan keyin (standart — alohida worker shart emas).
 */
class PushService
{
    public function __construct(private FcmClient $fcm) {}

    public function enabled(): bool
    {
        return $this->fcm->configured();
    }

    /** @param  int|list<int>  $userIds */
    public function toUsers(int|array $userIds, PushMessage $message): void
    {
        $ids = array_values(array_unique(array_map('intval', (array) $userIds)));
        if (! $ids || ! $this->enabled() || ! DeviceToken::query()->whereIn('user_id', $ids)->exists()) {
            return;
        }

        $job = new SendPushNotification($ids, $message->toArray());

        if (config('fikrlash.push.dispatch') === 'queue') {
            dispatch($job);
        } else {
            dispatch($job)->afterResponse();
        }
    }

    /** Darhol yuboradi; eskirgan tokenlar o‘chiriladi. Nechta qurilmaga yetgani qaytadi. */
    public function deliver(array $userIds, PushMessage $message): int
    {
        $payload = $message->toFcm();
        $sent = 0;

        DeviceToken::query()->whereIn('user_id', $userIds)->chunkById(200, function ($tokens) use ($payload, &$sent) {
            foreach ($tokens as $device) {
                $result = $this->fcm->send($device->token, $payload);
                if ($result === FcmClient::OK) {
                    $sent++;
                } elseif ($result === FcmClient::INVALID_TOKEN) {
                    $device->delete();
                }
            }
        });

        return $sent;
    }
}
