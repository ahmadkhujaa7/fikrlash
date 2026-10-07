<?php

namespace App\Jobs;

use App\Services\Push\PushMessage;
use App\Services\Push\PushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Foydalanuvchilarning barcha qurilmalariga push yuborish. */
class SendPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    /** @param  list<int>  $userIds */
    public function __construct(public array $userIds, public array $message) {}

    public function handle(PushService $push): void
    {
        $push->deliver($this->userIds, PushMessage::fromArray($this->message));
    }
}
