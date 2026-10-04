<?php

namespace App\Models;

use App\Services\Security\LoginTracker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Laravel'ning "sessions" jadvali (SESSION_DRIVER=database) — faqat o‘qish va tugatish uchun.
 * Har so‘rovda last_activity yangilanadi, shuning uchun "hozir onlayn" aniq ko‘rinadi.
 */
class UserSession extends Model
{
    public const ONLINE_MINUTES = 5;

    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'sessions';

    protected $keyType = 'string';

    protected $hidden = ['payload'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function lastActivityAt(): Carbon
    {
        return Carbon::createFromTimestamp($this->last_activity, config('app.timezone'));
    }

    public function isOnline(): bool
    {
        return $this->last_activity >= now()->subMinutes(self::ONLINE_MINUTES)->timestamp;
    }

    public function device(): string
    {
        return LoginTracker::describeDevice($this->user_agent);
    }

    public function scopeOnline($query)
    {
        return $query->where('last_activity', '>=', now()->subMinutes(self::ONLINE_MINUTES)->timestamp);
    }
}
