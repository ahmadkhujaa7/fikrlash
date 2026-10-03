<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserInterest extends Model
{
    public const CREATED_AT = null;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $fillable = ['user_id', 'category_id', 'weight', 'followed_at'];

    protected function casts(): array
    {
        return ['weight' => 'float', 'followed_at' => 'datetime'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
