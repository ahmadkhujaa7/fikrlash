<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bitta maqolaning bir kundagi daromadi (ko‘rishlar × o‘sha paytdagi narx). */
class AuthorEarning extends Model
{
    protected $fillable = ['user_id', 'post_id', 'date', 'views', 'amount'];

    protected function casts(): array
    {
        return ['date' => 'date', 'views' => 'integer', 'amount' => 'decimal:2'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class)->withTrashed();
    }
}
