<?php

namespace App\Services\Feed;

use App\Models\Post;
use App\Models\PostView;
use App\Models\User;
use App\Services\Social\FollowService;
use App\Services\Social\InterestService;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Pagination\Paginator as SimplePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * "Siz uchun" lentasi — rule-based tavsiya.
 *
 * 1) Nomzodlar: so‘nggi N kundagi eng yuqori "hot" ballli postlar + eng yangi postlar.
 * 2) Har bir nomzod shaxsiy signallar bilan baholanadi:
 *      qiziqish (kategoriya vazni), obuna bo‘lingan muallif, AI sifat bahosi,
 *      allaqachon ko‘rilgan postlar pastga tushadi.
 * 3) Xilma-xillik: bitta muallifdan ketma-ket ko‘p post chiqmaydi.
 * Natija (ID ro‘yxati) keshda saqlanadi — sahifalash barqaror va arzon.
 * Kelajakda shu klass ichidagi rank() ML model bilan almashtiriladi.
 */
class RecommendationService
{
    private const MAX_PER_AUTHOR_IN_ROW = 2;

    public function __construct(
        private ScoreCalculator $scores,
        private InterestService $interests,
        private FollowService $follows,
    ) {}

    public function forUser(User $user, int $page = 1): Paginator
    {
        $perPage = (int) config('fikrlash.feed.per_page');
        $ids = Cache::remember($this->cacheKey($user), now()->addMinutes(config('fikrlash.feed.cache_minutes')), fn () => $this->rank($user));

        $pageIds = array_slice($ids, ($page - 1) * $perPage, $perPage + 1);
        $hasMore = count($pageIds) > $perPage;
        $pageIds = array_slice($pageIds, 0, $perPage);

        // Visibility qayta tekshiriladi: keshdan keyin o‘chirilgan/yashirilgan postlar chiqmaydi.
        $posts = Post::query()->forFeed($user)->with(['user', 'category'])->whereIn('posts.id', $pageIds)->get()
            ->sortBy(fn (Post $p) => array_search($p->id, $pageIds, true))->values();

        $paginator = new SimplePaginator($posts, $perPage, $page, ['path' => SimplePaginator::resolveCurrentPath(), 'pageName' => 'page']);
        $paginator->hasMorePagesWhen($hasMore);

        return $paginator;
    }

    public function forget(User $user): void
    {
        Cache::forget($this->cacheKey($user));
    }

    /** @return list<int> */
    public function rank(User $user): array
    {
        $candidates = $this->candidates($user);
        if ($candidates->isEmpty()) {
            return [];
        }

        $w = config('fikrlash.feed.weights');
        $interests = $this->interests->normalizedMap($user->id);
        $followed = array_flip($this->follows->followingIds($user));
        $seen = PostView::query()->where('user_id', $user->id)
            ->whereIn('post_id', $candidates->pluck('id'))->pluck('post_id')->flip()->all();

        $scored = $candidates->map(function (Post $post) use ($w, $interests, $followed, $seen) {
            $score = $this->scores->hot($post);
            $score *= 1 + $w['interest'] * ($interests[$post->category_id] ?? 0);

            if (isset($followed[$post->user_id])) {
                $score *= 1 + $w['followed_author'];
            }
            if ($post->ai_score !== null) {
                $score *= 1 + $w['ai_quality'] * (($post->ai_score - 50) / 50);
            }
            if (isset($seen[$post->id])) {
                $score *= $w['seen_penalty'];
            }

            return ['id' => $post->id, 'author' => $post->user_id, 'score' => $score];
        })->sortByDesc('score')->values();

        return $this->diversify($scored)->take((int) config('fikrlash.feed.ranked_size'))->all();
    }

    private function candidates(User $user): Collection
    {
        $columns = ['posts.id', 'posts.user_id', 'posts.category_id', 'posts.likes_count', 'posts.comments_count',
            'posts.saves_count', 'posts.views_count', 'posts.ai_score', 'posts.published_at'];
        $since = now()->subDays(config('fikrlash.feed.candidate_days'));
        $base = fn () => Post::query()->forFeed($user)->where('posts.user_id', '!=', $user->id);

        $top = $base()->where('posts.published_at', '>=', $since)
            ->orderByDesc('posts.score')->limit(config('fikrlash.feed.candidate_limit'))->get($columns);
        $fresh = $base()->orderByDesc('posts.published_at')->limit(150)->get($columns);

        $all = $top->concat($fresh)->unique('id');

        // Yangi platformada kontent kam bo‘lsa — eski postlar bilan to‘ldiriladi.
        if ($all->count() < config('fikrlash.feed.per_page') * 2) {
            $all = $all->concat($base()->orderByDesc('posts.score')->limit(200)->get($columns))->unique('id');
        }

        return $all->values();
    }

    /** Bitta muallifdan ketma-ket ko‘p post chiqmasligi uchun qayta tartiblash. */
    private function diversify(Collection $scored): Collection
    {
        $result = [];
        $deferred = [];
        $recent = [];

        foreach ($scored as $item) {
            $streak = count(array_filter(array_slice($recent, -self::MAX_PER_AUTHOR_IN_ROW), fn ($a) => $a === $item['author']));
            if ($streak >= self::MAX_PER_AUTHOR_IN_ROW) {
                $deferred[] = $item;

                continue;
            }
            $result[] = $item['id'];
            $recent[] = $item['author'];
        }

        foreach ($deferred as $item) {
            $result[] = $item['id'];
        }

        return collect($result);
    }

    private function cacheKey(User $user): string
    {
        return "feed:for-you:{$user->id}";
    }
}
