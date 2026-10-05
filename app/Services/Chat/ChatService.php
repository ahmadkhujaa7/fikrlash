<?php

namespace App\Services\Chat;

use App\Enums\MessagePrivacy;
use App\Exceptions\ChatException;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Shaxsiy xabarlar: suhbat ochish, yuborish, tahrirlash, o‘chirish, reaksiya, o‘qildi va "yozmoqda".
 *
 * Kim kimga yoza oladi:
 *  - ikkalasi ham faol bo‘lishi kerak (bloklangan/o‘chirilgan akkauntga yozilmaydi);
 *  - qabul qiluvchi suhbatni bloklagan bo‘lsa — yo‘q;
 *  - qabul qiluvchining sozlamasi: hamma / faqat u obuna bo‘lganlar / hech kim.
 *    U ilgari o‘zi yozgan bo‘lsa — javob berish har doim mumkin.
 */
class ChatService
{
    public function __construct(private VoiceStore $voices) {}

    /** Ikki kishi o‘rtasidagi suhbat (bo‘lmasa yaratiladi). */
    public function between(User $me, User $other): Conversation
    {
        $key = Conversation::pairKey($me->id, $other->id);

        $existing = Conversation::query()->where('pair_key', $key)->first();
        if ($existing) {
            return $existing;
        }

        $daily = (int) config('fikrlash.chat.daily_new_conversations');
        if (! RateLimiter::attempt('chat:new:'.$me->id, $daily, fn () => true, 86_400)) {
            throw new ChatException('Bugun juda ko‘p yangi suhbat boshladingiz. Ertaga urinib ko‘ring.');
        }

        return DB::transaction(function () use ($key, $me, $other) {
            $conversation = Conversation::query()->firstOrCreate(['pair_key' => $key]);
            foreach ([$me->id, $other->id] as $userId) {
                ConversationParticipant::query()->firstOrCreate(['conversation_id' => $conversation->id, 'user_id' => $userId]);
            }

            return $conversation;
        });
    }

    /** Yozish mumkin bo‘lmasa — sababini qaytaradi (UI'da ko‘rsatiladi). */
    public function cannotMessage(User $from, User $to, ?Conversation $conversation = null): ?string
    {
        if ($from->is($to)) {
            return 'O‘zingizga xabar yozib bo‘lmaydi.';
        }
        if (! $from->canInteract()) {
            return 'Akkauntingiz vaqtincha cheklangan — xabar yuborib bo‘lmaydi.';
        }
        if ($to->trashed() || ! in_array($to->status, User::VISIBLE_STATUSES, true)) {
            return 'Bu foydalanuvchi hozir xabar qabul qila olmaydi.';
        }

        $conversation ??= Conversation::query()->where('pair_key', Conversation::pairKey($from->id, $to->id))->first();
        $theirs = $conversation?->participants()->where('user_id', $to->id)->first();
        if ($theirs?->blocked_at) {
            return 'Bu foydalanuvchi sizdan xabar qabul qilmaydi.';
        }

        // Suhbatdosh ilgari o‘zi yozgan bo‘lsa — javob berish doim mumkin.
        $theyWrote = $conversation && $conversation->messages()->where('user_id', $to->id)->exists();
        if ($theyWrote) {
            return null;
        }

        return match ($to->messages_from ?? MessagePrivacy::Everyone) {
            MessagePrivacy::Nobody => 'Bu foydalanuvchi shaxsiy xabarlarni yopib qo‘ygan.',
            MessagePrivacy::Following => $to->isFollowing($from) ? null : 'Bu foydalanuvchiga faqat u obuna bo‘lgan odamlar yoza oladi.',
            default => null,
        };
    }

    /**
     * @param  array{body?: string|null, voice?: UploadedFile|null, duration?: int|null, waveform?: array|null, reply_to_id?: int|null}  $data
     *
     * @throws ChatException
     */
    public function send(Conversation $conversation, User $sender, array $data): Message
    {
        $recipient = $conversation->otherUser($sender) ?? throw new ChatException('Suhbatdosh topilmadi.');
        if ($reason = $this->cannotMessage($sender, $recipient, $conversation)) {
            throw new ChatException($reason);
        }

        $replyTo = null;
        if (! empty($data['reply_to_id'])) {
            $replyTo = $conversation->messages()->whereKey($data['reply_to_id'])->whereNull('removed_at')->first();
        }

        $voice = ($data['voice'] ?? null) instanceof UploadedFile ? $this->voices->store($data['voice']) : null;

        try {
            $message = DB::transaction(function () use ($conversation, $sender, $data, $voice, $replyTo) {
                $message = $conversation->messages()->create([
                    'user_id' => $sender->id,
                    'reply_to_id' => $replyTo?->id,
                    'type' => $voice ? Message::TYPE_VOICE : Message::TYPE_TEXT,
                    'body' => $voice ? null : trim((string) $data['body']),
                    'voice_path' => $voice['path'] ?? null,
                    'voice_mime' => $voice['mime'] ?? null,
                    'voice_duration' => $voice ? max(1, min((int) ($data['duration'] ?? 1), (int) config('fikrlash.chat.voice_max_seconds'))) : null,
                    'voice_waveform' => $voice ? $this->waveform($data['waveform'] ?? null) : null,
                ]);

                $conversation->forceFill(['last_message_id' => $message->id, 'last_message_at' => $message->created_at])->save();
                // O‘zi yozgan xabarni o‘qigan hisoblanadi.
                $conversation->participants()->where('user_id', $sender->id)->update(['last_read_message_id' => $message->id]);

                return $message;
            });
        } catch (\Throwable $e) {
            $this->voices->delete($voice['path'] ?? null);
            throw $e;
        }

        Cache::forget($this->typingKey($conversation->id, $sender->id));
        $this->forgetUnread($recipient->id);

        return $message;
    }

