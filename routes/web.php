<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SavedController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Settings;
use App\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

Route::get('/', [FeedController::class, 'index'])->name('home');
Route::get('/feed', [FeedController::class, 'page'])->name('feed.page');

// ---- Autentifikatsiya ----
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:register');
    Route::get('/register/verify', [RegisterController::class, 'verifyForm'])->name('register.verify');
    Route::post('/register/verify', [RegisterController::class, 'verify'])->middleware('throttle:otp-verify');
    Route::post('/register/resend', [RegisterController::class, 'resend'])->name('register.resend')->middleware('throttle:otp-send');

    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');

    Route::get('/password/forgot', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/password/forgot', [PasswordResetController::class, 'send'])->name('password.send')->middleware('throttle:password-reset');
    Route::get('/password/reset', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/password/reset', [PasswordResetController::class, 'reset'])->middleware('throttle:otp-verify');
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// ---- Ochiq sahifalar ----
Route::get('/posts/{post}', [PostController::class, 'show'])->whereNumber('post')->name('posts.show');
Route::get('/posts/{post}/comments', [CommentController::class, 'index'])->whereNumber('post')->name('posts.comments');
Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
Route::get('/c/{category}', [CategoryController::class, 'show'])->name('categories.show');
Route::get('/t/{slug}', [TagController::class, 'show'])->name('tags.show');
Route::get('/search', [SearchController::class, 'index'])->middleware('throttle:search')->name('search');
Route::get('/@{user}', [ProfileController::class, 'show'])->name('profile.show');
Route::get('/@{user}/posts', [ProfileController::class, 'posts'])->name('profile.posts');
Route::get('/@{user}/replies', [ProfileController::class, 'replies'])->name('profile.replies');
Route::get('/@{user}/followers', [ProfileController::class, 'followers'])->name('profile.followers');
Route::get('/@{user}/following', [ProfileController::class, 'following'])->name('profile.following');
Route::view('/about', 'pages.about')->name('about');
Route::view('/terms', 'pages.terms')->name('terms');
Route::view('/privacy', 'pages.privacy')->name('privacy');
Route::view('/docs/api', 'docs.api')->name('docs.api');
Route::get('/sitemap.xml', \App\Http\Controllers\SitemapController::class)->name('sitemap');
Route::get('/docs/openapi.json', fn () => response()->file(base_path('docs/openapi.json'), ['Content-Type' => 'application/json']))->name('docs.openapi');

// ---- Tizimga kirganlar ----
Route::middleware('auth')->group(function () {
    Route::get('/compose', [PostController::class, 'create'])->name('posts.create');
    Route::post('/posts', [PostController::class, 'store'])->middleware('throttle:posts')->name('posts.store');
    Route::get('/posts/{post}/edit', [PostController::class, 'edit'])->whereNumber('post')->name('posts.edit');
    Route::put('/posts/{post}', [PostController::class, 'update'])->whereNumber('post')->name('posts.update');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->whereNumber('post')->name('posts.destroy');
    Route::get('/drafts', [PostController::class, 'drafts'])->name('posts.drafts');

    Route::post('/posts/{post}/comments', [CommentController::class, 'store'])->whereNumber('post')->middleware('throttle:comments')->name('comments.store');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

    Route::get('/saved', [SavedController::class, 'index'])->name('saved.index');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [Settings\ProfileSettingsController::class, 'edit'])->name('profile');
        Route::put('/profile', [Settings\ProfileSettingsController::class, 'update'])->name('profile.update');
        Route::post('/avatar', [Settings\ProfileSettingsController::class, 'updateAvatar'])->middleware('throttle:uploads')->name('avatar');
        Route::delete('/avatar', [Settings\ProfileSettingsController::class, 'destroyAvatar'])->name('avatar.destroy');

        Route::get('/security', [Settings\SecuritySettingsController::class, 'edit'])->name('security');
        Route::put('/password', [Settings\SecuritySettingsController::class, 'updatePassword'])->name('password');
        Route::post('/phone', [Settings\SecuritySettingsController::class, 'requestPhoneChange'])->middleware('throttle:otp-send')->name('phone');
        Route::post('/phone/verify', [Settings\SecuritySettingsController::class, 'verifyPhoneChange'])->middleware('throttle:otp-verify')->name('phone.verify');

        Route::get('/tokens', [Settings\ApiTokenController::class, 'index'])->name('tokens');
        Route::post('/tokens', [Settings\ApiTokenController::class, 'store'])->name('tokens.store');
        Route::delete('/tokens/{token}', [Settings\ApiTokenController::class, 'destroy'])->whereNumber('token')->name('tokens.destroy');

        Route::get('/account', [Settings\AccountSettingsController::class, 'edit'])->name('account');
        Route::post('/deactivate', [Settings\AccountSettingsController::class, 'deactivate'])->name('deactivate');
        Route::delete('/account', [Settings\AccountSettingsController::class, 'destroy'])->name('delete');
        Route::get('/export', [Settings\AccountSettingsController::class, 'export'])->name('export');
    });
});
