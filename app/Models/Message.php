<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Shaxsiy xabar: matn yoki ovozli. O‘chirilgan xabar joyida qoladi ("Xabar o‘chirildi"),
 * lekin matni va ovoz fayli darhol yo‘q qilinadi.
 *
 * @property int $id
 * @property int $conversation_id
 * @property int $user_id
 * @property string $type text|voice
 * @property string|null $body
 */
class Message extends Model
{
    public const TYPE_TEXT = 'text';

    public const TYPE_VOICE = 'voice';

    public const TYPE_MEDIA = 'media';

    public const TYPE_LOCATION = 'location';

    public const TYPE_POST = 'post';

    protected $fillable = [
        'conversation_id', 'user_id', 'reply_to_id', 'post_id', 'type', 'body',
        'voice_path', 'voice_mime', 'voice_duration', 'voice_waveform', 'meta',
    ];

    protected $attributes = ['type' => 'text'];

    protected function casts(): array
    {
        return [
            'voice_waveform' => 'array',
            'meta' => 'array',
            'voice_duration' => 'integer',
            'edited_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class)->orderBy('position');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class)->withTrashed();
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    public function isRemoved(): bool
    {
        return $this->removed_at !== null;
    }

    public function isVoice(): bool
    {
        return $this->type === self::TYPE_VOICE;
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $this->user_id === $user->id;
    }

    /** Ro‘yxat va javob ko‘rinishi uchun qisqa matn. */
    public function snippet(int $length = 80): string
    {
        if ($this->isRemoved()) {
            return 'Xabar o‘chirildi';
        }
        if ($this->isVoice()) {
            return 'Ovozli xabar · '.self::duration((int) $this->voice_duration);
        }
        $caption = trim((string) $this->body);
        $prefix = match ($this->type) {
            self::TYPE_MEDIA => $this->mediaLabel(),
            self::TYPE_LOCATION => 'Joylashuv',
            self::TYPE_POST => 'Ulashilgan post',
            default => null,
        };
        if ($prefix !== null) {
            return $caption !== '' ? $prefix.': '.mb_strimwidth(preg_replace('/\s+/u', ' ', $caption) ?? '', 0, $length, '…') : $prefix;
        }

        return mb_strimwidth(preg_replace('/\s+/u', ' ', (string) $this->body) ?? '', 0, $length, '…');
    }

    /** "Rasm", "3 ta rasm", "Video", "2 ta rasm, video" — meta'dagi turlar bo‘yicha (attachment yuklamasdan). */
    public function mediaLabel(): string
    {
        $kinds = array_count_values($this->meta['kinds'] ?? ['image']);
        $parts = [];
        foreach (['image' => 'rasm', 'video' => 'video'] as $kind => $word) {
            $n = $kinds[$kind] ?? 0;
            if ($n > 0) {
                $parts[] = $n > 1 ? "{$n} ta {$word}" : $word;
            }
        }

        return ucfirst(implode(', ', $parts) ?: 'rasm');
    }

    public static function duration(int $seconds): string
    {
        return intdiv($seconds, 60).':'.str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
    }
}
