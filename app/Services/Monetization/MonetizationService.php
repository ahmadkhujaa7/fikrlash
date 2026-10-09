<?php

namespace App\Services\Monetization;

use App\Enums\NotificationType;
use App\Enums\PostStatus;
use App\Exceptions\MonetizationException;
use App\Models\AuthorApplication;
use App\Models\AuthorEarning;
use App\Models\AuthorPayout;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Services\Social\AuditLogger;
use App\Services\Social\NotificationService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Maqola mualliflari monetizatsiyasi.
 *
 * 1) Talablar (admin belgilaydi): obunachilar, maqolalar ko‘rishlari va maqolalar soni.
 *    Talabga yetgan foydalanuvchi so‘rov yuboradi, admin tasdiqlaydi → users.monetized_at.
 * 2) Daromad: tasdiqlangandan KEYIN chop etilgan maqolalarning ko‘rishlari uchun.
 *    Ko‘rish = ro‘yxatdan o‘tgan foydalanuvchining shu maqolani birinchi ochishi (post_views),
 *    kamida N soniya o‘qilgan (sozlanadi), muallifning o‘zi hisoblanmaydi.
 *    Har "rate_views" ko‘rish uchun "rate_amount" so‘m. Narx o‘zgarsa — oldingi daromad o‘zgarmaydi
 *    (har kun/maqola uchun o‘sha paytdagi narxda yoziladi: author_earnings).
 * 3) Balans = daromad − (kutilayotgan + to‘langan) yechimlar. Minimal yechish summasini admin belgilaydi.
 */
class MonetizationService
{
    /** O‘qish vaqti yig‘ilishi uchun ko‘rishlar shuncha kechikib hisoblanadi. */
    public const ACCRUAL_DELAY_MINUTES = 30;

    public const DEFAULTS = [
        'monetization_enabled' => true,
        'monetization_min_followers' => 100,
        'monetization_min_views' => 5000,
        'monetization_min_articles' => 3,
        'monetization_rate_views' => 1000,
        'monetization_rate_amount' => 5000,
        'monetization_min_payout' => 100000,
        'monetization_min_read_seconds' => 15,
        'monetization_terms' => null,
    ];

    public function __construct(private NotificationService $notifications, private AuditLogger $audit) {}

    /** @return array{enabled: bool, min_followers: int, min_views: int, min_articles: int, rate_views: int, rate_amount: float, min_payout: float, min_read_seconds: int, terms: ?string} */
    public function settings(): array
    {
        $get = fn (string $key) => Setting::read($key, self::DEFAULTS[$key]);

        return [
            'enabled' => (bool) $get('monetization_enabled'),
            'min_followers' => max(0, (int) $get('monetization_min_followers')),
            'min_views' => max(0, (int) $get('monetization_min_views')),
            'min_articles' => max(0, (int) $get('monetization_min_articles')),
            'rate_views' => max(1, (int) $get('monetization_rate_views')),
            'rate_amount' => max(0, (float) $get('monetization_rate_amount')),
            'min_payout' => max(0, (float) $get('monetization_min_payout')),
            'min_read_seconds' => max(0, (int) $get('monetization_min_read_seconds')),
            'terms' => $get('monetization_terms'),
        ];
    }

    /* ---------------- Talablar va so‘rov ---------------- */

    /** Foydalanuvchining hozirgi ko‘rsatkichlari va talablar. */
    public function eligibility(User $user): array
    {
        $s = $this->settings();
        $articles = $user->posts()->where('type', Post::TYPE_ARTICLE)->where('status', PostStatus::Published);
        $current = [
            'followers' => (int) $user->followers_count,
            'views' => (int) (clone $articles)->sum('views_count'),
            'articles' => (int) (clone $articles)->count(),
        ];
        $required = ['followers' => $s['min_followers'], 'views' => $s['min_views'], 'articles' => $s['min_articles']];

        $checks = [];
        foreach ($current as $key => $value) {
            $checks[$key] = [
                'current' => $value,
                'required' => $required[$key],
                'ok' => $value >= $required[$key],
                'percent' => $required[$key] > 0 ? min(100, (int) floor($value / $required[$key] * 100)) : 100,
            ];
        }

        return ['checks' => $checks, 'eligible' => collect($checks)->every(fn ($c) => $c['ok'])];
    }

    public function pendingApplication(User $user): ?AuthorApplication
    {
        return $user->authorApplications()->where('status', AuthorApplication::PENDING)->latest('id')->first();
    }

