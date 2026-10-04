<?php

namespace App\Services\Feed;

use App\Models\Post;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Foydalanuvchi didi (taste profile) — faqat xatti-harakatdan o‘rganiladi.
 *
 * Har bir post uchta xususiyatga ega: kategoriya (AI aniqlaydi), teglar (#hashtag + AI kalit so‘zlari) va muallif.
 * Post ko‘rsatilganda   → shu xususiyatlarning exposures qiymati oshadi;
 * foydalanuvchi javob bersa (ochish, o‘qish, like, izoh, saqlash) → score oshadi;
 * "Qiziq emas"          → exposures keskin oshadi (javobsiz ko‘rsatish = salbiy signal).
 *
 * Qiziqish darajasi = Bayes bilan silliqlangan javob nisbati, o‘rtachaga nisbatan "lift":
 *     rate = (score + k·μ) / (exposures + k),   lift = rate / μ   (μ — o‘rtacha javob darajasi)
 * lift > 1 — o‘rtachadan ko‘proq qiziqadi, lift < 1 — kamroq. Ma'lumot kam bo‘lsa lift ≈ 1 (neytral).
 */
class TasteService
{
    public const CATEGORY = 'category';

    public const TAG = 'tag';

    public const AUTHOR = 'author';

    /** Foydalanuvchi postga ijobiy javob berdi. */
    public function engage(int $userId, Post $post, string $signal): void
    {
        $amount = (float) config("fikrlash.taste.signals.{$signal}", 0);
        if ($amount <= 0 || $post->user_id === $userId) {
            return;
        }

        $this->write($userId, $this->features($post), score: $amount);
    }

    /** Post foydalanuvchiga ko‘rsatildi (lentada ko‘rindi yoki ochildi). */
    public function expose(int $userId, Post $post): void
    {
        if ($post->user_id === $userId) {
            return;
        }

        $this->write($userId, $this->features($post), exposures: 1.0);
    }

    /** "Qiziq emas": shu turdagi postlar kamroq chiqadi, post o‘zi esa yashiriladi. */
    public function notInterested(int $userId, Post $post): void
    {
        $this->write($userId, $this->features($post), exposures: (float) config('fikrlash.taste.not_interested'));

        DB::table('post_views')->upsert(
            [['user_id' => $userId, 'post_id' => $post->id, 'first_viewed_at' => now(), 'last_viewed_at' => now(), 'dismissed_at' => now()]],
            ['user_id', 'post_id'],
            ['dismissed_at'],
        );
    }

    /** Muallifga obuna — muallif vazniga kuchli ijobiy signal. */
    public function followAuthor(int $userId, int $authorId): void
    {
        $this->write($userId, [[self::AUTHOR, $authorId, 1.0]], score: (float) config('fikrlash.taste.signals.follow'));
    }

    /**
     * Ranking uchun profil: har bir xususiyat bo‘yicha lift va exposures.
     *
     * @return array{category: array<int, float>, tag: array<int, float>, author: array<int, float>, seen: array<int, float>, empty: bool}
     */
    public function profile(int $userId): array
    {
        return Cache::remember($this->key($userId), now()->addMinutes(5), function () use ($userId) {
            $profile = [self::CATEGORY => [], self::TAG => [], self::AUTHOR => [], 'seen' => [], 'empty' => true];

            DB::table('user_affinities')->where('user_id', $userId)
                ->get(['kind', 'target_id', 'score', 'exposures'])
                ->each(function ($row) use (&$profile) {
                    $profile[$row->kind][(int) $row->target_id] = $this->lift((float) $row->score, (float) $row->exposures);
                    if ($row->kind === self::CATEGORY) {
                        $profile['seen'][(int) $row->target_id] = (float) $row->exposures;
                    }
                    $profile['empty'] = false;
                });

            return $profile;
        });
    }

    /**
     * Foydalanuvchi eng ko‘p qiziqadigan kategoriyalar (sidebar'da "lentangiz nimaga moslashgan").
     *
     * @return array<int, float> category_id => lift
     */
    public function topCategories(int $userId, int $limit = 4): array
    {
        $lifts = array_filter($this->profile($userId)[self::CATEGORY], fn ($lift) => $lift > 1.05);
        arsort($lifts);

        return array_slice($lifts, 0, $limit, true);
    }

    public function lift(float $score, float $exposures): float
    {
        $mu = (float) config('fikrlash.taste.prior_rate');
        $k = (float) config('fikrlash.taste.prior_strength');
        $rate = ($score + $k * $mu) / ($exposures + $k);

        return round(max((float) config('fikrlash.taste.lift_min'), min((float) config('fikrlash.taste.lift_max'), $rate / $mu)), 4);
    }

    /** Haftalik: eski qiziqishlar asta-sekin unutiladi (did o‘zgarishi mumkin). */
    public function decay(): void
    {
        $f = sprintf('%.4F', (float) config('fikrlash.taste.weekly_decay'));
        DB::table('user_affinities')->update(['score' => DB::raw("score * {$f}"), 'exposures' => DB::raw("exposures * {$f}")]);
        DB::table('user_affinities')->where('score', '<', 0.05)->where('exposures', '<', 0.5)->delete();
    }

    public function forget(int $userId): void
    {
        Cache::forget($this->key($userId));
    }

    /**
     * Postning xususiyatlari va ularning ulushi (teglar vazni teng bo‘linadi).
     *
     * @return list<array{0: string, 1: int, 2: float}>
     */
    public function features(Post $post): array
    {
        $features = [[self::AUTHOR, (int) $post->user_id, 1.0]];
        if ($post->category_id) {
            $features[] = [self::CATEGORY, (int) $post->category_id, 1.0];
        }

        $tagIds = $post->relationLoaded('tags')
            ? $post->tags->pluck('id')->all()
            : DB::table('post_tag')->where('post_id', $post->id)->pluck('tag_id')->all();
        $tagIds = array_slice($tagIds, 0, 5);
        foreach ($tagIds as $tagId) {
            $features[] = [self::TAG, (int) $tagId, 1 / max(1, count($tagIds))];
        }

        return $features;
    }

    /** @param  list<array{0: string, 1: int, 2: float}>  $features */
    private function write(int $userId, array $features, float $score = 0, float $exposures = 0): void
    {
        if ($features === []) {
            return;
        }

        $now = now();
        $rows = array_map(fn ($f) => [
            'user_id' => $userId, 'kind' => $f[0], 'target_id' => $f[1],
            'score' => round($score * $f[2], 4), 'exposures' => round($exposures * $f[2], 4), 'updated_at' => $now,
        ], $features);

        DB::table('user_affinities')->upsert($rows, ['user_id', 'kind', 'target_id'], [
            'score' => DB::raw($this->increment('score')),
            'exposures' => DB::raw($this->increment('exposures')),
            'updated_at' => $now,
        ]);

        $this->forget($userId);
    }

    private function key(int $userId): string
    {
        return "taste:{$userId}";
    }

    /** Upsert'da mavjud qiymatga qo‘shish (MySQL va SQLite sintaksisi farq qiladi). */
    private function increment(string $column): string
    {
        return DB::connection()->getDriverName() === 'mysql'
            ? "user_affinities.{$column} + VALUES({$column})"
            : "user_affinities.{$column} + excluded.{$column}";
    }
}
