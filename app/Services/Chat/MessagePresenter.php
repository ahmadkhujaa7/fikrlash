<?php

namespace App\Services\Chat;

use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use App\Support\ContentFormatter;
use App\Support\Time;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Xabarni brauzer uchun JSON ko‘rinishiga keltiradi. Matn faqat serverda (ContentFormatter orqali)
 * HTML'ga aylanadi — xom HTML hech qachon chiqmaydi. Tahrirlash uchun xom matn faqat muallifga beriladi.
 */
class MessagePresenter
{
    /** @param  Collection<int, Message>|iterable<Message>  $messages */
    public function many(iterable $messages, User $viewer): array
    {
        return collect($messages)->map(fn (Message $m) => $this->one($m, $viewer))->values()->all();
    }

    public function one(Message $message, User $viewer): array
    {
        $mine = $message->isOwnedBy($viewer);
        $removed = $message->isRemoved();

        return [
            'id' => $message->id,
            'mine' => $mine,
            'type' => $message->type,
            'removed' => $removed,
            'html' => ! $removed && ! $message->isVoice() && $message->body !== null ? ContentFormatter::toHtml((string) $message->body, rich: true) : null,
            'body' => $mine && ! $removed && ! $message->isVoice() ? $message->body : null,
            'media' => ! $removed && $message->type === Message::TYPE_MEDIA ? $this->media($message) : null,
            'location' => ! $removed && $message->type === Message::TYPE_LOCATION ? $this->location($message) : null,
            'post' => ! $removed && $message->type === Message::TYPE_POST ? $this->post($message, $viewer) : null,
            'voice' => ! $removed && $message->isVoice() ? [
                'url' => route('messages.voice', [$message->conversation_id, $message->id]),
                'duration' => (int) $message->voice_duration,
                'waveform' => $message->voice_waveform ?? [],
            ] : null,
            'reply' => $message->reply_to_id && $message->relationLoaded('replyTo') && $message->replyTo ? [
                'id' => $message->replyTo->id,
                'name' => $message->replyTo->isOwnedBy($viewer) ? 'Siz' : $message->replyTo->user?->name,
                'text' => $message->replyTo->snippet(90),
            ] : null,
            'reactions' => $this->reactions($message, $viewer),
            'edited' => $message->edited_at !== null && ! $removed,
            'at' => $message->created_at->toIso8601String(),
            'time' => $message->created_at->format('H:i'),
            'day' => $message->created_at->toDateString(),
            'day_label' => self::dayLabel($message->created_at),
        ];
    }

    /** @return list<array{id: int, kind: string, url: string, poster: ?string, w: ?int, h: ?int, duration: ?int}> */
    private function media(Message $message): array
    {
        if (! $message->relationLoaded('attachments')) {
            return [];
        }

        return $message->attachments->map(function (MessageAttachment $a) use ($message) {
            $url = route('messages.media', [$message->conversation_id, $message->id, $a->id]);

            return [
                'id' => $a->id,
                'kind' => $a->kind,
                'url' => $url,
                'poster' => $a->poster_path ? $url.'?poster=1' : null,
                'w' => $a->width,
                'h' => $a->height,
                'duration' => $a->duration,
            ];
        })->values()->all();
    }

    /** Joylashuv — kalitlar tartibi barqaror (MySQL JSON ustuni kalitlarni o‘zicha tartiblaydi). */
    private function location(Message $message): ?array
    {
        $meta = $message->meta ?? [];
        if (! isset($meta['lat'], $meta['lng'])) {
            return null;
        }

        return [
            'lat' => (float) $meta['lat'],
            'lng' => (float) $meta['lng'],
            'acc' => isset($meta['acc']) ? (int) $meta['acc'] : null,
        ];
    }

    /** Ulashilgan post: suhbatdosh uni ko‘ra olmasa (yopiq yoki o‘chirilgan) — "mavjud emas". */
    private function post(Message $message, User $viewer): array
    {
        $post = $message->relationLoaded('post') ? $message->post : null;
        if (! $post || ! Gate::forUser($viewer)->allows('view', $post)) {
            return ['available' => false];
        }

        $author = $post->user;

        return [
            'available' => true,
            'url' => $post->url(),
            'title' => $post->isArticle() ? $post->title : null,
            'text' => $post->summary(180),
            'image' => $post->imageUrl(),
            'author' => [
                'name' => $author?->name,
                'username' => $author?->username,
                'avatar' => $author?->avatarUrl(),
                'initials' => $author?->initials(),
                'tone' => $author?->tone(),
                'verified' => (bool) $author?->isVerified(),
            ],
        ];
    }

    /** @return list<array{emoji: string, count: int, mine: bool}> */
    private function reactions(Message $message, User $viewer): array
    {
        if (! $message->relationLoaded('reactions') || $message->reactions->isEmpty()) {
            return [];
        }

        $order = array_flip(config('fikrlash.chat.reactions'));

        return $message->reactions->groupBy('emoji')
            ->map(fn ($group, $emoji) => [
                'emoji' => (string) $emoji,
                'count' => $group->count(),
                'mine' => $group->contains('user_id', $viewer->id),
            ])
            ->sortBy(fn ($r) => $order[$r['emoji']] ?? 99)
            ->values()->all();
    }

    public static function dayLabel(CarbonInterface $time): string
    {
        return match (true) {
            $time->isToday() => 'Bugun',
            $time->isYesterday() => 'Kecha',
            default => Time::date($time),
        };
    }

    /** Suhbatdoshning holati: "onlayn" yoki "5 daq oldin". */
    public static function presence(?User $user): array
    {
        $seen = $user?->last_active_at;
        $online = $seen !== null && $seen->gt(now()->subMinutes(6));

        return [
            'online' => $online,
            'label' => $online ? 'onlayn' : ($seen ? 'oxirgi marta '.Time::short($seen).($seen->gt(now()->subWeek()) ? ' oldin' : '') : ''),
        ];
    }
}
