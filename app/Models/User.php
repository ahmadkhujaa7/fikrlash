<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\PostStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $username
 * @property string $phone
 * @property UserRole $role
 * @property UserStatus $status
 */
class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, SoftDeletes;

    /** Tashqi ko‘rinishi mumkin bo‘lgan statuslar (kontenti feed'da chiqadi). */
    public const VISIBLE_STATUSES = [UserStatus::Active, UserStatus::Suspended];

    protected $fillable = [
        'name', 'username', 'phone', 'email', 'password', 'bio', 'gender', 'birth_date', 'avatar_path',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = [
        'role' => 'user',
        'status' => 'active',
        'followers_count' => 0,
        'following_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'last_active_at' => 'datetime',
            'suspended_until' => 'datetime',
            'birth_date' => 'date',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'gender' => Gender::class,
            'followers_count' => 'integer',
            'following_count' => 'integer',
        ];
    }

    protected function setUsernameAttribute(string $value): void
    {
        $this->attributes['username'] = mb_strtolower(trim($value));
    }

    // ---- Relationships ----

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(PostLike::class);
    }

    public function savedPosts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'saved_posts')->withPivot('created_at');
    }

    /** Menga obuna bo‘lganlar. */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'following_id', 'follower_id')->withPivot('created_at');
    }

    /** Men obuna bo‘lganlar. */
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id')->withPivot('created_at');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function interests(): HasMany
    {
        return $this->hasMany(UserInterest::class);
    }

    // ---- Scopes ----

    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereIn('status', self::VISIBLE_STATUSES);
    }

    // ---- Helpers ----

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /** Suspend muddati o‘tgan bo‘lsa, foydalanuvchi faol hisoblanadi. */
    public function isSuspended(): bool
    {
        return $this->status === UserStatus::Suspended
            && ($this->suspended_until === null || $this->suspended_until->isFuture());
    }

    public function isBlocked(): bool
    {
        return $this->status === UserStatus::Blocked;
    }

    /** Tizimga kira oladimi (login)? */
    public function canSignIn(): bool
    {
        return ! $this->isBlocked() && ! $this->trashed();
    }

    /** Kontent yarata oladimi (post, comment, like...)? */
    public function canInteract(): bool
    {
        return $this->canSignIn() && ! $this->isSuspended() && $this->status !== UserStatus::Deactivated;
    }

    public function hasVerifiedPhone(): bool
    {
        return $this->phone_verified_at !== null;
    }

    public function isFollowing(User $user): bool
    {
        return $this->following()->whereKey($user->getKey())->exists();
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path
            ? Storage::disk(config('fikrlash.media.disk'))->url($this->avatar_path)
            : null;
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr(trim($this->name) ?: $this->username, 0, 1));
    }

    public function profileUrl(): string
    {
        return route('profile.show', $this->username);
    }

    public function publishedPostsCount(): int
    {
        return $this->posts()->where('status', PostStatus::Published)->count();
    }

    // ---- Filament ----

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin() && $this->canInteract();
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatarUrl();
    }
}
