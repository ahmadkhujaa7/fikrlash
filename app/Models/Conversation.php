<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ikki kishi o‘rtasidagi suhbat. Har bir juftlik uchun bitta (pair_key unikal).
 *
 * @property int $id
 * @property string $pair_key
 * @property int|null $last_message_id
 */
class Conversation extends Model
{
    protected $fillable = ['pair_key', 'last_message_id', 'last_message_at'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public static function pairKey(int $a, int $b): string
    {
        return min($a, $b).':'.max($a, $b);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')->withTrashed();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->whereHas('participants', fn (Builder $q) => $q->where('user_id', $user->id));
    }

    public function participantFor(User $user): ?ConversationParticipant
    {
        return $this->relationLoaded('participants')
            ? $this->participants->firstWhere('user_id', $user->id)
            : $this->participants()->where('user_id', $user->id)->first();
    }

    /** Suhbatdagi ikkinchi odam. */
    public function otherUser(User $user): ?User
    {
        $this->loadMissing('participants.user');
        $other = $this->participants->firstWhere('user_id', '!=', $user->id);

        return $other?->user;
    }

    public function hasParticipant(User $user): bool
    {
        return $this->participants()->where('user_id', $user->id)->exists();
    }
}
