<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Maqola ichiga yuklangan rasm. Post saqlanmaguncha `post_id` bo‘sh turadi;
 * biriktirilmagan eski rasmlar `fikrlash:prune` orqali tozalanadi.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $post_id
 * @property string $path
 */
class MediaUpload extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'post_id', 'path', 'width', 'height'];

    protected function casts(): array
    {
        return ['width' => 'integer', 'height' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function scopeUnattached(Builder $query): Builder
    {
        return $query->whereNull('post_id');
    }

    public function url(): string
    {
        return Storage::disk(config('fikrlash.media.disk'))->url($this->path);
    }
}
