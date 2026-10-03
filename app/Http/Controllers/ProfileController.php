<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Follow;
use App\Models\User;
use App\Services\Feed\FeedService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private FeedService $feed) {}

    public function show(Request $request, User $user): View
    {
        $viewer = $request->user();
        $posts = $this->feed->byUser($user, $viewer)->withPath(route('profile.posts', $user->username));

        return view('profile.show', $this->common($request, $user) + [
            'tab' => 'posts',
            'posts' => $this->feed->withViewerState($posts, $viewer),
        ]);
    }

    /** Profil postlari — infinite scroll uchun HTML bo‘lak. */
    public function posts(Request $request, User $user): View
    {
        $posts = $this->feed->byUser($user, $request->user())->withPath(route('profile.posts', $user->username));

        return view('partials.feed-page', ['posts' => $this->feed->withViewerState($posts, $request->user())]);
    }

    public function replies(Request $request, User $user): View
    {
        $comments = Comment::query()
            ->where('user_id', $user->id)->published()
            ->whereHas('post', fn ($q) => $q->forFeed($request->user()))
            ->with(['post.user', 'user'])
            ->latest()->paginate(20);

        return view('profile.show', $this->common($request, $user) + ['tab' => 'replies', 'comments' => $comments]);
    }

    public function followers(Request $request, User $user): View
    {
        return view('profile.connections', $this->common($request, $user) + [
            'tab' => 'followers',
            'people' => $people = $user->followers()->visible()->orderByPivot('created_at', 'desc')->paginate(30),
            'followingIds' => $this->followingIds($request, $people->pluck('id')->all()),
        ]);
    }

    public function following(Request $request, User $user): View
    {
        return view('profile.connections', $this->common($request, $user) + [
            'tab' => 'following',
            'people' => $people = $user->following()->visible()->orderByPivot('created_at', 'desc')->paginate(30),
            'followingIds' => $this->followingIds($request, $people->pluck('id')->all()),
        ]);
    }

    /** @return array<int, true> Ko‘rayotgan foydalanuvchi obuna bo‘lganlar (N+1 siz). */
    private function followingIds(Request $request, array $ids): array
    {
        if (! $request->user() || $ids === []) {
            return [];
        }

        return Follow::query()->where('follower_id', $request->user()->id)
            ->whereIn('following_id', $ids)->pluck('following_id')->flip()->map(fn () => true)->all();
    }

    private function common(Request $request, User $user): array
    {
        $viewer = $request->user();

        return [
            'user' => $user,
            'isOwner' => $viewer?->is($user) ?? false,
            'isFollowing' => $viewer && ! $viewer->is($user) ? $viewer->isFollowing($user) : false,
            'postsCount' => $user->publishedPostsCount(),
        ];
    }
}
