<?php

namespace App\Services\Feed;

use App\Models\Post;
use App\Models\PostView;
use App\Models\User;
use App\Services\Social\FollowService;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Pagination\Paginator as SimplePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * "Siz uchun" lentasi — foydalanuvchi didiga moslashadigan tavsiya.
 *
 * Foydalanuvchi hech narsa tanlamaydi: did TasteService orqali xatti-harakatdan o‘rganiladi
 * (nima ko‘rsatildi va nimaga javob berdi — ochdi, o‘qidi, like, izoh, saqladi, "qiziq emas").
 *
 * 1) Nomzodlar: so‘nggi N kundagi "hot" postlar + eng yangilari (+ kontent kam bo‘lsa eskilari).
 * 2) Ball = hot × kategoriya lift × teglar lift × muallif lift × obuna × AI sifat × (ko‘rilgan bo‘lsa jarima).
 *    Lift'lar daraja ko‘rsatkichi orqali qo‘shiladi: score × lift^w (w — xususiyat vazni).
 * 3) Xilma-xillik: bitta muallifdan ketma-ket ko‘p post chiqmaydi.
 * 4) Kashfiyot: har N-o‘ringa foydalanuvchi hali kam ko‘rgan mavzudan post qo‘yiladi —
 *    aks holda lenta bir xil mavzuga "qamalib" qoladi va yangi qiziqish paydo bo‘lmaydi.
 * "Qiziq emas" bosilgan postlar umuman chiqmaydi. Natija (ID ro‘yxati) keshda saqlanadi.
 */
class RecommendationService
{
    private const MAX_PER_AUTHOR_IN_ROW = 2;

    public function __construct(
        private ScoreCalculator $scores,
        private TasteService $taste,
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
        $posts = Post::query()->forFeed($user)->with(['user'])->whereIn('posts.id', $pageIds)->get()
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
        $fw = config('fikrlash.taste.feature_weights');
        $profile = $this->taste->profile($user->id);
        $followed = array_flip($this->follows->followingIds($user));
        $verified = User::query()->whereIn('id', $candidates->pluck('user_id')->unique())->whereNotNull('verified_at')->pluck('id')->flip()->all();

        $views = PostView::query()->where('user_id', $user->id)->whereIn('post_id', $candidates->pluck('id'))
            ->get(['post_id', 'dismissed_at'])->keyBy('post_id');
        $tags = DB::table('post_tag')->whereIn('post_id', $candidates->pluck('id'))->get(['post_id', 'tag_id'])
            ->groupBy('post_id')->map(fn ($rows) => $rows->pluck('tag_id')->all());

        $scored = $candidates
            ->reject(fn (Post $post) => $views->get($post->id)?->dismissed_at !== null)
            ->map(function (Post $post) use ($w, $fw, $profile, $followed, $verified, $views, $tags) {
                $score = $this->scores->hot($post);

                $score *= ($profile['category'][$post->category_id] ?? 1.0) ** $fw['category'];
                $score *= $this->tagLift($tags->get($post->id, []), $profile['tag']) ** $fw['tag'];
                $score *= ($profile['author'][$post->user_id] ?? 1.0) ** $fw['author'];

                if (isset($followed[$post->user_id])) {
                    $score *= 1 + $w['followed_author'];
                }
                if (isset($verified[$post->user_id])) {
                    $score *= 1 + $w['verified_author'];
                }
                if ($post->ai_score !== null) {
                    $score *= 1 + $w['ai_quality'] * (($post->ai_score - 50) / 50);
                }
                if ($views->has($post->id)) {
                    $score *= $w['seen_penalty'];
                }

                // Kashfiyot uchun: foydalanuvchi bu mavzuni deyarli ko‘rmagan.
                $unexplored = ! $profile['empty'] && ($profile['seen'][$post->category_id] ?? 0) < 2;

                return ['id' => $post->id, 'author' => $post->user_id, 'score' => $score, 'explore' => $unexplored, 'hot' => $this->scores->hot($post)];
            })->sortByDesc('score')->values();

        $ordered = $this->explore($this->diversify($scored));

        return array_slice($ordered, 0, (int) config('fikrlash.feed.ranked_size'));
    }

    /** Teglar bo‘yicha qiziqish: eng kuchli ijobiy va eng kuchli salbiy signal o‘rtasidagi muvozanat. */
    private function tagLift(array $tagIds, array $tagProfile): float
    {
        $lifts = array_values(array_intersect_key($tagProfile, array_flip($tagIds)));
        if ($lifts === []) {
            return 1.0;
        }

        return sqrt(max($lifts) * min($lifts));
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

    /**
     * Bitta muallifdan ketma-ket ko‘p post chiqmasligi uchun qayta tartiblash.
     *
     * @return list<array{id: int, author: int, score: float, explore: bool, hot: float}>
     */
    private function diversify(Collection $scored): array
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
            $result[] = $item;
            $recent[] = $item['author'];
        }

        return [...$result, ...$deferred];
    }

    /**
     * Har N-o‘ringa kam ko‘rilgan mavzudagi eng mashhur postni ko‘tarish.
     *
     * @return list<int>
     */
    private function explore(array $items): array
    {
        $every = (int) config('fikrlash.taste.explore_every');
        $pool = collect($items)->where('explore', true)->sortByDesc('hot')->pluck('id')->all();
        if ($every < 2 || $pool === []) {
            return array_column($items, 'id');
        }

        $placed = [];
        $rest = array_column($items, 'id');
        $result = [];
        while ($rest !== [] || $pool !== []) {
            if ((count($result) + 1) % $every === 0 && $pool !== []) {
                $id = array_shift($pool);
            } else {
                $id = array_shift($rest) ?? array_shift($pool);
            }
            if ($id === null) {
                break;
            }
            if (! isset($placed[$id])) {
                $placed[$id] = true;
                $result[] = $id;
            }
        }

        return $result;
    }

    private function cacheKey(User $user): string
    {
        return "feed:for-you:{$user->id}";
    }
}
