<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kim e'lonni bildirishnomalar sahifasida ko‘rdi (seen_at) va kim bosib ochdi (opened_at). */
class AnnouncementReceipt extends Model
{
    public $timestamps = false;

    protected $fillable = ['announcement_id', 'user_id', 'seen_at', 'opened_at'];

    protected function casts(): array
    {
        return ['seen_at' => 'datetime', 'opened_at' => 'datetime'];
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