    public function apply(User $user, ?string $message = null): AuthorApplication
    {
        if (! $this->settings()['enabled']) {
            throw new MonetizationException('Monetizatsiya dasturi hozircha yopiq.');
        }
        if ($user->isMonetized()) {
            throw new MonetizationException('Siz allaqachon muallifsiz.');
        }
        if ($this->pendingApplication($user)) {
            throw new MonetizationException('So‘rovingiz ko‘rib chiqilmoqda.');
        }
        $e = $this->eligibility($user);
        if (! $e['eligible']) {
            throw new MonetizationException('Hali talablarga yetmagansiz.');
        }

        return $user->authorApplications()->create([
            'status' => AuthorApplication::PENDING,
            'message' => $message ? trim($message) : null,
            'followers' => $e['checks']['followers']['current'],
            'article_views' => $e['checks']['views']['current'],
            'articles' => $e['checks']['articles']['current'],
        ]);
    }

    public function approve(AuthorApplication $application, User $admin, ?string $note = null): void
    {
        DB::transaction(function () use ($application, $admin, $note) {
            $application->forceFill(['status' => AuthorApplication::APPROVED, 'admin_note' => $note, 'reviewed_by' => $admin->id, 'reviewed_at' => now()])->save();
            $user = $application->user;
            if ($user && ! $user->isMonetized()) {
                $user->forceFill(['monetized_at' => now()])->save();
            }
        });

        $this->notify($application->user, 'Tabriklaymiz! Siz Fikrlash muallifi bo‘ldingiz. Endi chop etadigan maqolalaringiz ko‘rishlari uchun daromad hisoblanadi.'.($note ? ' Izoh: '.$note : ''));
        $this->audit->log('monetization.approved', $application->user, [], ['application' => $application->id, 'note' => $note], $admin);
    }

    public function reject(AuthorApplication $application, User $admin, string $reason): void
    {
        $application->forceFill(['status' => AuthorApplication::REJECTED, 'admin_note' => $reason, 'reviewed_by' => $admin->id, 'reviewed_at' => now()])->save();
        $this->notify($application->user, 'Muallif bo‘lish so‘rovingiz hozircha qabul qilinmadi. Sabab: '.$reason);
        $this->audit->log('monetization.rejected', $application->user, [], ['application' => $application->id, 'reason' => $reason], $admin);
    }

