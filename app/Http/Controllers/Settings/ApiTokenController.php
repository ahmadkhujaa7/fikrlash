<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApiTokenController extends Controller
{
    private const MAX_TOKENS = 10;

    public function index(Request $request): View
    {
        return view('settings.tokens', [
            'tokens' => $request->user()->tokens()->latest()->get(),
            'newToken' => session('new_token'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'expires_in_days' => ['nullable', 'integer', 'in:7,30,90,365'],
        ]);

        if ($request->user()->tokens()->count() >= self::MAX_TOKENS) {
            return back()->withErrors(['name' => 'Ko‘pi bilan '.self::MAX_TOKENS.' ta token yaratish mumkin.']);
        }

        $expires = isset($data['expires_in_days']) ? now()->addDays((int) $data['expires_in_days']) : null;
        $token = $request->user()->createToken($data['name'], ['*'], $expires);

        // Ochiq token faqat bir marta ko‘rsatiladi — DB'da faqat hash.
        return back()->with('new_token', $token->plainTextToken);
    }

    public function destroy(Request $request, int $token): RedirectResponse
    {
        $request->user()->tokens()->whereKey($token)->delete();

        return back()->with('toast', 'Token bekor qilindi.');
    }
}
