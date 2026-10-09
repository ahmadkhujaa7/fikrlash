<?php

namespace App\Services\Marketing;

use App\Enums\NotificationType;
use App\Enums\UserStatus;
use App\Models\MarketingLink;
use App\Models\MarketingVisit;
use App\Models\Setting;
use App\Models\User;
use App\Services\Social\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;

/**
 * Marketing: foydalanuvchi qayerdan kelganini aniqlash.
 *
 * Manbalar (eng oxirgi bosilgani hisoblanadi, muddati — sozlamada, standart 30 kun):
 *  - link     — admin yaratgan kampaniya havolasi: fikrlash.uz/r/tg1 (yoki istalgan sahifa ?ref=tg1);
 *  - invite   — foydalanuvchining do‘st taklifi: fikrlash.uz/i/username;
 *  - utm      — ?utm_source=…&utm_campaign=… bilan kelgan (havola yaratilmagan reklama);
 *  - referrer — boshqa saytdan o‘tgan (t.me, instagram.com, google…), birinchi tashrif;
 *  - direct   — hech biri yo‘q.
 * Botlar (Telegram/Facebook havola ko‘rinishini yasovchilar, qidiruv robotlari) hisoblanmaydi.
 */
class MarketingService
{
    public const COOKIE_VISITOR = 'fk_vid';

    public const COOKIE_REF = 'fk_ref';

    public const COOKIE_INVITE = 'fk_inv';

    public const COOKIE_UTM = 'fk_utm';

    public const COOKIE_SOURCE = 'fk_src';

    public const DEFAULTS = [
        'marketing_attribution_days' => 30,
        'marketing_invites_enabled' => true,
        'marketing_meta_pixel' => null,
        'marketing_ga4' => null,
        'marketing_yandex_metrika' => null,
    ];

    public const SOURCES = [
        'link' => 'Kampaniya havolasi',
        'invite' => 'Do‘st taklifi',
        'utm' => 'UTM (reklama belgisi)',
        'referrer' => 'Boshqa sayt',
        'direct' => 'To‘g‘ridan-to‘g‘ri',
    ];

    private const BOTS = '~googlebot|bingbot|yandex(bot|images|mobilebot)|duckduckbot|baiduspider|applebot|ahrefsbot|semrushbot|mj12bot|petalbot|bytespider|telegrambot|twitterbot|slackbot|discordbot|linkedinbot|pinterestbot|redditbot|facebookexternalhit|meta-externalagent|facebookcatalog|whatsapp|skypeuripreview|vkshare|embedly|bitlybot|crawler|spider|headlesschrome|lighthouse|curl/|wget/|python|go-http-client|okhttp|java/|node-fetch|axios/|postmanruntime|scrapy|\\bbot\\b~i';

    public function __construct(private NotificationService $notifications) {}

    public function settings(): array
    {
        return collect(self::DEFAULTS)->mapWithKeys(fn ($v, $k) => [$k => Setting::read($k, $v)])->all();
    }

    public function attributionDays(): int
    {
        return max(1, min(365, (int) Setting::read('marketing_attribution_days', 30)));
    }

    public function invitesEnabled(): bool
    {
        return (bool) Setting::read('marketing_invites_enabled', true);
    }

    /* ---------------- Kuzatish ---------------- */

    public function isBot(?string $userAgent): bool
    {
        $ua = trim((string) $userAgent);

        return $ua === '' || preg_match(self::BOTS, $ua) === 1;
    }

    /** Kampaniya havolasi bosildi: tashrif yoziladi, atributsiya cookie qo‘yiladi. Bot yoki o‘chiq havola — null. */
    public function track(MarketingLink $link, Request $request): ?MarketingVisit
    {
        if (! $link->isLive() || $this->isBot($request->userAgent())) {
            return null;
        }

        $visitor = $this->visitorId($request);
        $unique = ! MarketingVisit::query()->where('marketing_link_id', $link->id)->where('visitor', $visitor)->exists();
        $agent = $this->parseAgent((string) $request->userAgent());

        $visit = MarketingVisit::query()->create([
            'marketing_link_id' => $link->id,
            'visitor' => $visitor,
            'is_unique' => $unique,
            'user_id' => $request->user()?->id,
            'referrer' => $this->externalHost($request),
        ] + $agent);

        MarketingLink::query()->whereKey($link->id)->update([
            'clicks_count' => DB::raw('clicks_count + 1'),
            'visitors_count' => DB::raw('visitors_count + '.($unique ? 1 : 0)),
            'last_click_at' => now(),
        ]);

        if (! $request->user()) {
            $this->queue(self::COOKIE_REF, ['l' => $link->id, 'v' => $visit->id, 't' => now()->timestamp]);
        }

        return $visit;
    }

