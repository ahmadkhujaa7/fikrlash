<?php

namespace App\Services\Security;

use App\Models\LoginEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Kirishlar tarixini yozadi (admin panel → Kuzatuv). Hech qachon asosiy jarayonni to‘xtatmaydi:
 * yozib bo‘lmasa — jim o‘tib ketadi (rescue).
 */
class LoginTracker
{
    public function record(string $event, ?User $user, ?Request $request = null, ?string $identifier = null, ?User $actor = null): void
    {
        $request ??= request();

        rescue(function () use ($event, $user, $request, $identifier, $actor) {
            $ua = (string) $request?->userAgent();
            LoginEvent::query()->create([
                'user_id' => $user?->id,
                'event' => $event,
                'channel' => $this->channel($request),
                'identifier' => $identifier !== null ? mb_substr($identifier, 0, 64) : null,
                'ip' => $request?->ip(),
                'device' => self::describeDevice($ua),
                'user_agent' => mb_substr($ua, 0, 300) ?: null,
                'actor_id' => $actor?->id,
            ]);
            Cache::forget('admin:security-stats');
        }, report: false);
    }

    /** "Chrome · Windows", "Safari · iPhone", "Mobil ilova" kabi qisqa tavsif. */
    public static function describeDevice(?string $ua): string
    {
        $ua = (string) $ua;
        if ($ua === '') {
            return 'Noma’lum qurilma';
        }

        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'YaBrowser') => 'Yandex',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            str_contains($ua, 'okhttp') || str_contains($ua, 'Dart/') || str_contains($ua, 'CFNetwork') => 'Mobil ilova',
            str_contains($ua, 'curl') || str_contains($ua, 'python') || str_contains($ua, 'Postman') => 'Skript/API',
            default => 'Brauzer',
        };

        $os = match (true) {
            str_contains($ua, 'iPhone') => 'iPhone',
            str_contains($ua, 'iPad') => 'iPad',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => null,
        };

        return $os ? "{$browser} · {$os}" : $browser;
    }

    private function channel(?Request $request): string
    {
        if (! $request) {
            return 'web';
        }

        return match (true) {
            $request->is('api/*') => 'api',
            $request->is('admin*') || $request->is('livewire*') => 'admin',
            default => 'web',
        };
    }
}
