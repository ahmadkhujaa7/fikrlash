<?php

use App\Http\Controllers\Api\V1;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| REST API v1 — /api/v1/...
|--------------------------------------------------------------------------
| Autentifikatsiya: "Authorization: Bearer <token>" (Sanctum, users_tokens jadvali).
| O‘z saytimiz (fikrlash.uz) shu endpointlarni sessiya cookie + CSRF bilan chaqiradi.
| Javob formati: {"success", "message", "data", "meta"} / {"success": false, "message", "errors"}.
*/

Route::prefix('v1')->name('api.v1.')->middleware('throttle:api')->group(function () {

    // ---- Auth (mehmonlar) ----
    Route::prefix('auth')->group(function () {
        Route::post('register', [V1\AuthController::class, 'register'])->middleware('throttle:register');
        Route::post('register/verify', [V1\AuthController::class, 'verifyRegistration'])->middleware('throttle:otp-verify');
        Route::post('register/resend', [V1\AuthController::class, 'resendRegistrationCode'])->middleware('throttle:otp-send');
        Route::post('login', [V1\AuthController::class, 'login'])->middleware('throttle:login');
        Route::post('password/forgot', [V1\AuthController::class, 'forgotPassword'])->middleware('throttle:password-reset');
        Route::post('password/reset', [V1\AuthController::class, 'resetPassword'])->middleware('throttle:otp-verify');
    });

    // ---- Ochiq (token ixtiyoriy) ----
    Route::get('feed', [V1\FeedController::class, 'index'])->name('feed');
    Route::get('posts', [V1\PostController::class, 'index']);
    Route::get('posts/{post}', [V1\PostController::class, 'show'])->whereNumber('post');
    Route::get('posts/{post}/comments', [V1\CommentController::class, 'index'])->whereNumber('post');
    Route::get('users/{user}', [V1\UserController::class, 'show']);
    Route::get('users/{user}/posts', [V1\UserController::class, 'posts']);
    Route::get('users/{user}/followers', [V1\UserController::class, 'followers']);
    Route::get('users/{user}/following', [V1\UserController::class, 'following']);
    Route::get('categories', [V1\CategoryController::class, 'index']);
    Route::get('categories/{category}/posts', [V1\CategoryController::class, 'posts']);
    Route::get('tags/{slug}/posts', [V1\TagController::class, 'posts']);
    Route::get('search', V1\SearchController::class)->middleware('throttle:search');
    Route::post('views', [V1\PostController::class, 'views'])->middleware('throttle:views')->name('views');

    // ---- Avtorizatsiya talab qilinadi ----
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [V1\AuthController::class, 'me']);
        Route::post('auth/logout', [V1\AuthController::class, 'logout']);

        Route::get('tokens', [V1\TokenController::class, 'index']);
        Route::delete('tokens/others', [V1\TokenController::class, 'destroyOthers']);
        Route::delete('tokens/{token}', [V1\TokenController::class, 'destroy'])->whereNumber('token');

        Route::patch('me', [V1\MeController::class, 'update']);
        Route::post('me/avatar', [V1\MeController::class, 'avatar'])->middleware('throttle:uploads');
        Route::get('me/saved', [V1\MeController::class, 'saved']);
        Route::get('me/export', [V1\MeController::class, 'export']);
        Route::delete('me', [V1\MeController::class, 'destroy']);

        Route::post('posts', [V1\PostController::class, 'store'])->middleware('throttle:posts');
        Route::match(['put', 'patch'], 'posts/{post}', [V1\PostController::class, 'update'])->whereNumber('post');
        Route::delete('posts/{post}', [V1\PostController::class, 'destroy'])->whereNumber('post');

        Route::middleware('throttle:interactions')->group(function () {
            Route::post('posts/{post}/like', [V1\PostController::class, 'like'])->name('posts.like');
            Route::delete('posts/{post}/like', [V1\PostController::class, 'unlike'])->name('posts.unlike');
            Route::post('posts/{post}/save', [V1\PostController::class, 'save'])->name('posts.save');
            Route::delete('posts/{post}/save', [V1\PostController::class, 'unsave'])->name('posts.unsave');
            Route::post('comments/{comment}/like', [V1\CommentController::class, 'like'])->name('comments.like');
            Route::delete('comments/{comment}/like', [V1\CommentController::class, 'unlike'])->name('comments.unlike');
        });
        Route::post('posts/{post}/read', [V1\PostController::class, 'readTime'])->middleware('throttle:views')->name('posts.read');
        Route::post('posts/{post}/not-interested', [V1\PostController::class, 'notInterested'])->middleware('throttle:interactions')->name('posts.not-interested');

        Route::post('posts/{post}/comments', [V1\CommentController::class, 'store'])->middleware('throttle:comments');
        Route::patch('comments/{comment}', [V1\CommentController::class, 'update']);
        Route::delete('comments/{comment}', [V1\CommentController::class, 'destroy']);

        Route::middleware('throttle:follows')->group(function () {
            Route::post('users/{user}/follow', [V1\UserController::class, 'follow'])->name('users.follow');
            Route::delete('users/{user}/follow', [V1\UserController::class, 'unfollow'])->name('users.unfollow');
        });

        Route::get('notifications', [V1\NotificationController::class, 'index']);
        Route::get('notifications/unread-count', [V1\NotificationController::class, 'unreadCount'])->name('notifications.unread');
        Route::post('notifications/read-all', [V1\NotificationController::class, 'markAllRead']);
        Route::post('notifications/{notification}/read', [V1\NotificationController::class, 'markRead'])->whereNumber('notification');

        Route::post('reports', [V1\ReportController::class, 'store'])->middleware('throttle:reports')->name('reports.store');
    });

    Route::fallback(fn () => ApiResponse::error('Endpoint topilmadi.', 404));
});
