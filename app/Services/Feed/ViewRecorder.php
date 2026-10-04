<?php

namespace App\Services\Feed;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Haqiqiy ko‘rishlar statistikasi.
 *  - Bitta tomoshabin (user yoki IP+UA hash) bitta postni 30 daqiqa ichida faqat bir marta hisoblanadi.
 *  - Muallifning o‘z ko‘rishlari hisoblanmaydi.
 *  - "redis" buffer rejimida hisoblagichlar Redis'da yig‘iladi va har daqiqada DB'ga bitta UPDATE bilan yoziladi.
 */
class ViewRecorder
{
    private const BUFFER_KEY = 'post_views_buffer';

    public function __construct(private TasteService $taste) {}

    public function record(Post $post, ?User $viewer, string $fingerprint): bool
    {
        if ($viewer && $post->user_id === $viewer->id) {
            return false;
        }

        $who = $viewer ? 'u'.$viewer->id : 'g'.substr(hash('sha256', $fingerprint), 0, 24);
        $isNew = Cache::add("pv:{$post->id}:{$who}", 1, now()->addMinutes(config('fikrlash.views.dedupe_minutes')));

        if (! $isNew) {
            return false;
        }

        $this->increment($post->id);

        if ($viewer) {
            $now = now();
            DB::table('post_views')->upsert(
                [['user_id' => $viewer->id, 'post_id' => $post->id, 'first_viewed_at' => $now, 'last_viewed_at' => $now]],
                ['user_id', 'post_id'],
                ['last_viewed_at'],
            );
            $this->taste->expose($viewer->id, $post);
        }

        return true;
    }

    /** O‘qish vaqti (soniya) — tavsiya tizimi uchun signal. */
    public function recordReadTime(Post $post, User $viewer, int $seconds): void
    {
        $seconds = max(0, min($seconds, (int) config('fikrlash.views.max_read_seconds')));
        if ($seconds === 0 || $post->user_id === $viewer->id) {
            return;
        }

        $max = (int) config('fikrlash.views.max_read_seconds');
        DB::table('post_views')
            ->where(['user_id' => $viewer->id, 'post_id' => $post->id])
            ->update([
                'read_seconds' => DB::raw("CASE WHEN read_seconds + {$seconds} > {$max} THEN {$max} ELSE read_seconds + {$seconds} END"),
                'last_viewed_at' => now(),
            ]);

        // Uzoq o‘qilgan post — kuchli qiziqish signali (bir postdan kuniga bir marta).
        if ($seconds >= 20 && Cache::add("taste:read:{$viewer->id}:{$post->id}", 1, now()->addDay())) {
            $this->taste->engage($viewer->id, $post, 'read');
        }
    }

    /** Post sahifasi ochildi — foydalanuvchi postni bosib kirdi (kuniga bir marta hisoblanadi). */
    public function recordOpen(Post $post, User $viewer): void
    {
        if ($post->user_id !== $viewer->id && Cache::add("taste:open:{$viewer->id}:{$post->id}", 1, now()->addDay())) {
            $this->taste->engage($viewer->id, $post, 'open');
        }
    }

    /**
     * Lentada kartochka ustida to‘xtash vaqti (soniya). Tez o‘tkazib yuborilgan post — signal yo‘q
     * (exposure allaqachon yozilgan, demak nisbat pasayadi); to‘xtab o‘qilgan post — ijobiy signal.
     */
    public function recordDwell(Post $post, User $viewer, int $seconds): void
    {
        if ($post->user_id === $viewer->id || $seconds < 4) {
            return;
        }
        if (Cache::add("taste:dwell:{$viewer->id}:{$post->id}", 1, now()->addDay())) {
            $this->taste->engage($viewer->id, $post, $seconds >= 12 ? 'dwell_long' : 'dwell');
        }
    }

    /** Redis buffer'dagi ko‘rishlarni DB'ga yozish (scheduler har daqiqada chaqiradi). */
    public function flush(): int
    {
        if (config('fikrlash.views.buffer') !== 'redis') {
            return 0;
        }

        $redis = Redis::connection();
        $tmp = self::BUFFER_KEY.':flushing:'.uniqid();

        // RENAME atomik: flush paytida kelgan yangi ko‘rishlar yo‘qolmaydi.
        if (! $redis->exists(self::BUFFER_KEY)) {
            return 0;
        }
        $redis->rename(self::BUFFER_KEY, $tmp);
        $counts = $redis->hgetall($tmp);
        $redis->del($tmp);

        $total = 0;
        foreach (array_chunk($counts, 500, true) as $chunk) {
            $cases = [];
            $bindings = [];
            foreach ($chunk as $postId => $count) {
                $cases[] = 'WHEN ? THEN views_count + ?';
                array_push($bindings, (int) $postId, (int) $count);
                $total += (int) $count;
            }
            $ids = array_map('intval', array_keys($chunk));
            DB::update(
                'UPDATE posts SET views_count = CASE id '.implode(' ', $cases).' ELSE views_count END WHERE id IN ('.implode(',', $ids).')',
                $bindings,
            );
        }

        return $total;
    }

    private function increment(int $postId): void
    {
        if (config('fikrlash.views.buffer') === 'redis') {
            Redis::connection()->hincrby(self::BUFFER_KEY, (string) $postId, 1);

            return;
        }

        Post::withoutTimestamps(fn () => Post::query()->whereKey($postId)->increment('views_count'));
    }
}
