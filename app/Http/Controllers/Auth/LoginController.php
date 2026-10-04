<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Account\AccountService;
use App\Services\Security\LoginTracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, AccountService $accounts, LoginTracker $tracker): RedirectResponse
    {
        $user = $request->resolveUser();

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate(); // session fixation himoyasi

        $accounts->reactivateIfNeeded($user);
        $user->forceFill(['last_login_at' => now()])->save();
        $tracker->record('login', $user, $request);

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request, LoginTracker $tracker): RedirectResponse
    {
        $tracker->record('logout', $request->user(), $request);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