    /** Har bir sahifa (mehmonlar): ?ref=kod, UTM belgilari va boshqa saytdan kelganini eslab qoladi. */
    public function captureLanding(Request $request): void
    {
        if ($request->user() || ! $request->isMethod('GET') || $request->ajax() || $this->isBot($request->userAgent())) {
            return;
        }

        $ref = strtolower((string) $request->query('ref', ''));
        if ($ref !== '' && preg_match('/^[a-z0-9_-]{1,40}$/', $ref)) {
            if ($link = MarketingLink::query()->where('code', $ref)->first()) {
                $this->track($link, $request);
            }
        }

        if ($request->filled('utm_source')) {
            $clean = fn (string $key) => mb_substr(trim(preg_replace('/[^\pL\pN _.\-\/]/u', '', (string) $request->query($key, ''))), 0, 40);
            $this->queue(self::COOKIE_UTM, ['s' => $clean('utm_source'), 'm' => $clean('utm_medium'), 'c' => $clean('utm_campaign'), 't' => now()->timestamp]);
        }

        if (! $request->cookie(self::COOKIE_SOURCE) && ($host = $this->externalHost($request))) {
            $this->queue(self::COOKIE_SOURCE, ['h' => $host, 't' => now()->timestamp]);
        }
    }

    /** Ro‘yxatdan o‘tishning 1-bosqichi (SMS kod so‘raldi) — havola bo‘yicha bir marta hisoblanadi. */
    public function recordStart(Request $request): void
    {
        $ref = $this->activeRef($request);
        $visitor = $request->cookie(self::COOKIE_VISITOR);
        if (! $ref || ! is_string($visitor) || ! preg_match('/^[a-f0-9]{32}$/', $visitor)) {
            return;
        }

        $inserted = DB::table('marketing_starts')->insertOrIgnore([
            'marketing_link_id' => $ref['link']->id,
            'visitor' => $visitor,
            'created_at' => now(),
        ]);
        if ($inserted) {
            MarketingLink::query()->whereKey($ref['link']->id)->increment('starts_count');
        }
    }

    /** Yangi foydalanuvchi qayerdan kelganini yozadi; taklif qilgan do‘stga xabar beradi. */
    public function attribute(User $user, Request $request): void
    {
        $ref = $this->activeRef($request);
        $inviter = $this->activeInviter($request, $user);
        $utm = $this->cookieJson($request, self::COOKIE_UTM);
        $site = $this->cookieJson($request, self::COOKIE_SOURCE);

        // Havola va taklif ikkalasi bo‘lsa — oxirgi bosilgani.
        $useInvite = $inviter && (! $ref || $inviter['t'] >= $ref['t']);

        [$source, $detail, $linkId] = match (true) {
            $useInvite => ['invite', '@'.$inviter['user']->username, null],
            (bool) $ref => ['link', $ref['link']->code, $ref['link']->id],
            $this->fresh($utm) && ($utm['s'] ?? '') !== '' => ['utm', trim(implode(' / ', array_filter([$utm['s'], $utm['c'] ?? null])), ' /'), null],
            $this->fresh($site) && ($site['h'] ?? '') !== '' => ['referrer', $site['h'], null],
            default => ['direct', null, null],
        };

        $user->forceFill([
            'acquisition_source' => $source,
            'acquisition_detail' => $detail ? mb_substr($detail, 0, 120) : null,
            'acquisition_link_id' => $linkId,
            'referred_by' => $inviter['user']->id ?? null,
        ])->save();

        if ($linkId) {
            MarketingLink::query()->whereKey($linkId)->increment('signups_count');
        }
        if ($inviter) {
            $this->notifications->notify($inviter['user'], NotificationType::System, null, null, [
                'message' => $user->name.' (@'.$user->username.') sizning taklifingiz bilan Fikrlash’ga qo‘shildi. Rahmat!',
            ]);
        }

        foreach ([self::COOKIE_REF, self::COOKIE_INVITE, self::COOKIE_UTM, self::COOKIE_SOURCE] as $name) {
            Cookie::queue(Cookie::forget($name));
        }
        // Pixel / Analytics: keyingi sahifada "ro‘yxatdan o‘tdi" hodisasi yuboriladi.
        session()->flash('marketing_event', 'signup');
    }

    /* ---------------- Do‘st taklifi ---------------- */

    public function inviteUrl(User $user): string
    {
        return route('invite', $user->username);
    }

    public function rememberInviter(User $inviter): void
    {
        $this->queue(self::COOKIE_INVITE, ['u' => $inviter->id, 't' => now()->timestamp]);
    }

