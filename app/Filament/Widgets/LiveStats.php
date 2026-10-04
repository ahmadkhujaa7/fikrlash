<?php

namespace App\Filament\Widgets;

use App\Enums\PostStatus;
use App\Enums\ReportStatus;
use App\Filament\Resources\LoginEvents\LoginEventResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Reports\ReportResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Comment;
use App\Models\LoginEvent;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Models\UserSession;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Number;

/** Jonli ko‘rsatkichlar — har 30 soniyada yangilanadi. */
class LiveStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $s = Cache::remember('admin:live-stats', 20, fn () => [
            'online_users' => UserSession::query()->online()->whereNotNull('user_id')->distinct()->count('user_id'),
            'online_guests' => UserSession::query()->online()->whereNull('user_id')->count(),
            'active_today' => User::query()->where('last_active_at', '>=', today())->count(),
            'users' => User::query()->count(),
            'users_today' => User::query()->where('created_at', '>=', today())->count(),
            'users_week' => User::query()->where('created_at', '>=', today()->subDays(6))->count(),
            'posts_today' => Post::query()->where('created_at', '>=', today())->count(),
            'comments_today' => Comment::query()->where('created_at', '>=', today())->count(),
            'reports' => Report::query()->where('status', ReportStatus::Pending)->count(),
            'queue' => Post::query()->where('status', PostStatus::PendingModeration)->count(),
            'failed' => LoginEvent::query()->whereIn('event', ['failed', 'blocked'])->where('created_at', '>=', now()->subDay())->count(),
            'users_series' => $this->series('users'),
            'posts_series' => $this->series('posts'),
            'logins_series' => $this->loginSeries(),
        ]);

        return [
            Stat::make('Hozir onlayn', Number::format($s['online_users']))
                ->description($s['online_guests'].' ta mehmon ham saytda')->descriptionIcon(Heroicon::Signal)
                ->color('success')->url(UserResource::getUrl('index', ['tab' => 'online'])),
            Stat::make('Bugun faol', Number::format($s['active_today']))
                ->description($s['users'] ? round($s['active_today'] / max(1, $s['users']) * 100).'% barcha foydalanuvchilardan' : '—')
                ->chart($s['logins_series'])->color('primary'),
            Stat::make('Yangi foydalanuvchilar', '+'.$s['users_today'])
                ->description('7 kunda: +'.$s['users_week'].' · jami '.Number::format($s['users']))
                ->chart($s['users_series'])->color('primary')->url(UserResource::getUrl('index', ['tab' => 'today'])),
            Stat::make('Bugungi postlar', Number::format($s['posts_today']))
                ->description($s['comments_today'].' ta izoh')->chart($s['posts_series'])->color('gray'),
            Stat::make('Kutilayotgan shikoyatlar', $s['reports'])
                ->description($s['reports'] ? 'Ko‘rib chiqish kerak' : 'Hammasi ko‘rilgan')
                ->color($s['reports'] ? 'danger' : 'gray')->url(ReportResource::getUrl('index')),
            Stat::make('Moderatsiya navbati', $s['queue'])
                ->description('Tekshiruvni kutayotgan postlar')
                ->color($s['queue'] ? 'warning' : 'gray')->url(PostResource::getUrl('index')),
            Stat::make('Noto‘g‘ri parol (24 soat)', $s['failed'])
                ->description($s['failed'] >= 20 ? 'Odatdagidan ko‘p — tekshiring' : 'Kirishlar tarixi')
                ->color($s['failed'] >= 20 ? 'danger' : 'gray')->url(LoginEventResource::getUrl('index')),
            Stat::make('Jami foydalanuvchilar', Number::format($s['users'])),
        ];
    }

    /** So‘nggi 14 kunlik kunlik sonlar (sparkline). */
    private function series(string $table): array
    {
        $rows = \DB::table($table)->where('created_at', '>=', today()->subDays(13))
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')->groupBy('d')->pluck('c', 'd');

        return collect(range(13, 0))->map(fn ($d) => (int) ($rows[today()->subDays($d)->toDateString()] ?? 0))->all();
    }

    private function loginSeries(): array
    {
        $rows = LoginEvent::query()->where('event', 'login')->where('created_at', '>=', today()->subDays(13))
            ->selectRaw('DATE(created_at) as d, COUNT(DISTINCT user_id) as c')->groupBy('d')->pluck('c', 'd');

        return collect(range(13, 0))->map(fn ($d) => (int) ($rows[today()->subDays($d)->toDateString()] ?? 0))->all();
    }
}
