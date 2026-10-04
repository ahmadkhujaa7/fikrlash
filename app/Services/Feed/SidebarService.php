<?php

namespace App\Services\Feed;

use App\Models\Category;
use App\Models\Follow;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Yon panel: trend teglar, kimni o‘qish mumkin va "lentangiz nimaga moslashgan" (keshlangan). */
class SidebarService
{
    public function __construct(private TasteService $taste) {}

    /** Algoritm aniqlagan eng kuchli qiziqishlar — foydalanuvchiga lenta nega shunday ekanini ko‘rsatadi. */
    public function tasteTopics(?User $viewer, int $limit = 4): Collection
    {
        if (! $viewer) {
            return collect();
        }
        $ids = array_keys($this->taste->topCategories($viewer->id, $limit));

        return Category::cachedActive()->whereIn('id', $ids)->sortBy(fn ($c) => array_search($c->id, $ids, true))->values();
    }

    public function trendingTags(int $limit = 8): Collection
    {
        return Cache::remember('sidebar:trending-tags', now()->addMinutes(10), fn () => Tag::query()
            ->select('tags.*', DB::raw('COUNT(post_tag.post_id) as recent_posts'))
            ->join('post_tag', 'post_tag.tag_id', '=', 'tags.id')
            ->join('posts', 'posts.id', '=', 'post_tag.post_id')
            ->where('posts.status', 'published')
            ->whereNull('posts.deleted_at')
            ->where('posts.published_at', '>=', now()->subDays(7))
            ->groupBy('tags.id', 'tags.name', 'tags.slug', 'tags.created_at', 'tags.updated_at')
            ->orderByDesc('recent_posts')
            ->limit($limit)
            ->get());
    }

    public function suggestedUsers(?User $viewer, int $limit = 4): Collection
    {
        $key = 'sidebar:suggested:'.($viewer?->id ?? 'guest');

        return Cache::remember($key, now()->addMinutes(15), function () use ($viewer, $limit) {
            $base = fn () => User::query()->visible()
                ->when($viewer, fn ($q) => $q->whereKeyNot($viewer->id)
                    ->whereNotIn('id', Follow::query()->select('following_id')->where('follower_id', $viewer->id)));

            // Avval: foydalanuvchi postlarini ko‘p o‘qigan/yoqtirgan, lekin hali obuna bo‘lmagan mualliflar.
            $liked = [];
            if ($viewer) {
                $lifts = array_filter($this->taste->profile($viewer->id)['author'], fn ($l) => $l > 1.2);
                arsort($lifts);
                $liked = array_slice(array_keys($lifts), 0, $limit * 2);
            }
            $people = $liked ? $base()->whereIn('id', $liked)->get()->sortBy(fn ($u) => array_search($u->id, $liked, true))->take($limit) : collect();

            return $people->concat(
                $base()->whereNotIn('id', $people->pluck('id'))->where('last_active_at', '>=', now()->subDays(30))
                    ->orderByDesc('followers_count')->limit($limit - $people->count())->get()
            )->values();
        });
    }

    public function forget(?User $viewer): void
    {
        Cache::forget('sidebar:suggested:'.($viewer?->id ?? 'guest'));
    }
}
