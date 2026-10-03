<?php

namespace App\Filament\Widgets;

use App\Enums\ReportStatus;
use App\Models\Comment;
use App\Models\Post;
use App\Models\PostAiAnalysis;
use App\Models\PostLike;
use App\Models\Report;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Number;

class PlatformStats extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $s = Cache::remember('admin:platform-stats', now()->addMinute(), fn () => [
            'users' => User::query()->count(),
            'users_today' => User::query()->where('created_at', '>=', today())->count(),
            'active_7d' => User::query()->where('last_active_at', '>=', now()->subDays(7))->count(),
            'posts' => Post::query()->count(),
            'posts_today' => Post::query()->where('created_at', '>=', today())->count(),
            'comments' => Comment::query()->count(),
            'likes' => PostLike::query()->count(),
            'views' => (int) Post::query()->sum('views_count'),
            'reports' => Report::query()->where('status', ReportStatus::Pending)->count(),
            'ai_done' => PostAiAnalysis::query()->where('status', PostAiAnalysis::STATUS_COMPLETED)->distinct('post_id')->count('post_id'),
            'spam' => Post::query()->where('ai_flagged', true)->count(),
            'users_series' => $this->series(User::class),
            'posts_series' => $this->series(Post::class),
        ]);

        return [
            Stat::make('Foydalanuvchilar', Number::format($s['users']))
                ->description('+'.$s['users_today'].' bugun')->chart($s['users_series'])->color('primary'),
            Stat::make('Faol (7 kun)', Number::format($s['active_7d']))
                ->description($s['users'] ? round($s['active_7d'] / $s['users'] * 100).'% foydalanuvchilar' : '—'),
            Stat::make('Postlar', Number::format($s['posts']))
                ->description('+'.$s['posts_today'].' bugun')->chart($s['posts_series'])->color('success'),
            Stat::make('Izohlar', Number::format($s['comments'])),
            Stat::make('Like', Number::format($s['likes'])),
            Stat::make('Ko‘rishlar', Number::abbreviate($s['views'])),
            Stat::make('Kutilayotgan shikoyatlar', $s['reports'])->color($s['reports'] > 0 ? 'danger' : 'gray'),
            Stat::make('AI tahlil qilingan', Number::format($s['ai_done']))
                ->description($s['spam'].' ta xavfli/spam belgilangan')->color($s['spam'] > 0 ? 'warning' : 'gray'),
        ];
    }

    /** So‘nggi 14 kunlik kunlik sonlar (sparkline). */
    private function series(string $model): array
    {
        $rows = $model::query()->where('created_at', '>=', today()->subDays(13))
            ->get(['created_at'])->groupBy(fn ($m) => $m->created_at->toDateString())->map->count();

        return collect(range(13, 0))->map(fn ($d) => $rows->get(today()->subDays($d)->toDateString(), 0))->all();
    }
}
