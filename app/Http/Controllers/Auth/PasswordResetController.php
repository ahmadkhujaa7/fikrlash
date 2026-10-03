<?php

namespace App\Http\Controllers\Auth;

use App\Enums\OtpPurpose;
use App\Exceptions\OtpException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\Auth\OtpService;
use App\Services\Auth\PasswordResetService;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function __construct(private PasswordResetService $passwords, private OtpService $otp) {}

    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function send(ForgotPasswordRequest $request): RedirectResponse
    {
        $phone = $request->validated('phone');

        try {
            $this->passwords->sendCode($phone, $request->ip());
        } catch (OtpException $e) {
            return back()->withInput()->withErrors(['phone' => $e->getMessage()]);
        }

        $request->session()->put('password_reset_phone', $phone);

        return redirect()->route('password.reset')
            ->with('status', 'Agar raqam ro‘yxatdan o‘tgan bo‘lsa, unga tasdiqlash kodi yuborildi.');
    }

    public function resetForm(Request $request): View|RedirectResponse
    {
        $phone = $request->session()->get('password_reset_phone');
        if (! $phone) {
            return redirect()->route('password.request');
        }

        return view('auth.reset-password', [
            'phone' => $phone,
            'maskedPhone' => PhoneNumber::mask($phone),
            'resendIn' => $this->otp->secondsUntilResend($phone, OtpPurpose::PasswordReset),
        ]);
    }

    public function reset(ResetPasswordRequest $request): RedirectResponse
    {
        try {
            $this->passwords->reset($request->validated('phone'), $request->validated('code'), $request->validated('password'));
        } catch (OtpException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        $request->session()->forget('password_reset_phone');

        return redirect()->route('login')->with('status', 'Parol yangilandi. Yangi parol bilan kiring.');
    }
}
