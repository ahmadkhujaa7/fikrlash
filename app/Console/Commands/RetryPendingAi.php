<?php

namespace App\Console\Commands;

use App\Jobs\AnalyzePostJob;
use App\Models\Post;
use Illuminate\Console\Command;

class RetryPendingAi extends Command
{
    protected $signature = 'ai:retry-pending {--limit=100}';

    protected $description = 'AI tahlil qilinmagan postlarni qayta navbatga qo‘yadi';

    public function handle(): int
    {
        if (! config('ai.enabled') || config('ai.provider') === 'null') {
            return self::SUCCESS;
        }

        $ids = Post::query()->published()
            ->whereNull('ai_analyzed_at')
            ->whereBetween('published_at', [now()->subDays(2), now()->subMinutes(10)])
            ->orderBy('id')->limit((int) $this->option('limit'))->pluck('id');

        $ids->each(fn (int $id) => AnalyzePostJob::dispatchSafely($id));
        $this->info("Navbatga qo‘yildi: {$ids->count()} ta post");

        return self::SUCCESS;
    }
}
