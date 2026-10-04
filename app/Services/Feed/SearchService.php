<?php

namespace App\Services\Feed;

use App\Models\Tag;
use App\Models\User;
use App\Support\TextNormalizer;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Global qidiruv. MySQL'da FULLTEXT (boolean mode), boshqa drayverlarda LIKE.
 * Interfeys o‘zgarmagan holda keyinchalik Meilisearch/OpenSearch (Laravel Scout) ga o‘tkazish mumkin.
 */
class SearchService
{
    private const MIN_FULLTEXT_TERM = 3;

    public function __construct(private FeedService $feed) {}

    public function posts(string $query, ?User $viewer, int $page = 1): Paginator
    {
        return $this->postsQuery($query, $viewer)
            ->simplePaginate(config('fikrlash.feed.per_page'), ['*'], 'page', $page);
    }

    /** Tezkor takliflar uchun: bir nechta eng mos post. */
    public function quickPosts(string $query, ?User $viewer, int $limit = 4): Collection
    {
        return $this->postsQuery($query, $viewer)->limit($limit)->get();
    }

    private function postsQuery(string $query, ?User $viewer): Builder
    {
        $normalized = TextNormalizer::forSearch($query);
        $builder = $this->feed->baseQuery($viewer);

        $terms = array_values(array_filter(
            preg_split('/\s+/u', preg_replace('/[+\-<>()~*"@]+/u', ' ', $normalized) ?? '') ?: [],
            fn ($t) => mb_strlen($t) >= self::MIN_FULLTEXT_TERM,
        ));

        if (DB::getDriverName() === 'mysql' && $terms !== []) {
            $boolean = implode(' ', array_map(fn ($t) => '+'.$t.'*', $terms));
            $builder->whereFullText('posts.search_text', $boolean, ['mode' => 'boolean']);
        } else {
            $builder->where('posts.search_text', 'like', '%'.$this->escapeLike($normalized).'%');
        }

        return $builder->orderByDesc('posts.score')->orderByDesc('posts.id');
    }

    public function users(string $query, int $limit = 20): Collection
    {
        $q = $this->escapeLike(mb_strtolower(ltrim(trim($query), '@')));
        if ($q === '') {
            return collect();
        }

        return User::query()->visible()
            ->where(fn (Builder $b) => $b->where('username', 'like', $q.'%')->orWhere('name', 'like', '%'.$q.'%'))
            // Tasdiqlangan akkauntlar qidiruvda birinchi.
            ->orderByRaw('verified_at IS NULL')
            ->orderByDesc('followers_count')
            ->limit($limit)
            ->get();
    }

    public function tags(string $query, int $limit = 10): Collection
    {
        $slug = TextNormalizer::tagSlug($query);
        if ($slug === '') {
            return collect();
        }

        return Tag::query()->where('slug', 'like', $this->escapeLike($slug).'%')
            ->withCount('posts')->orderByDesc('posts_count')->limit($limit)->get();
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
