<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\UserFollowed;
use App\Services\Feed\RecommendationService;
use App\Services\Social\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyUserFollowed implements ShouldQueue
{
    public bool $deleteWhenMissingModels = true;

    public function __construct(private NotificationService $notifications, private RecommendationService $recommendations) {}

    public function handle(UserFollowed $event): void
    {
        $this->notifications->notify($event->following, NotificationType::Followed, $event->follower, $event->follower);
        // Yangi obuna "Siz uchun" lentasiga ta'sir qiladi.
        $this->recommendations->forget($event->follower);
    }
}
