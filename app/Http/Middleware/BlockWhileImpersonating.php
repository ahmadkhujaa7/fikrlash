<?php

namespace App\Http\Middleware;

use App\Services\Security\ImpersonationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Admin foydalanuvchi nomidan ko‘rayotganda parol, telefon va akkauntni o‘zgartirib bo‘lmaydi. */
class BlockWhileImpersonating
{
    public function handle(Request $request, Closure $next): Response
    {
        if (ImpersonationService::active($request)) {
            return back()->with('toast', 'Foydalanuvchi nomidan ko‘rish rejimida bu amal taqiqlangan.');
        }

        return $next($request);
    }
}
