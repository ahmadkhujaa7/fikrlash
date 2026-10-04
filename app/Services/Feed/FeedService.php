<?php

namespace App\Services\Feed;

use App\Models\Follow;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\SavedPost;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FeedService
{
    public const TAB_FOR_YOU = 'for-you';

    public const TAB_LATEST = 'latest';

    public const TAB_FOLLOWING = 'following';

    public const TABS = [self::TAB_FOR_YOU, self::TAB_LATEST, self::TAB_FOLLOWING];

    public function __construct(private RecommendationService $recommendations) {}

    /** Kartochka uchun kerakli bog‘lanishlar (N+1 oldini olish). */
    public function baseQuery(?User $viewer): Builder
    {
        return Post::query()->forFeed($viewer)->with(['user']);
    }

    public function latest(?User $viewer): CursorPaginator
    {
        return $this->cursor($this->baseQuery($viewer));
    }

    public function following(User $viewer): CursorPaginator
    {
        $authors = Follow::query()->select('following_id')->where('follower_id', $viewer->id);

        return $this->cursor($this->baseQuery($viewer)->where(
            fn (Builder $q) => $q->whereIn('posts.user_id', $authors)->orWhere('posts.user_id', $viewer->id)
        ));
    }

    /** Shaxsiy tavsiyalar; mehmon uchun — trending. */
    public function forYou(?User $viewer, int $page = 1): Paginator
    {
        if ($viewer) {
            return $this->recommendations->forUser($viewer, $page);
        }

        $trending = $this->trending(null, $page);

        // Jim davrda (oxirgi haftada post kam) — barcha vaqtdagi eng yaxshilar.
        return $trending->isEmpty() && $page === 1 ? $this->trending(null, $page, days: 3650) : $trending;
    }

    public function trending(?User $viewer, int $page = 1, int $days = 7): Paginator
    {
        return $this->baseQuery($viewer)
            ->where('posts.published_at', '>=', now()->subDays($days))
            ->orderByDesc('posts.score')->orderByDesc('posts.id')
            ->simplePaginate(config('fikrlash.feed.per_page'), ['*'], 'page', $page);
    }

    public function byUser(User $author, ?User $viewer): CursorPaginator
    {
        return $this->cursor($this->baseQuery($viewer)->where('posts.user_id', $author->id));
    }

    public function byTag(int $tagId, ?User $viewer): CursorPaginator
    {
        return $this->cursor($this->baseQuery($viewer)->whereHas('tags', fn (Builder $q) => $q->whereKey($tagId)));
    }

    public function saved(User $viewer): CursorPaginator
    {
        return $this->baseQuery($viewer)
            ->join('saved_posts', 'saved_posts.post_id', '=', 'posts.id')
            ->where('saved_posts.user_id', $viewer->id)
            ->select('posts.*')
            ->orderByDesc('saved_posts.id')
            ->cursorPaginate(config('fikrlash.feed.per_page'));
    }

    /** O‘xshash postlar: shu kategoriya yoki teglar, yaqin vaqt, yuqori ball. */
    public function related(Post $post, ?User $viewer, int $limit = 4): Collection
    {
        $tagIds = $post->tags()->pluck('tags.id');

        return $this->baseQuery($viewer)
            ->whereKeyNot($post->id)
            ->where(fn (Builder $q) => $q
                ->when($post->category_id, fn ($q) => $q->where('posts.category_id', $post->category_id))
                ->when($tagIds->isNotEmpty(), fn ($q) => $q->orWhereHas('tags', fn ($t) => $t->whereIn('tags.id', $tagIds))))
            ->orderByDesc('posts.score')
            ->limit($limit)
            ->get();
    }

    /**
     * Joriy foydalanuvchi uchun is_liked / is_saved belgilarini 2 ta so‘rov bilan qo‘shadi.
     *
     * @template T of iterable<Post>
     *
     * @param  T  $posts
     * @return T
     */
    public function withViewerState(iterable $posts, ?User $viewer): iterable
    {
        $items = collect($posts instanceof Paginator || $posts instanceof CursorPaginator ? $posts->items() : $posts);
        $ids = $items->pluck('id')->all();

        $liked = $saved = [];
        if ($viewer && $ids) {
            $liked = array_flip(PostLike::query()->where('user_id', $viewer->id)->whereIn('post_id', $ids)->pluck('post_id')->all());
            $saved = array_flip(SavedPost::query()->where('user_id', $viewer->id)->whereIn('post_id', $ids)->pluck('post_id')->all());
        }

        foreach ($items as $post) {
            $post->setAttribute('is_liked', isset($liked[$post->id]));
            $post->setAttribute('is_saved', isset($saved[$post->id]));
        }

        return $posts;
    }

    private function cursor(Builder $query): CursorPaginator
    {
        return $query->orderByDesc('posts.published_at')->orderByDesc('posts.id')
            ->cursorPaginate(config('fikrlash.feed.per_page'));
    }
}
