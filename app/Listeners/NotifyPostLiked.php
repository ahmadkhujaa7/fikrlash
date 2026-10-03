<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\PostLiked;
use App\Services\Social\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyPostLiked implements ShouldQueue
{
    public bool $deleteWhenMissingModels = true;

    public function __construct(private NotificationService $notifications) {}

    public function handle(PostLiked $event): void
    {
        $this->notifications->notify($event->post->user, NotificationType::PostLiked, $event->user, $event->post);
    }
}