    /** @throws ChatException */
    public function edit(Message $message, User $user, string $body): Message
    {
        if (! $message->isOwnedBy($user) || $message->isRemoved() || $message->isVoice()) {
            throw new ChatException('Bu xabarni tahrirlab bo‘lmaydi.');
        }

        $window = config('fikrlash.chat.edit_window_hours');
        if ($window !== null && $message->created_at->lt(now()->subHours((int) $window))) {
            throw new ChatException("Xabarni faqat {$window} soat ichida tahrirlash mumkin.");
        }

        $body = trim($body);
        if ($body !== $message->body) {
            $message->forceFill(['body' => $body, 'edited_at' => now()])->save();
        }

        return $message;
    }

    /** O‘chirish: xabar o‘rnida "Xabar o‘chirildi" qoladi, matn va ovoz fayli yo‘q qilinadi. */
    public function remove(Message $message, User $user): Message
    {
        if (! $message->isOwnedBy($user)) {
            throw new ChatException('Faqat o‘zingizning xabaringizni o‘chira olasiz.');
        }
        if ($message->isRemoved()) {
            return $message;
        }

        $path = $message->voice_path;
        DB::transaction(function () use ($message) {
            $message->reactions()->delete();
            $message->forceFill([
                'body' => null, 'voice_path' => null, 'voice_waveform' => null, 'removed_at' => now(),
            ])->save();
        });
        $this->voices->delete($path);
        $this->forgetUnread($message->loadMissing('conversation')->conversation->otherUser($user)?->id);

        return $message;
    }

    /** Reaksiya: bir xil emoji qayta bosilsa — olib tashlanadi, boshqasi — almashtiriladi. */
    public function react(Message $message, User $user, string $emoji): Message
    {
        if ($message->isRemoved()) {
            throw new ChatException('O‘chirilgan xabarga reaksiya bildirib bo‘lmaydi.');
        }
        if (! in_array($emoji, config('fikrlash.chat.reactions'), true)) {
            throw new ChatException('Bunday reaksiya yo‘q.');
        }

        DB::transaction(function () use ($message, $user, $emoji) {
            $current = MessageReaction::query()->where(['message_id' => $message->id, 'user_id' => $user->id])->lockForUpdate()->first();

            if ($current?->emoji === $emoji) {
                $current->delete();
            } elseif ($current) {
                $current->update(['emoji' => $emoji]);
            } else {
                MessageReaction::query()->create(['message_id' => $message->id, 'user_id' => $user->id, 'emoji' => $emoji]);
            }

            // Suhbatdoshning ekranida ham yangilansin (poll updated_at bo‘yicha o‘zgarishlarni oladi).
            $message->touch();
        });

        return $message;
    }

    public function markRead(Conversation $conversation, User $user, int $upTo): void
    {
        $latest = (int) $conversation->messages()->where('id', '<=', $upTo)->max('id');
        if ($latest > 0) {
            $conversation->participants()->where('user_id', $user->id)
                ->where('last_read_message_id', '<', $latest)
                ->update(['last_read_message_id' => $latest]);
            $this->forgetUnread($user->id);
        }
    }

    public function setBlocked(Conversation $conversation, User $user, bool $blocked): void
    {
        $conversation->participants()->where('user_id', $user->id)->update(['blocked_at' => $blocked ? now() : null]);
    }

    /** Suhbatni men uchun tozalash: hozirgi xabarlar ko‘rinmaydi, suhbat ro‘yxatdan yo‘qoladi. */
    public function clear(Conversation $conversation, User $user): void
    {
        $last = (int) $conversation->messages()->max('id');
        $conversation->participants()->where('user_id', $user->id)
            ->update(['cleared_message_id' => $last, 'last_read_message_id' => $last]);
        $this->forgetUnread($user->id);
    }

    public function typing(Conversation $conversation, User $user): void
    {
        Cache::put($this->typingKey($conversation->id, $user->id), true, now()->addSeconds(6));
    }

    public function isTyping(Conversation $conversation, User $user): bool
    {
        return (bool) Cache::get($this->typingKey($conversation->id, $user->id));
    }

    /** O‘qilmagan xabarlar soni (barcha suhbatlar bo‘yicha) — menyudagi belgi uchun. */
    public function unreadCount(User $user): int
    {
        return (int) Cache::remember($this->unreadKey($user->id), now()->addMinutes(5), fn () => DB::table('messages')
            ->join('conversation_participants as p', function ($join) use ($user) {
                $join->on('p.conversation_id', '=', 'messages.conversation_id')->where('p.user_id', '=', $user->id);
            })
            ->where('messages.user_id', '!=', $user->id)
            ->whereNull('messages.removed_at')
            ->whereColumn('messages.id', '>', 'p.last_read_message_id')
            ->whereColumn('messages.id', '>', 'p.cleared_message_id')
            ->count());
    }

    private function forgetUnread(?int $userId): void
    {
        if ($userId) {
            Cache::forget($this->unreadKey($userId));
        }
    }

    /** To‘lqin shakli: 0–100 oralig‘idagi 64 tagacha butun son (mijozdan kelgani tozalanadi). */
    private function waveform(mixed $raw): ?array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (! is_array($raw) || $raw === []) {
            return null;
        }

        return array_map(fn ($v) => max(0, min(100, (int) $v)), array_slice(array_values($raw), 0, 64));
    }

    private function typingKey(int $conversationId, int $userId): string
    {
        return "chat:typing:{$conversationId}:{$userId}";
    }

    private function unreadKey(int $userId): string
    {
        return "chat:unread:{$userId}";
    }
}
