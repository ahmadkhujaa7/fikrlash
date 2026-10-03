<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\PostCreated;
use App\Jobs\AnalyzePostJob;
use App\Models\User;
use App\Services\Social\NotificationService;
use App\Support\ContentFormatter;
use Illuminate\Contracts\Queue\ShouldQueue;

/** Yangi post: AI tahlilni navbatga qo‘yish va eslatilgan (@mention) foydalanuvchilarga xabar. */
class HandlePostCreated implements ShouldQueue
{
    public bool $deleteWhenMissingModels = true;

    public function __construct(private NotificationService $notifications) {}

    public function handle(PostCreated $event): void
    {
        $post = $event->post;

        // AI xatosi (hatto sync queue bilan ham) postni chop etishga ta'sir qilmaydi.
        AnalyzePostJob::dispatchSafely($post->id);

        $usernames = ContentFormatter::mentions($post->content);
        if ($usernames === []) {
            return;
        }

        User::query()->visible()->whereIn('username', $usernames)->get()
            ->each(fn (User $user) => $this->notifications->notify($user, NotificationType::Mentioned, $post->user, $post));
    }
}
