<?php

namespace App\Services\Chat;

use App\Models\Message;
use App\Models\User;
use App\Support\ContentFormatter;
use App\Support\Time;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

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
            'html' => ! $removed && ! $message->isVoice() ? ContentFormatter::toHtml((string) $message->body, rich: true) : null,
            'body' => $mine && ! $removed && ! $message->isVoice() ? $message->body : null,
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
