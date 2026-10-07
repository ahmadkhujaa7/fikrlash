<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Chatdagi rasm yoki video. Fayllar ommaviy emas — faqat suhbat ishtirokchilariga
 * himoyalangan manzil orqali beriladi (MessageController@media).
 *
 * @property int $id
 * @property string $kind image|video
 * @property string $path
 */
class MessageAttachment extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['message_id', 'kind', 'path', 'mime', 'size', 'width', 'height', 'duration', 'poster_path', 'position'];

    protected function casts(): array
    {
        return ['size' => 'integer', 'width' => 'integer', 'height' => 'integer', 'duration' => 'integer', 'position' => 'integer'];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function isVideo(): bool
    {
        return $this->kind === 'video';
    }
}
