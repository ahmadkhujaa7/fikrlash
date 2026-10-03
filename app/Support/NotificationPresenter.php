<?php

namespace App\Support;

use App\Enums\NotificationType;
use App\Models\Comment;
use App\Models\Notification;
use App\Models\Post;
use App\Models\User;

/** Bildirishnomani o‘qiladigan matn va havolaga aylantiradi (web va API uchun bir xil). */
final class NotificationPresenter
{
    /** @return array{text: string, url: ?string, excerpt: ?string} */
    public static function present(Notification $n): array
    {
        $subject = $n->subject;
        $data = $n->data ?? [];

        $url = match (true) {
            $subject instanceof Post && ! $subject->trashed() => route('posts.show', $subject),
            $subject instanceof Comment && ! $subject->trashed() => route('posts.show', $subject->post_id).'#comment-'.$subject->id,
            $subject instanceof User => route('profile.show', $subject->username),
            default => null,
        };

        $text = match ($n->type) {
            NotificationType::Followed => 'sizga obuna bo‘ldi',
            NotificationType::PostLiked => 'fikringizni yoqtirdi',
            NotificationType::PostCommented => 'fikringizga izoh qoldirdi',
            NotificationType::CommentReplied => 'izohingizga javob berdi',
            NotificationType::Mentioned => 'sizni eslatib o‘tdi',
            NotificationType::PostModerated => match ($data['status'] ?? null) {
                'pending_moderation' => 'Postingiz moderator tekshiruviga yuborildi. Natija haqida xabar beramiz.',
                'hidden' => 'Postingiz qoidalarga mos kelmagani uchun yashirildi.'.(! empty($data['reason']) ? ' Sabab: '.$data['reason'] : ''),
                default => 'Postingiz holati o‘zgardi.',
            },
            NotificationType::System => (string) ($data['message'] ?? 'Tizim xabari'),
        };

        $excerpt = $data['excerpt'] ?? ($subject instanceof Post ? $subject->excerpt(120) : null);

        return ['text' => $text, 'url' => $url, 'excerpt' => $excerpt];
    }
}
