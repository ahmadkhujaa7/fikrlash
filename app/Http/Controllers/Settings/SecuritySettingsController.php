<?php

namespace App\Http\Controllers\Settings;

use App\Enums\OtpPurpose;
use App\Events\PhoneVerified;
use App\Exceptions\OtpException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ChangePasswordRequest;
use App\Http\Requests\Account\ChangePhoneRequest;
use App\Http\Requests\Auth\VerifyCodeRequest;
use App\Services\Account\AccountService;
use App\Services\Auth\OtpService;
use App\Services\Auth\PhoneChangeService;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SecuritySettingsController extends Controller
{
    public function __construct(private PhoneChangeService $phones, private OtpService $otp) {}

    public function edit(Request $request): View
    {
        $user = $request->user();
        $pending = $this->phones->pendingPhone($user);

        return view('settings.security', [
            'user' => $user,
            'maskedPhone' => PhoneNumber::mask($user->phone),
            'pendingPhone' => $pending ? PhoneNumber::mask($pending) : null,
            'resendIn' => $pending ? $this->otp->secondsUntilResend($pending, OtpPurpose::PhoneChange) : 0,
        ]);
    }

    public function updatePassword(ChangePasswordRequest $request, AccountService $accounts): RedirectResponse
    {
        $accounts->changePassword($request->user(), $request->validated('password'));
        // Boshqa qurilmalardagi sessiyalar yopiladi, joriy sessiya saqlanadi.
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $request->user()->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        return back()->with('toast', 'Parol yangilandi.');
    }

    public function requestPhoneChange(ChangePhoneRequest $request): RedirectResponse
    {
        try {
            $this->phones->start($request->user(), $request->validated('phone'), $request->ip());
        } catch (OtpException $e) {
            return back()->withErrors(['phone' => $e->getMessage()]);
        }

        return back()->with('status', 'Yangi raqamga tasdiqlash kodi yuborildi.');
    }

    public function verifyPhoneChange(VerifyCodeRequest $request): RedirectResponse
    {
        try {
            $this->phones->complete($request->user(), $request->validated('code'));
        } catch (OtpException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        PhoneVerified::dispatch($request->user());

        return back()->with('toast', 'Telefon raqam yangilandi.');
    }
}
