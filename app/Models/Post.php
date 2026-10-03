<?php

namespace App\Models;

use App\Enums\PostStatus;
use App\Enums\PostVisibility;
use App\Support\TextNormalizer;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $content
 * @property PostStatus $status
 * @property PostVisibility $visibility
 */
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['content', 'category_id', 'image_path', 'status', 'visibility', 'published_at'];

    protected $attributes = [
        'status' => 'published',
        'visibility' => 'public',
        'likes_count' => 0,
        'comments_count' => 0,
        'views_count' => 0,
        'saves_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'visibility' => PostVisibility::class,
            'published_at' => 'datetime',
            'edited_at' => 'datetime',
            'ai_analyzed_at' => 'datetime',
            'ai_flagged' => 'boolean',
            'ai_score' => 'integer',
            'likes_count' => 'integer',
            'comments_count' => 'integer',
            'views_count' => 'integer',
            'saves_count' => 'integer',
            'score' => 'float',
        ];
    }

    protected static function booted(): void
    {
        // Qidiruv matni va content hash har saqlashda yangilanadi.
        static::saving(function (Post $post) {
            if ($post->isDirty('content')) {
                $post->search_text = TextNormalizer::forSearch($post->content);
                $post->content_hash = hash('sha256', TextNormalizer::forHash($post->content));
            }
        });
    }

    // ---- Relationships ----

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(PostLike::class);
    }

    public function saves(): HasMany
    {
        return $this->hasMany(SavedPost::class);
    }

    public function aiAnalyses(): HasMany
    {
        return $this->hasMany(PostAiAnalysis::class);
    }

    public function latestAiAnalysis(): HasOne
    {
        return $this->hasOne(PostAiAnalysis::class)->latestOfMany();
    }

    // ---- Scopes ----

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('posts.status', PostStatus::Published);
    }

    /** Muallifi faol (bloklanmagan, o‘chirilmagan) postlar. */
    public function scopeFromVisibleAuthors(Builder $query): Builder
    {
        return $query->whereHas('user', fn (Builder $q) => $q->visible());
    }

    /**
     * Ushbu foydalanuvchi ko‘ra oladigan postlar:
     * public, yoki "followers" bo‘lsa — obunachi yoki muallifning o‘zi.
     */
    public function scopeVisibleTo(Builder $query, ?User $viewer): Builder
    {
        return $query->where(function (Builder $q) use ($viewer) {
            $q->where('posts.visibility', PostVisibility::Public);

            if ($viewer) {
                $q->orWhere('posts.user_id', $viewer->id)
                    ->orWhere(fn (Builder $f) => $f
                        ->where('posts.visibility', PostVisibility::Followers)
                        ->whereIn('posts.user_id', Follow::query()->select('following_id')->where('follower_id', $viewer->id)));
            }
        });
    }

    /** Feed uchun standart filtr: chop etilgan + ko‘rinadigan + faol muallif. */
    public function scopeForFeed(Builder $query, ?User $viewer): Builder
    {
        return $query->published()->visibleTo($viewer)->fromVisibleAuthors();
    }

    // ---- Helpers ----

    public function isPublished(): bool
    {
        return $this->status === PostStatus::Published;
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $this->user_id === $user->id;
    }

    public function imageUrl(): ?string
    {
        return $this->image_path
            ? Storage::disk(config('fikrlash.media.disk'))->url($this->image_path)
            : null;
    }

    public function url(): string
    {
        return route('posts.show', $this);
    }

    public function excerpt(int $length = 160): string
    {
        return Str::limit(preg_replace('/\s+/u', ' ', $this->content), $length);
    }

    public function isLong(): bool
    {
        return mb_strlen($this->content) > config('fikrlash.posts.card_preview_length');
    }
}
