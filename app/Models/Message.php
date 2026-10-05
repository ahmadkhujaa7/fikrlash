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

    protected $fillable = [
        'conversation_id', 'user_id', 'reply_to_id', 'type', 'body',
        'voice_path', 'voice_mime', 'voice_duration', 'voice_waveform',
    ];

    protected $attributes = ['type' => 'text'];

    protected function casts(): array
    {
        return [
            'voice_waveform' => 'array',
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

        return mb_strimwidth(preg_replace('/\s+/u', ' ', (string) $this->body) ?? '', 0, $length, '…');
    }

    public static function duration(int $seconds): string
    {
        return intdiv($seconds, 60).':'.str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
    }
}
