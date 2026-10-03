<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloklangan foydalanuvchi sessiyasi darhol tugatiladi.
 * Suspend muddati o‘tgan bo‘lsa — avtomatik faollashtiriladi.
 * last_active_at 5 daqiqada bir marta yangilanadi (har so‘rovda DB'ga yozmaslik uchun).
 */
class EnsureUserIsActive
{
    private const ACTIVITY_THROTTLE_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            if ($user->status === UserStatus::Suspended && $user->suspended_until?->isPast()) {
                $user->forceFill(['status' => UserStatus::Active, 'suspended_until' => null])->save();
            }

            if (! $user->canSignIn()) {
                if ($request->expectsJson() || $request->is('api/*')) {
                    return ApiResponse::error('Akkauntingiz bloklangan.', 403);
                }

                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors(['login' => 'Akkauntingiz bloklangan.']);
            }

            if (Cache::add("user:active:{$user->id}", 1, self::ACTIVITY_THROTTLE_SECONDS)) {
                $user->newQuery()->whereKey($user->id)->update(['last_active_at' => now()]);
            }
        }

        return $next($request);
    }
}
