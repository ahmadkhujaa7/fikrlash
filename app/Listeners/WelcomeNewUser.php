<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\UserRegistered;
use App\Services\Social\NotificationService;
use Illuminate\Support\Facades\Log;

class WelcomeNewUser
{
    public function __construct(private NotificationService $notifications) {}

    public function handle(UserRegistered $event): void
    {
        $this->notifications->notify($event->user, NotificationType::System, null, null, [
            'message' => 'Fikrlash.uz ga xush kelibsiz! Birinchi fikringizni yozing — bu yerda har bir g‘oya qadrlanadi.',
        ]);

        Log::channel('security')->info('Yangi foydalanuvchi', ['user_id' => $event->user->id]);
    }
}
