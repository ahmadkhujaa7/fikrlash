<?php

namespace App\Http\Middleware;

use App\Services\Marketing\MarketingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mehmon sayt sahifasini ochganda: ?ref=kod (kampaniya), utm_* belgilari va boshqa saytdan kelganini eslab qoladi.
 * Keyin ro‘yxatdan o‘tsa — foydalanuvchiga "qayerdan kelgan" yoziladi.
 */
class CaptureAcquisition
{
    public function __construct(private MarketingService $marketing) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('admin', 'admin/*', 'livewire/*', 'livewire-*/*', 'r/*', 'i/*', 'up', 'build/*', 'storage/*')) {
            rescue(fn () => $this->marketing->captureLanding($request), report: true);
        }

        return $next($request);
    }
}
