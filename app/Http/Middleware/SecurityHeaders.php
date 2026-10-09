<?php

namespace App\Http\Middleware;

use App\Support\TrackingIds;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Xavfsizlik headerlari. CSP — nonce asosida: inline <script> faqat nonce bilan ishlaydi.
 * Alpine.js standart build ifodalarni baholash uchun 'unsafe-eval' talab qiladi.
 * Admin panel (Filament/Livewire) inline skriptlardan foydalanadi — u yerda yumshoqroq siyosat.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Str::random(32);
        Vite::useCspNonce($nonce);
        $request->attributes->set('csp_nonce', $nonce);

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Mikrofon (ovozli xabar), kamera (chatda rasm olish), joylashuv (chatda yuborish) — faqat o‘z saytimizda.
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(self), geolocation=(self), payment=(), usb=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if (config('fikrlash.security.hsts') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (config('fikrlash.security.csp') && ! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', $this->policy($request, $nonce));
        }

        return $response;
    }

    private function policy(Request $request, string $nonce): string
    {
        $media = $this->origin(config('filesystems.disks.'.config('fikrlash.media.disk').'.url'));
        $dev = app()->isLocal() && file_exists(public_path('hot'))
            ? trim((string) file_get_contents(public_path('hot')))
            : '';
        $devWs = $dev ? str_replace(['http://', 'https://'], ['ws://', 'wss://'], $dev) : '';

        $isAdmin = $request->is('admin', 'admin/*', 'livewire/*', 'livewire-*/*');
        // Marketing analitikasi (Meta Pixel, GA4, Yandex Metrika) — faqat sayt sahifalarida va ID kiritilgan bo‘lsa.
        $tracking = $isAdmin ? ['script' => '', 'img' => '', 'connect' => ''] : rescue(fn () => TrackingIds::csp(), ['script' => '', 'img' => '', 'connect' => ''], false);
        $script = $isAdmin
            ? "'self' 'unsafe-inline' 'unsafe-eval' {$dev}"
            : "'self' 'nonce-{$nonce}' 'unsafe-eval' {$tracking['script']} {$dev}";

        return implode('; ', array_filter([
            "default-src 'self'",
            "script-src {$script}",
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net {$dev}",
            "font-src 'self' data: https://fonts.bunny.net",
            // tile.openstreetmap.org — chatdagi joylashuv xaritasi.
            "img-src 'self' data: blob: https://tile.openstreetmap.org {$media} {$tracking['img']}",
            // Ovozli xabar: yozib olingandan keyin yuborilguncha blob: dan eshitiladi.
            "media-src 'self' blob: {$media}",
            "connect-src 'self' {$tracking['connect']} {$dev} {$devWs}",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]));
    }

    private function origin(?string $url): string
    {
        if (! $url || ! str_starts_with($url, 'http')) {
            return '';
        }
        $parts = parse_url($url);

        return ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