    /**
     * Admin so‘rovsiz, to‘g‘ridan-to‘g‘ri muallif qiladi (foydalanuvchilar sahifasidan).
     * Talablar tekshirilmaydi; ko‘rib chiqilayotgan so‘rov bo‘lsa — o‘sha tasdiqlanadi.
     */
    public function grant(User $user, User $admin, ?string $note = null): void
    {
        if ($user->isMonetized()) {
            return;
        }

        if ($pending = $this->pendingApplication($user)) {
            $this->approve($pending, $admin, $note);

            return;
        }

        $e = $this->eligibility($user)['checks'];
        $application = DB::transaction(function () use ($user, $admin, $note, $e) {
            $application = $user->authorApplications()->create([
                'status' => AuthorApplication::APPROVED,
                'followers' => $e['followers']['current'],
                'article_views' => $e['views']['current'],
                'articles' => $e['articles']['current'],
                'admin_note' => $note ?: 'Admin tomonidan berildi',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);
            $user->forceFill(['monetized_at' => now()])->save();

            return $application;
        });

        $this->notify($user, 'Tabriklaymiz! Sizga Fikrlash muallifi maqomi berildi. Endi chop etadigan maqolalaringiz ko‘rishlari uchun daromad hisoblanadi.'.($note ? ' Izoh: '.$note : ''));
        $this->audit->log('monetization.granted', $user, [], ['application' => $application->id, 'note' => $note], $admin);
    }

    /**
     * Monetizatsiyani to‘xtatish: yangi daromad hisoblanmaydi, mavjud balans saqlanadi.
     * Foydalanuvchi keyin talablarga javob bersa, qayta so‘rov yubora oladi.
     */
    public function revoke(User $user, User $admin, ?string $reason = null): void
    {
        $reason = trim((string) $reason) ?: null;

        DB::transaction(function () use ($user, $admin, $reason) {
            $user->forceFill(['monetized_at' => null])->save();
            $user->authorApplications()->where('status', AuthorApplication::APPROVED)
                ->update(['status' => AuthorApplication::REVOKED, 'admin_note' => $reason, 'reviewed_by' => $admin->id, 'reviewed_at' => now()]);
        });
        $this->notify($user, 'Monetizatsiya to‘xtatildi.'.($reason ? ' Sabab: '.$reason.'.' : '')
            .' Balansdagi mablag‘ saqlanadi. Talablarga javob bersangiz, qayta so‘rov yuborishingiz mumkin.');
        $this->audit->log('monetization.revoked', $user, [], ['reason' => $reason], $admin);
    }

    /* ---------------- Daromad hisoblash ---------------- */

    /**
     * Yangi ko‘rishlarni daromadga aylantiradi (rejalashtiruvchi har 10 daqiqada, muallif paneli ochilganda ham).
     * Qayerdan davom etish — settings: monetization_accrued_until. Qayta ishga tushirilsa ikki marta hisoblamaydi.
     */
    public function accrue(?CarbonInterface $now = null): int
    {
        $lock = Cache::lock('monetization:accrue', 300);
        if (! $lock->get()) {
            return 0;
        }

        try {
            $until = Carbon::instance(($now ?? now())->copy())->subMinutes(self::ACCRUAL_DELAY_MINUTES);
            $cursor = Setting::read('monetization_accrued_until');
            $from = $cursor ? Carbon::parse($cursor) : $this->firstMonetizedAt();
            if (! $from || $from->gte($until)) {
                if (! $cursor && $from) {
                    Setting::write('monetization_accrued_until', $from->toIso8601String());
                }

                return 0;
            }

            $s = $this->settings();
            $credited = 0;
            if ($s['enabled'] && $s['rate_amount'] > 0) {
                $rows = DB::table('post_views as v')
                    ->join('posts as p', 'p.id', '=', 'v.post_id')
                    ->join('users as u', 'u.id', '=', 'p.user_id')
                    ->whereNotNull('u.monetized_at')
                    ->where('p.type', Post::TYPE_ARTICLE)
                    ->where('p.status', PostStatus::Published->value)
                    ->whereNull('p.deleted_at')
                    ->whereColumn('p.published_at', '>=', 'u.monetized_at')
                    ->whereColumn('v.first_viewed_at', '>=', 'u.monetized_at')
                    ->whereColumn('v.user_id', '!=', 'p.user_id')
                    ->where('v.first_viewed_at', '>', $from)
                    ->where('v.first_viewed_at', '<=', $until)
                    ->where('v.read_seconds', '>=', $s['min_read_seconds'])
                    ->groupBy('p.user_id', 'v.post_id', DB::raw('DATE(v.first_viewed_at)'))
                    ->selectRaw('p.user_id, v.post_id, DATE(v.first_viewed_at) as day, COUNT(*) as views')
                    ->get();

                DB::transaction(function () use ($rows, $s, &$credited) {
                    foreach ($rows as $row) {
                        $amount = round($row->views * $s['rate_amount'] / $s['rate_views'], 2);
                        $earning = AuthorEarning::query()->where('post_id', $row->post_id)->whereDate('date', $row->day)->first()
                            ?? new AuthorEarning(['post_id' => $row->post_id, 'date' => $row->day]);
                        $earning->user_id = $row->user_id;
                        $earning->views = (int) $earning->views + (int) $row->views;
                        $earning->amount = round((float) $earning->amount + $amount, 2);
                        $earning->save();
                        $credited += (int) $row->views;
                    }
                });
            }

            Setting::write('monetization_accrued_until', $until->toIso8601String());

            return $credited;
        } finally {
            $lock->release();
        }
    }

    private function firstMonetizedAt(): ?Carbon
    {
        $first = User::query()->whereNotNull('monetized_at')->min('monetized_at');

        return $first ? Carbon::parse($first) : null;
    }

    /* ---------------- Balans va yechish ---------------- */

    /** @return array{earned: float, held: float, paid: float, pending: float, balance: float} */
    public function balance(User $user): array
    {
        $earned = (float) $user->authorEarnings()->sum('amount');
        $paid = (float) $user->authorPayouts()->where('status', AuthorPayout::PAID)->sum('amount');
        $pending = (float) $user->authorPayouts()->where('status', AuthorPayout::PENDING)->sum('amount');

        return [
            'earned' => round($earned, 2),
            'paid' => round($paid, 2),
            'pending' => round($pending, 2),
            'held' => round($paid + $pending, 2),
            'balance' => round(max(0, $earned - $paid - $pending), 2),
        ];
    }

    public function requestPayout(User $user, float $amount, string $account, ?string $holder): AuthorPayout
    {
        $s = $this->settings();
        $amount = round($amount, 2);

        return DB::transaction(function () use ($user, $amount, $account, $holder, $s) {
            // Bir vaqtda ikki so‘rov balansdan ortiq yechmasin.
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            if ($user->authorPayouts()->where('status', AuthorPayout::PENDING)->exists()) {
                throw new MonetizationException('Oldingi so‘rovingiz hali ko‘rib chiqilmoqda.');
            }
            $balance = $this->balance($user)['balance'];
            if ($amount < $s['min_payout']) {
                throw new MonetizationException('Eng kam yechish summasi — '.self::money($s['min_payout']).'.');
            }
            if ($amount > $balance) {
                throw new MonetizationException('Balansda yetarli mablag‘ yo‘q (mavjud: '.self::money($balance).').');
            }

            return $user->authorPayouts()->create([
                'amount' => $amount,
                'status' => AuthorPayout::PENDING,
                'method' => 'card',
                'account' => preg_replace('/\s+/', '', $account),
                'holder' => $holder ? trim($holder) : null,
            ]);
        });
    }

    public function cancelPayout(AuthorPayout $payout): void
    {
        if ($payout->status !== AuthorPayout::PENDING) {
            throw new MonetizationException('Bu so‘rovni endi bekor qilib bo‘lmaydi.');
        }
        $payout->forceFill(['status' => AuthorPayout::CANCELLED])->save();
    }

    public function markPaid(AuthorPayout $payout, User $admin, ?string $reference = null, ?string $note = null): void
    {
        $payout->forceFill(['status' => AuthorPayout::PAID, 'reference' => $reference, 'admin_note' => $note, 'processed_by' => $admin->id, 'processed_at' => now()])->save();
        $this->notify($payout->user, self::money((float) $payout->amount).' kartangizga ('.$payout->maskedAccount().') o‘tkazildi.');
        $this->audit->log('monetization.payout_paid', $payout->user, [], ['payout' => $payout->id, 'amount' => (float) $payout->amount, 'reference' => $reference], $admin);
    }

    public function rejectPayout(AuthorPayout $payout, User $admin, string $reason): void
    {
        $payout->forceFill(['status' => AuthorPayout::REJECTED, 'admin_note' => $reason, 'processed_by' => $admin->id, 'processed_at' => now()])->save();
        $this->notify($payout->user, 'Pul yechish so‘rovingiz ('.self::money((float) $payout->amount).') rad etildi, summa balansingizga qaytdi. Sabab: '.$reason);
        $this->audit->log('monetization.payout_rejected', $payout->user, [], ['payout' => $payout->id, 'reason' => $reason], $admin);
    }

    /* ---------------- Statistika ---------------- */

    /** Kunlik ko‘rish va daromad (oxirgi N kun, bo‘sh kunlar 0). */
    public function daily(User $user, int $days = 30): Collection
    {
        $start = today()->subDays($days - 1);
        $rows = $user->authorEarnings()->where('date', '>=', $start)
            ->selectRaw('date, SUM(views) as views, SUM(amount) as amount')->groupBy('date')->get()
            ->keyBy(fn ($r) => Carbon::parse($r->date)->toDateString());

        return collect(range(0, $days - 1))->map(function (int $i) use ($start, $rows) {
            $day = $start->copy()->addDays($i)->toDateString();

            return ['date' => $day, 'views' => (int) ($rows[$day]->views ?? 0), 'amount' => round((float) ($rows[$day]->amount ?? 0), 2)];
        });
    }

    /** Maqolalar: ko‘rishlar va daromad (tasdiqlangandan keyingilari daromadli). */
    public function articles(User $user, int $limit = 50): Collection
    {
        $earnings = $user->authorEarnings()->selectRaw('post_id, SUM(views) as views, SUM(amount) as amount')->groupBy('post_id')->get()->keyBy('post_id');

        return $user->posts()->where('type', Post::TYPE_ARTICLE)->where('status', PostStatus::Published)
            ->latest('published_at')->limit($limit)->get()
            ->map(fn (Post $post) => [
                'post' => $post,
                'paid_views' => (int) ($earnings[$post->id]->views ?? 0),
                'amount' => round((float) ($earnings[$post->id]->amount ?? 0), 2),
                'monetized' => $user->monetized_at && $post->published_at && $post->published_at->gte($user->monetized_at),
            ]);
    }

    public static function money(float $amount): string
    {
        $decimals = fmod($amount, 1.0) == 0.0 ? 0 : 2;

        return number_format($amount, $decimals, ',', ' ').' so‘m';
    }

    private function notify(?User $user, string $message): void
    {
        if ($user) {
            $this->notifications->notify($user, NotificationType::Monetization, null, null, ['message' => $message]);
        }
    }
}
