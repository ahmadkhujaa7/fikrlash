<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mobil ilova qurilmasining push tokeni (FCM).
 *
 * @property int $id
 * @property int $user_id
 * @property string $token
 * @property string $platform
 */
class DeviceToken extends Model
{
    public const PLATFORMS = ['android', 'ios'];

    protected $fillable = ['user_id', 'token', 'platform', 'app_version', 'last_seen_at'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
