<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Models\MarketingLink;
use App\Models\User;
use App\Services\Marketing\MarketingService;
use App\Services\Marketing\QrCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Kampaniya havolasi (fikrlash.uz/r/tg1) va do‘st taklifi (fikrlash.uz/i/username).
 * Bosish yoziladi, keyin foydalanuvchi kerakli sahifaga yo‘naltiriladi.
 */
class MarketingController extends Controller
{
    public function __construct(private MarketingService $marketing) {}

    public function go(Request $request, string $code): RedirectResponse
    {
        $link = MarketingLink::query()->where('code', mb_strtolower($code))->first();
        if (! $link) {
            return redirect()->route('home');
        }

        rescue(fn () => $this->marketing->track($link, $request), report: true);

        // Kirgan foydalanuvchini ro‘yxatdan o‘tish sahifasiga emas, lentaga.
        $target = $request->user() && $link->targetPath() === '/register' ? '/' : $link->targetPath();

        return redirect($target);
    }

    public function invite(Request $request, string $username): RedirectResponse
    {
        $inviter = User::query()->where('username', mb_strtolower($username))->where('status', UserStatus::Active)->first();

        if (! $inviter || ! $this->marketing->invitesEnabled()) {
            return redirect()->route($request->user() ? 'home' : 'register');
        }
        if ($request->user()) {
            return redirect($inviter->profileUrl());
        }
        if (! $this->marketing->isBot($request->userAgent())) {
            $this->marketing->rememberInviter($inviter);
        }

        return redirect()->route('register');
    }

    /** "Do‘stlarni taklif qilish": shaxsiy havola, ulashish tugmalari va taklif qilinganlar ro‘yxati. */
    public function invites(Request $request): View
    {
        abort_unless($this->marketing->invitesEnabled(), 404);
        $me = $request->user();

        return view('marketing.invite', [
            'url' => $this->marketing->inviteUrl($me),
            'invitees' => $me->invitees()->latest('id')->limit(100)->get(['id', 'name', 'username', 'avatar_path', 'verified_at', 'monetized_at', 'created_at']),
            'total' => $me->invitees()->count(),
        ]);
    }

    /** Admin: kampaniya havolasining QR kodi (PNG yoki SVG); ?download=1 — fayl sifatida. */
    public function qr(Request $request, MarketingLink $link, string $format): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $body = $format === 'svg' ? QrCode::svg($link->url()) : QrCode::png($link->url(), 24);
        $headers = [
            'Content-Type' => $format === 'svg' ? 'image/svg+xml' : 'image/png',
            'Cache-Control' => 'private, max-age=600',
        ];
        if ($request->boolean('download')) {
            $headers['Content-Disposition'] = 'attachment; filename="fikrlash-qr-'.$link->code.'.'.$format.'"';
        }

        return response($body, 200, $headers);
    }
}
