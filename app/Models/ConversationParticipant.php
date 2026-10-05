<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $conversation_id
 * @property int $user_id
 * @property int $last_read_message_id
 * @property int $cleared_message_id
 */
class ConversationParticipant extends Model
{
    protected $fillable = ['conversation_id', 'user_id', 'last_read_message_id', 'cleared_message_id', 'blocked_at'];

    protected $attributes = [
        'last_read_message_id' => 0,
        'cleared_message_id' => 0,
    ];

    protected function casts(): array
    {
        return [
            'last_read_message_id' => 'integer',
            'cleared_message_id' => 'integer',
            'blocked_at' => 'datetime',
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
}
