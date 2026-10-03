<?php

use Illuminate\Support\Facades\Schedule;

/*
| Scheduler — serverda bitta cron yozuvi kerak:
|   * * * * * cd /var/www/fikrlash && php artisan schedule:run >> /dev/null 2>&1
*/

Schedule::command('views:flush')->everyMinute()->withoutOverlapping();
Schedule::command('posts:refresh-scores')->everyTenMinutes()->withoutOverlapping();
Schedule::command('users:lift-suspensions')->everyTenMinutes();
Schedule::command('ai:retry-pending')->hourly()->withoutOverlapping();
Schedule::command('fikrlash:reconcile-counters')->dailyAt('03:10')->withoutOverlapping();
Schedule::command('fikrlash:prune')->dailyAt('03:30');
Schedule::command('accounts:purge-deleted')->dailyAt('04:00');
Schedule::command('interests:decay')->weeklyOn(1, '04:30');
Schedule::command('queue:prune-failed', ['--hours' => 24 * 14])->weekly();
