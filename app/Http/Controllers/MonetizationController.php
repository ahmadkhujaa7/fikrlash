<?php

namespace App\Http\Controllers;

use App\Exceptions\MonetizationException;
use App\Models\AuthorPayout;
use App\Services\Monetization\MonetizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Monetizatsiya: talablar va so‘rov (hali muallif bo‘lmaganlar uchun),
 * muallif paneli — balans, statistika, maqolalar bo‘yicha daromad, pul yechish.
 */
class MonetizationController extends Controller
{
    public function __construct(private MonetizationService $monetization) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $settings = $this->monetization->settings();

        // Monetizatsiyasi to‘xtatilgan, lekin balansi bor muallif — panelni ko‘radi (pulini yechib oladi);
        // ?apply=1 — qayta so‘rov sahifasi.
        $hasEarnings = $user->authorEarnings()->exists();
        if (! $user->isMonetized() && (! $hasEarnings || $request->boolean('apply'))) {
            return view('monetization.apply', [
                'settings' => $settings,
                'eligibility' => $this->monetization->eligibility($user),
                'application' => $user->authorApplications()->latest('id')->first(),
                'hasEarnings' => $hasEarnings,
            ]);
        }

        if ($user->isMonetized()) {
            $this->monetization->accrue(); // yangi ko‘rishlar darhol balansga tushsin
        }
        $daily = $this->monetization->daily($user, 30);

        return view('monetization.dashboard', [
            'settings' => $settings,
            'balance' => $this->monetization->balance($user),
            'daily' => $daily,
            'week' => ['views' => $daily->slice(-7)->sum('views'), 'amount' => round($daily->slice(-7)->sum('amount'), 2)],
            'month' => ['views' => $daily->sum('views'), 'amount' => round($daily->sum('amount'), 2)],
            'articles' => $this->monetization->articles($user),
            'payouts' => $user->authorPayouts()->latest('id')->limit(20)->get(),
            'pendingPayout' => $user->authorPayouts()->where('status', AuthorPayout::PENDING)->exists(),
            'application' => $user->isMonetized() ? null : $user->authorApplications()->latest('id')->first(),
        ]);
    }

    public function apply(Request $request): RedirectResponse
    {
        $data = $request->validate(['message' => ['nullable', 'string', 'max:1000']]);

        try {
            $this->monetization->apply($request->user(), $data['message'] ?? null);
        } catch (MonetizationException $e) {
            return back()->with('toast', $e->getMessage());
        }

        return back()->with('toast', 'So‘rov yuborildi. Admin ko‘rib chiqqach, bildirishnoma keladi.');
    }

    public function payout(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isMonetized() || $this->monetization->balance($request->user())['balance'] > 0, 403);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'account' => ['required', 'string', 'regex:/^[\d\s]{16,23}$/'],
            'holder' => ['required', 'string', 'max:120'],
        ], [
            'account.regex' => 'Karta raqamini to‘g‘ri kiriting (16 ta raqam).',
            'holder.required' => 'Karta egasining ismini yozing.',
        ]);

        try {
            $this->monetization->requestPayout($request->user(), (float) $data['amount'], $data['account'], $data['holder']);
        } catch (MonetizationException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('toast', 'So‘rov yuborildi. Pul odatda 1–3 ish kunida o‘tkaziladi.');
    }

    public function cancelPayout(Request $request, AuthorPayout $payout): RedirectResponse
    {
        abort_unless($payout->user_id === $request->user()->id, 404);

        try {
            $this->monetization->cancelPayout($payout);
        } catch (MonetizationException $e) {
            return back()->with('toast', $e->getMessage());
        }

        return back()->with('toast', 'So‘rov bekor qilindi, summa balansga qaytdi.');
    }
}
