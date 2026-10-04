<?php

namespace App\Http\Controllers\Auth;

use App\Enums\OtpPurpose;
use App\Exceptions\OtpException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\VerifyCodeRequest;
use App\Models\Setting;
use App\Services\Auth\OtpService;
use App\Services\Auth\RegistrationService;
use App\Services\Security\LoginTracker;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    private const SESSION_KEY = 'registration_token';

    public function __construct(private RegistrationService $registration, private OtpService $otp) {}

    public function create(): View
    {
        return view('auth.register', ['registrationOpen' => Setting::read('registration_open')]);
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        abort_unless(Setting::read('registration_open'), 403, 'Ro‘yxatdan o‘tish vaqtincha yopiq.');

        try {
            $token = $this->registration->start($request->validated(), $request->ip());
        } catch (OtpException $e) {
            return back()->withInput($request->except('password', 'password_confirmation'))->withErrors(['phone' => $e->getMessage()]);
        }

        $request->session()->put(self::SESSION_KEY, $token);

        return redirect()->route('register.verify');
    }

    public function verifyForm(Request $request): View|RedirectResponse
    {
        $pending = $this->registration->pending($request->session()->get(self::SESSION_KEY));
        if (! $pending) {
            return redirect()->route('register')->withErrors(['phone' => 'Sessiya tugadi. Qaytadan boshlang.']);
        }

        return view('auth.verify', [
            'maskedPhone' => PhoneNumber::mask($pending['phone']),
            'resendIn' => $this->otp->secondsUntilResend($pending['phone'], OtpPurpose::Register),
            'action' => route('register.verify'),
            'resendAction' => route('register.resend'),
        ]);
    }

    public function verify(VerifyCodeRequest $request): RedirectResponse
    {
        try {
            $user = $this->registration->complete((string) $request->session()->get(self::SESSION_KEY), $request->validated('code'));
        } catch (OtpException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        $request->session()->forget(self::SESSION_KEY);
        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        app(LoginTracker::class)->record('register', $user, request());

        return redirect()->route('home')->with('toast', 'Xush kelibsiz, '.$user->name.'!');
    }

    public function resend(Request $request): RedirectResponse
    {
        try {
            $this->registration->resend((string) $request->session()->get(self::SESSION_KEY), $request->ip());
        } catch (OtpException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        return back()->with('status', 'Yangi kod yuborildi.');
    }
}
