<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\Feed\ScoreCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshPostScores extends Command
{
    protected $signature = 'posts:refresh-scores {--days=30 : Necha kunlik postlar qayta hisoblansin}';

    protected $description = 'Trending/"Siz uchun" uchun postlarning hot ballarini yangilaydi';

    public function handle(ScoreCalculator $scores): int
    {
        $count = 0;
        Post::query()->published()
            ->where('published_at', '>=', now()->subDays((int) $this->option('days')))
            ->select(['id', 'likes_count', 'comments_count', 'saves_count', 'views_count', 'published_at'])
            ->chunkById(500, function ($posts) use ($scores, &$count) {
                $cases = [];
                $bindings = [];
                foreach ($posts as $post) {
                    $cases[] = 'WHEN ? THEN ?';
                    array_push($bindings, $post->id, $scores->hot($post));
                }
                $ids = $posts->pluck('id')->implode(',');
                DB::update('UPDATE posts SET score = CASE id '.implode(' ', $cases)." ELSE score END WHERE id IN ({$ids})", $bindings);
                $count += $posts->count();
            });

        $this->info("Yangilandi: {$count} ta post");

        return self::SUCCESS;
    }
}
