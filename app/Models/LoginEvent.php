<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginEvent extends Model
{
    public const UPDATED_AT = null;

    public const LABELS = [
        'login' => 'Kirdi',
        'failed' => 'Noto‘g‘ri parol',
        'blocked' => 'Bloklangan akkaunt',
        'logout' => 'Chiqdi',
        'register' => 'Ro‘yxatdan o‘tdi',
        'impersonate' => 'Admin uning nomidan kirdi',
        'impersonate_end' => 'Admin qaytdi',
        'password_reset' => 'Parol tiklandi',
        'sessions_revoked' => 'Barcha qurilmalardan chiqarildi',
    ];

    public const COLORS = [
        'login' => 'success',
        'register' => 'primary',
        'failed' => 'danger',
        'blocked' => 'danger',
        'logout' => 'gray',
        'impersonate' => 'warning',
        'impersonate_end' => 'gray',
        'password_reset' => 'info',
        'sessions_revoked' => 'warning',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }

    public function label(): string
    {
        return self::LABELS[$this->event] ?? $this->event;
    }
}
