<?php

namespace App\Services\Feed;

use App\Models\Post;

/**
 * Rule-based "hot" ball:
 *   engagement = 1 + likes·w + comments·w + saves·w + views·w
 *   hot        = engagement / (age_hours + 2) ^ gravity
 * Keyinchalik ML model shu interfeys o‘rniga qo‘yilishi mumkin.
 */
class ScoreCalculator
{
    public function engagement(Post $post): float
    {
        $w = config('fikrlash.feed.weights');

        return 1
            + $post->likes_count * $w['like']
            + $post->comments_count * $w['comment']
            + $post->saves_count * $w['save']
            + $post->views_count * $w['view'];
    }

    public function hot(Post $post): float
    {
        $publishedAt = $post->published_at ?? now();
        $ageHours = max(0, $publishedAt->diffInMinutes(now()) / 60);

        return round($this->engagement($post) / (($ageHours + 2) ** config('fikrlash.feed.weights.gravity')), 6);
    }
}
