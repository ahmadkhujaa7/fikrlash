<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kampaniya havolasining bitta bosilishi (botlar yozilmaydi). */
class MarketingVisit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['marketing_link_id', 'visitor', 'is_unique', 'user_id', 'device', 'os', 'browser', 'app', 'referrer'];

    protected function casts(): array
    {
        return ['is_unique' => 'boolean', 'created_at' => 'datetime'];
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(MarketingLink::class, 'marketing_link_id')->withTrashed();
    }
}
