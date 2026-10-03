<?php

namespace App\Models;

use App\Enums\CommentStatus;
use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['content', 'parent_id', 'reply_to_user_id', 'status'];

    protected $attributes = ['status' => 'published', 'likes_count' => 0, 'replies_count' => 0];

    protected function casts(): array
    {
        return [
            'status' => CommentStatus::class,
            'likes_count' => 'integer',
            'replies_count' => 'integer',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    public function replyToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reply_to_user_id');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(CommentLike::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('comments.status', CommentStatus::Published);
    }

    public function scopeFromVisibleAuthors(Builder $query): Builder
    {
        return $query->whereHas('user', fn (Builder $q) => $q->visible());
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $this->user_id === $user->id;
    }

    public function url(): string
    {
        return route('posts.show', $this->post_id).'#comment-'.$this->id;
    }
}
