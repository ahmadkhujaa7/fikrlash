<?php

use App\Exceptions\OtpException;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SecurityHeaders;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Birinchi tomon (o‘z saytimiz) API'ni sessiya cookie bilan chaqira oladi (Sanctum SPA).
        $middleware->statefulApi();

        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: [EnsureUserIsActive::class]);
        $middleware->api(append: [EnsureUserIsActive::class]);

        // Nginx/load balancer orqasida haqiqiy IP (rate limit, audit) uchun.
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '127.0.0.1'));

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([OtpException::class]);

        // API uchun yagona JSON xato formati. Productionda stack trace, SQL va ichki yo‘llar ko‘rsatilmaydi.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return match (true) {
                $e instanceof ValidationException => ApiResponse::error($e->getMessage(), 422, $e->errors()),
                $e instanceof OtpException => ApiResponse::error($e->getMessage(), 422, ['code' => [$e->getMessage()]]),
                $e instanceof AuthenticationException => ApiResponse::error('Avtorizatsiya talab qilinadi.', 401),
                $e instanceof AuthorizationException, $e instanceof AccessDeniedHttpException => ApiResponse::error('Bu amalga ruxsat yo‘q.', 403),
                $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => ApiResponse::error('Topilmadi.', 404),
                $e instanceof ThrottleRequestsException => ApiResponse::error('So‘rovlar juda ko‘p. Birozdan keyin urinib ko‘ring.', 429),
                $e instanceof HttpExceptionInterface => ApiResponse::error($e->getMessage() ?: 'Xatolik.', $e->getStatusCode()),
                default => config('app.debug') ? null : ApiResponse::error('Serverda xatolik yuz berdi.', 500),
            };
        });
    })->create();
