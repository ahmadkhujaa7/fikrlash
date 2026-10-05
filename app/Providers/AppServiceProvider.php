<?php

namespace App\Providers;

use App\Contracts\SmsProvider;
use App\Models\Comment;
use App\Models\PersonalAccessToken;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Services\Chat\ChatService;
use App\Services\Sms\ArraySmsProvider;
use App\Services\Sms\EskizSmsProvider;
use App\Services\Sms\LogSmsProvider;
use App\Services\Social\NotificationService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Events\LocaleUpdated;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SmsProvider::class, fn () => match (config('sms.driver')) {
            'eskiz' => new EskizSmsProvider,
            'log' => new LogSmsProvider,
            'array' => new ArraySmsProvider,
            default => throw new InvalidArgumentException('Noma\'lum SMS drayver: '.config('sms.driver')),
        });
    }

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        Carbon::setLocale('uz_Latn');
        // Livewire (admin panel) har so‘rovda app locale'ni qayta o‘rnatadi — Carbon esa "uz" ni kirill deb oladi.
        // Shuning uchun har safar lotin yozuviga qaytaramiz ("2 kun avval", "2 кун аввал" emas).
        Event::listen(LocaleUpdated::class, function ($event) {
            if (str_starts_with($event->locale, 'uz')) {
                Carbon::setLocale('uz_Latn');
                CarbonImmutable::setLocale('uz_Latn');
                CarbonInterval::setLocale('uz_Latn');
            }
        });

        // DB'da klass nomlari emas, qisqa aliaslar saqlanadi (refactor'ga chidamli).
        Relation::enforceMorphMap([
            'user' => User::class,
            'post' => Post::class,
            'comment' => Comment::class,
            'report' => Report::class,
        ]);

        // Development'da N+1 va boshqa xatolarni erta ushlash.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Password::defaults(fn () => Password::min(config('fikrlash.password.min'))->letters()->numbers());

        Paginator::defaultView('components.pagination');
        Paginator::defaultSimpleView('components.pagination');

        Route::bind('user', function (string $value) {
            return User::query()->visible()
                ->where(ctype_digit($value) ? 'id' : 'username', ctype_digit($value) ? (int) $value : mb_strtolower($value))
                ->firstOrFail();
        });

        $this->configureRateLimiting();

        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            $view->with('unreadNotifications', $user ? app(NotificationService::class)->unreadCount($user) : 0);
            $view->with('unreadMessages', $user ? app(ChatService::class)->unreadCount($user) : 0);
        });
    }

    private function configureRateLimiting(): void
    {
        $by = fn (Request $r) => $r->user()?->id ? 'u:'.$r->user()->id : 'ip:'.$r->ip();

        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)->by('login:'.Str::lower((string) $r->input('login')).'|'.$r->ip()),
            Limit::perMinute(20)->by('login-ip:'.$r->ip()),
        ]);
        RateLimiter::for('register', fn (Request $r) => Limit::perHour(10)->by('register:'.$r->ip()));
        RateLimiter::for('otp-send', fn (Request $r) => Limit::perMinute(3)->by('otp-send:'.$r->ip()));
        RateLimiter::for('otp-verify', fn (Request $r) => Limit::perMinute(10)->by('otp-verify:'.$r->ip()));
        RateLimiter::for('password-reset', fn (Request $r) => Limit::perHour(10)->by('pwd:'.$r->ip()));

        RateLimiter::for('posts', fn (Request $r) => [
            Limit::perMinute(5)->by('posts:'.$by($r)),
            Limit::perDay(config('fikrlash.posts.daily_limit'))->by('posts-day:'.$by($r)),
        ]);
        RateLimiter::for('comments', fn (Request $r) => Limit::perMinute(15)->by('comments:'.$by($r)));
        RateLimiter::for('interactions', fn (Request $r) => Limit::perMinute(90)->by('interact:'.$by($r)));
        RateLimiter::for('follows', fn (Request $r) => Limit::perMinute(30)->by('follows:'.$by($r)));
        RateLimiter::for('search', fn (Request $r) => Limit::perMinute(40)->by('search:'.$by($r)));
        // Yozish paytidagi qidiruv: har bir harf emas, 250 ms pauzadan keyin so‘rov ketadi.
        RateLimiter::for('search-live', fn (Request $r) => Limit::perMinute(150)->by('search-live:'.$by($r)));
        RateLimiter::for('views', fn (Request $r) => Limit::perMinute(120)->by('views:'.$by($r)));
        RateLimiter::for('reports', fn (Request $r) => Limit::perHour(20)->by('reports:'.$by($r)));
        RateLimiter::for('uploads', fn (Request $r) => Limit::perHour(30)->by('uploads:'.$by($r)));
        // Xabarlar: yuborish/tahrir/reaksiya va qisqa so‘rovlar (poll + "yozmoqda").
        RateLimiter::for('chat-send', fn (Request $r) => [Limit::perMinute(40)->by('chat:'.$by($r)), Limit::perDay(3000)->by('chat-day:'.$by($r))]);
        RateLimiter::for('chat-poll', fn (Request $r) => Limit::perMinute(90)->by('chat-poll:'.$by($r)));
        // Maqola ichidagi rasmlar: bitta maqolada 30 tagacha bo‘lishi mumkin.
        RateLimiter::for('media', fn (Request $r) => [Limit::perMinute(20)->by('media:'.$by($r)), Limit::perDay(200)->by('media-day:'.$by($r))]);
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by('api:'.$by($r)));
        RateLimiter::for('ai', fn () => Limit::perMinute((int) config('ai.requests_per_minute')));
    }
}
