<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Feed\FeedService;
use App\Services\Social\FollowService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** {user} — ID yoki username. */
class UserController extends Controller
{
    public function __construct(private FollowService $follows, private FeedService $feed) {}

    public function show(Request $request, User $user): JsonResponse
    {
        if ($viewer = $request->user()) {
            $user->setAttribute('is_following', $viewer->isFollowing($user));
        }

        return ApiResponse::success(new UserResource($user), meta: ['posts_count' => $user->publishedPostsCount()]);
    }

    public function posts(Request $request, User $user): JsonResponse
    {
        $posts = $this->feed->withViewerState($this->feed->byUser($user, $request->user()), $request->user());

        return ApiResponse::success(PostResource::collection($posts));
    }

    public function followers(User $user): JsonResponse
    {
        return ApiResponse::success(UserResource::collection($user->followers()->visible()->orderByPivot('created_at', 'desc')->cursorPaginate(30)));
    }

    public function following(User $user): JsonResponse
    {
        return ApiResponse::success(UserResource::collection($user->following()->visible()->orderByPivot('created_at', 'desc')->cursorPaginate(30)));
    }

    public function follow(Request $request, User $user): JsonResponse
    {
        $this->authorize('follow', $user);
        $this->follows->follow($request->user(), $user);

        return ApiResponse::success(['following' => true, 'followers_count' => $user->followers_count]);
    }

    public function unfollow(Request $request, User $user): JsonResponse
    {
        $this->follows->unfollow($request->user(), $user);

        return ApiResponse::success(['following' => false, 'followers_count' => $user->followers_count]);
    }
}
