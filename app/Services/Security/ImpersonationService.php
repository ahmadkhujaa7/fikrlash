<?php

namespace App\Services\Security;

use App\Models\User;
use App\Services\Social\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Admin foydalanuvchi nomidan saytni ko‘radi (muammolarni tekshirish uchun).
 *  - adminlar va bloklangan akkauntlar nomidan kirib bo‘lmaydi;
 *  - har bir kirish/qaytish audit log va kirishlar tarixiga yoziladi;
 *  - saytda doim "Siz ... sifatida ko‘ryapsiz" paneli turadi;
 *  - parol, telefon va akkauntni o‘chirish kabi amallar bu rejimda taqiqlangan.
 */
class ImpersonationService
{
    public const SESSION_KEY = 'impersonator_id';

    public function __construct(private AuditLogger $audit, private LoginTracker $tracker) {}

    public function start(User $admin, User $target, Request $request): void
    {
        if (! $admin->isAdmin() || $target->isAdmin() || $admin->is($target) || ! $target->canSignIn() || $target->trashed()) {
            throw ValidationException::withMessages(['user' => 'Bu foydalanuvchi nomidan kirib bo‘lmaydi.']);
        }

        $this->audit->log('user.impersonated', $target, [], ['admin' => $admin->username], $admin);
        $this->tracker->record('impersonate', $target, $request, actor: $admin);

        Auth::guard('web')->login($target);
        session()->regenerate();
        session()->put(self::SESSION_KEY, $admin->id);
    }

    public function stop(Request $request): ?User
    {
        $adminId = session()->pull(self::SESSION_KEY);
        $target = $request->user();
        $admin = $adminId ? User::query()->find($adminId) : null;

        if (! $admin || ! $admin->isAdmin()) {
            Auth::guard('web')->logout();
            session()->invalidate();

            return null;
        }

        $this->tracker->record('impersonate_end', $target, $request, actor: $admin);
        $this->audit->log('user.impersonation_ended', $target, [], [], $admin);

        Auth::guard('web')->login($admin);
        session()->regenerate();

        return $target;
    }

    public static function active(?Request $request = null): bool
    {
        $request ??= request();

        return $request->hasSession() ? $request->session()->has(self::SESSION_KEY) : session()->has(self::SESSION_KEY);
    }
}