    /** Ro‘yxatdan o‘tish sahifasidagi salomlashuv: kampaniya matni yoki "@x sizni taklif qildi". */
    public function landing(Request $request): array
    {
        $inviter = $this->activeInviter($request);
        $ref = $this->activeRef($request);

        return [
            'inviter' => $inviter['user'] ?? null,
            'welcome' => ! $inviter && $ref ? $ref['link']->welcome : null,
        ];
    }

    /* ---------------- Statistika ---------------- */

    /** Voronka: bosishlar → noyob tashrifchilar → ro‘yxatni boshlaganlar → ro‘yxatdan o‘tganlar → faollashganlar. */
    public function funnel(Carbon $since, ?MarketingLink $link = null): array
    {
        $visits = MarketingVisit::query()->where('created_at', '>=', $since)->when($link, fn ($q) => $q->where('marketing_link_id', $link->id));
        $starts = DB::table('marketing_starts')->where('created_at', '>=', $since)->when($link, fn ($q) => $q->where('marketing_link_id', $link->id));
        $users = User::query()->where('created_at', '>=', $since)
            ->when($link, fn ($q) => $q->where('acquisition_link_id', $link->id), fn ($q) => $q->whereNotNull('acquisition_link_id'));

        $signups = (clone $users)->count();
        $visitors = (clone $visits)->distinct()->count('visitor');

        return [
            'clicks' => (clone $visits)->count(),
            'visitors' => $visitors,
            'starts' => $starts->count(),
            'signups' => $signups,
            'activated' => (clone $users)->where(fn ($q) => $q->whereHas('posts')->orWhereHas('comments'))->count(),
            'returned' => (clone $users)->whereNotNull('last_active_at')->whereRaw('last_active_at > '.$this->plusDay('created_at'))->count(),
            'members' => (clone $visits)->whereNotNull('user_id')->distinct()->count('user_id'),
            'conversion' => $visitors ? round($signups / $visitors * 100, 1) : null,
        ];
    }

    /** Barcha yangi foydalanuvchilar manbalar bo‘yicha (davr ichida). */
    public function sources(Carbon $since): Collection
    {
        return User::query()->where('created_at', '>=', $since)
            ->selectRaw("COALESCE(acquisition_source, 'unknown') as source, COUNT(*) as total")
            ->groupBy('source')->orderByDesc('total')
            ->pluck('total', 'source')
            ->map(fn ($n) => (int) $n);
    }

    /** Kunma-kun: bosishlar va ro‘yxatdan o‘tishlar (barcha yangi a'zolar + havolalar orqali). */
    public function daily(int $days, ?MarketingLink $link = null): array
    {
        $since = today()->subDays($days - 1);
        $dates = collect(range($days - 1, 0))->map(fn ($d) => today()->subDays($d)->toDateString());

        $clicks = MarketingVisit::query()->where('created_at', '>=', $since)
            ->when($link, fn ($q) => $q->where('marketing_link_id', $link->id))
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')->groupBy('d')->pluck('c', 'd');
        $signups = User::query()->where('created_at', '>=', $since)
            ->when($link, fn ($q) => $q->where('acquisition_link_id', $link->id), fn ($q) => $q->whereNotNull('acquisition_link_id'))
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')->groupBy('d')->pluck('c', 'd');
        $all = $link ? collect() : User::query()->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')->groupBy('d')->pluck('c', 'd');

        $series = fn ($rows) => $dates->map(fn ($d) => (int) ($rows[$d] ?? 0))->all();

        return ['dates' => $dates->all(), 'clicks' => $series($clicks), 'signups' => $series($signups), 'all' => $series($all)];
    }

    /** Qurilma / OT / ilova (Telegram, Instagram ichida ochilgan) / qaysi saytdan — taqsimot. */
    public function breakdown(MarketingLink $link, string $field, int $limit = 6): Collection
    {
        abort_unless(in_array($field, ['device', 'os', 'browser', 'app', 'referrer'], true), 400);

        return MarketingVisit::query()->where('marketing_link_id', $link->id)
            ->selectRaw("COALESCE({$field}, '') as k, COUNT(*) as c")->groupBy('k')->orderByDesc('c')->limit($limit)
            ->pluck('c', 'k')->map(fn ($n) => (int) $n);
    }

    /* ---------------- Ichki ---------------- */

    /** @return array{link: MarketingLink, visit: int|null, t: int}|null */
    private function activeRef(Request $request): ?array
    {
        $data = $this->cookieJson($request, self::COOKIE_REF);
        if (! $this->fresh($data) || ! isset($data['l'])) {
            return null;
        }
        $link = MarketingLink::withTrashed()->find((int) $data['l']);

        return $link ? ['link' => $link, 'visit' => $data['v'] ?? null, 't' => (int) $data['t']] : null;
    }

