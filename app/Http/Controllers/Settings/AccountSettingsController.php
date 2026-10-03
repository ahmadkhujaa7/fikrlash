<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Account\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountSettingsController extends Controller
{
    public function __construct(private AccountService $accounts) {}

    public function edit(): View
    {
        return view('settings.account', ['graceDays' => config('fikrlash.accounts.deletion_grace_days')]);
    }

    public function deactivate(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $this->accounts->deactivate($request->user());

        return $this->logout($request, 'Akkaunt vaqtincha o‘chirildi. Qayta kirsangiz — tiklanadi.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
            'confirm' => ['required', 'in:'.$request->user()->username],
        ], ['confirm.in' => 'Tasdiqlash uchun username\'ingizni aynan yozing.']);

        $user = $request->user();
        Auth::guard('web')->logout();
        $this->accounts->delete($user);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('toast', 'Akkauntingiz o‘chirildi.');
    }

    public function export(Request $request): StreamedResponse
    {
        $data = $this->accounts->export($request->user());

        return response()->streamDownload(
            fn () => print (json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)),
            'fikrlash-'.$request->user()->username.'-'.now()->format('Ymd').'.json',
            ['Content-Type' => 'application/json'],
        );
    }

    private function logout(Request $request, string $message): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', $message);
    }
}
