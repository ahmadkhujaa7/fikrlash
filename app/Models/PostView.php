<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostView extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $fillable = ['user_id', 'post_id', 'read_seconds', 'first_viewed_at', 'last_viewed_at'];

    protected function casts(): array
    {
        return ['first_viewed_at' => 'datetime', 'last_viewed_at' => 'datetime'];
    }
}