    /** @return array{user: User, t: int}|null */
    private function activeInviter(Request $request, ?User $newUser = null): ?array
    {
        if (! $this->invitesEnabled()) {
            return null;
        }
        $data = $this->cookieJson($request, self::COOKIE_INVITE);
        if (! $this->fresh($data) || ! isset($data['u'])) {
            return null;
        }
        $inviter = User::query()->where('status', UserStatus::Active)->find((int) $data['u']);
        if (! $inviter || ($newUser && $inviter->is($newUser))) {
            return null;
        }

        return ['user' => $inviter, 't' => (int) $data['t']];
    }

    private function fresh(?array $data): bool
    {
        return $data && isset($data['t']) && (int) $data['t'] >= now()->subDays($this->attributionDays())->timestamp;
    }

    private function cookieJson(Request $request, string $name): ?array
    {
        $raw = $request->cookie($name);
        $data = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($data) ? $data : null;
    }

    private function queue(string $name, array $value): void
    {
        Cookie::queue($name, json_encode($value), $this->attributionDays() * 1440, null, null, null, true, false, 'lax');
    }

    private function visitorId(Request $request): string
    {
        $id = $request->attributes->get(self::COOKIE_VISITOR) ?? $request->cookie(self::COOKIE_VISITOR);
        if (! is_string($id) || ! preg_match('/^[a-f0-9]{32}$/', $id)) {
            $id = bin2hex(random_bytes(16));
            Cookie::queue(self::COOKIE_VISITOR, $id, 525600, null, null, null, true, false, 'lax');
        }
        $request->attributes->set(self::COOKIE_VISITOR, $id);

        return $id;
    }

    /** Boshqa saytdan kelgan bo‘lsa — uning nomi (www.siz): t.me, instagram.com, l.facebook.com… */
    private function externalHost(Request $request): ?string
    {
        $host = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);
        if (! $host) {
            return null;
        }
        $host = mb_strtolower(preg_replace('/^www\./', '', $host));
        $own = mb_strtolower(preg_replace('/^www\./', '', (string) $request->getHost()));

        return $host === $own || str_ends_with($host, '.'.$own) ? null : mb_substr($host, 0, 120);
    }

    /** @return array{device: string, os: string, browser: string, app: string|null} */
    public function parseAgent(string $ua): array
    {
        $app = match (true) {
            str_contains($ua, 'FikrlashApp') => 'fikrlash',
            (bool) preg_match('/Telegram/i', $ua) => 'telegram',
            (bool) preg_match('/Instagram/i', $ua) => 'instagram',
            (bool) preg_match('/FBAN|FBAV|FB_IAB/', $ua) => 'facebook',
            (bool) preg_match('/musical_ly|TikTok|BytedanceWebview/i', $ua) => 'tiktok',
            default => null,
        };
        $os = match (true) {
            (bool) preg_match('/Android/i', $ua) => 'android',
            (bool) preg_match('/iPhone|iPad|iPod/i', $ua) => 'ios',
            (bool) preg_match('/Windows/i', $ua) => 'windows',
            (bool) preg_match('/Macintosh|Mac OS X/i', $ua) => 'mac',
            (bool) preg_match('/Linux|CrOS/i', $ua) => 'linux',
            default => 'other',
        };
        $device = match (true) {
            (bool) preg_match('/iPad|Tablet/i', $ua), $os === 'android' && ! preg_match('/Mobile/i', $ua) => 'tablet',
            (bool) preg_match('/Mobi|iPhone|Android/i', $ua) => 'mobile',
            default => 'desktop',
        };
        $browser = match (true) {
            (bool) preg_match('/YaBrowser/i', $ua) => 'yandex',
            (bool) preg_match('/SamsungBrowser/i', $ua) => 'samsung',
            (bool) preg_match('/OPR\/|Opera/i', $ua) => 'opera',
            (bool) preg_match('/Edg\//', $ua) => 'edge',
            (bool) preg_match('/Firefox|FxiOS/i', $ua) => 'firefox',
            (bool) preg_match('/Chrome|CriOS/i', $ua) => 'chrome',
            (bool) preg_match('/Safari/i', $ua) => 'safari',
            default => 'other',
        };

        return compact('device', 'os', 'browser', 'app');
    }

    /** "created_at + 1 kun" — SQLite va MySQL uchun. */
    private function plusDay(string $column): string
    {
        return DB::getDriverName() === 'sqlite' ? "datetime({$column}, '+1 day')" : "DATE_ADD({$column}, INTERVAL 1 DAY)";
    }
}
