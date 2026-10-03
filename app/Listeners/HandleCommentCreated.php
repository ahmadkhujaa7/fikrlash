<?php

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\CommentCreated;
use App\Models\User;
use App\Services\Social\NotificationService;
use App\Support\ContentFormatter;
use Illuminate\Contracts\Queue\ShouldQueue;

/** Izoh: post muallifiga, javob berilgan odamga va eslatilganlarga xabar (har kimga bittadan). */
class HandleCommentCreated implements ShouldQueue
{
    public bool $deleteWhenMissingModels = true;

    public function __construct(private NotificationService $notifications) {}

    public function handle(CommentCreated $event): void
    {
        $comment = $event->comment->loadMissing(['post.user', 'user', 'replyToUser']);
        $actor = $comment->user;
        $notified = [$actor->id => true];
        $data = ['excerpt' => mb_strimwidth($comment->content, 0, 120, '…'), 'post_id' => $comment->post_id];

        if ($comment->replyToUser && ! isset($notified[$comment->replyToUser->id])) {
            $this->notifications->notify($comment->replyToUser, NotificationType::CommentReplied, $actor, $comment, $data);
            $notified[$comment->replyToUser->id] = true;
        }

        $postAuthor = $comment->post->user;
        if ($postAuthor && ! isset($notified[$postAuthor->id])) {
            $this->notifications->notify($postAuthor, NotificationType::PostCommented, $actor, $comment, $data);
            $notified[$postAuthor->id] = true;
        }

        $usernames = ContentFormatter::mentions($comment->content);
        if ($usernames !== []) {
            User::query()->visible()->whereIn('username', $usernames)->whereNotIn('id', array_keys($notified))->get()
                ->each(fn (User $user) => $this->notifications->notify($user, NotificationType::Mentioned, $actor, $comment, $data));
        }
    }
}
