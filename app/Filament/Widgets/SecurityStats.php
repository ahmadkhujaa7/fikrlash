<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Models\LoginEvent;
use App\Models\User;
use App\Models\UserSession;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class SecurityStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $s = Cache::remember('admin:security-stats', 30, fn () => [
            'failed' => LoginEvent::query()->where('event', 'failed')->where('created_at', '>=', now()->subDay())->count(),
            'failed_ips' => LoginEvent::query()->where('event', 'failed')->where('created_at', '>=', now()->subDay())->distinct()->count('ip'),
            'blocked' => LoginEvent::query()->where('event', 'blocked')->where('created_at', '>=', now()->subDays(7))->count(),
            'logins' => LoginEvent::query()->where('event', 'login')->where('created_at', '>=', now()->subDay())->count(),
            'impersonations' => LoginEvent::query()->where('event', 'impersonate')->where('created_at', '>=', now()->subDays(30))->count(),
            'admins' => User::query()->where('role', UserRole::Admin)->count(),
            'admin_sessions' => UserSession::query()->online()
                ->whereIn('user_id', User::query()->where('role', UserRole::Admin)->select('id'))->count(),
        ]);

        return [
            Stat::make('Muvaffaqiyatli kirishlar (24 soat)', $s['logins'])->color('success'),
            Stat::make('Noto‘g‘ri parol (24 soat)', $s['failed'])
                ->description($s['failed_ips'].' ta turli IP manzildan')
                ->color($s['failed'] >= 20 ? 'danger' : 'gray'),
            Stat::make('Bloklangan akkauntga urinish (7 kun)', $s['blocked'])->color($s['blocked'] ? 'warning' : 'gray'),
            Stat::make('Adminlar', $s['admins'])->description($s['admin_sessions'].' tasi hozir onlayn'),
            Stat::make('Foydalanuvchi nomidan kirish (30 kun)', $s['impersonations'])
                ->description('Har biri audit log’da')->color($s['impersonations'] ? 'warning' : 'gray'),
        ];
    }
}
