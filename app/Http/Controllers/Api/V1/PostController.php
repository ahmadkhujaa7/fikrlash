<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Posts\CreatePostRequest;
use App\Http\Requests\Posts\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\Feed\FeedService;
use App\Services\Feed\TasteService;
use App\Services\Feed\ViewRecorder;
use App\Services\Posts\PostService;
use App\Services\Social\InteractionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function __construct(private PostService $posts, private FeedService $feed, private InteractionService $interactions) {}

    /** Eng yangi postlar. */
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(PostResource::collection(
            $this->feed->withViewerState($this->feed->latest($request->user()), $request->user())
        ));
    }

    public function show(Request $request, Post $post): JsonResponse
    {
        $this->authorize('view', $post);
        $post->load(['user', 'category', 'tags']);
        $this->feed->withViewerState([$post], $request->user());

        return ApiResponse::success(new PostResource($post));
    }

    public function store(CreatePostRequest $request): JsonResponse
    {
        $this->authorize('create', Post::class);
        $post = $this->posts->create($request->user(), $request->postData(), $request->file('image'));

        return ApiResponse::success(new PostResource($post->load(['user', 'category', 'tags'])), 'Post yaratildi.', 201);
    }

    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        $this->authorize('update', $post);
        $this->posts->update($post, $request->postData(), $request->file('image'));

        return ApiResponse::success(new PostResource($post->fresh(['user', 'category', 'tags'])), 'Post yangilandi.');
    }

    public function destroy(Post $post): JsonResponse
    {
        $this->authorize('delete', $post);
        $this->posts->delete($post);

        return ApiResponse::success(null, 'Post o‘chirildi.');
    }

    public function like(Request $request, Post $post): JsonResponse
    {
        $this->authorize('interact', $post);
        $this->interactions->likePost($request->user(), $post);

        return ApiResponse::success(['liked' => true, 'likes_count' => $post->likes_count]);
    }

    public function unlike(Request $request, Post $post): JsonResponse
    {
        $this->interactions->unlikePost($request->user(), $post);

        return ApiResponse::success(['liked' => false, 'likes_count' => $post->likes_count]);
    }

    public function save(Request $request, Post $post): JsonResponse
    {
        $this->authorize('interact', $post);
        $this->interactions->savePost($request->user(), $post);

        return ApiResponse::success(['saved' => true, 'saves_count' => $post->saves_count]);
    }

    public function unsave(Request $request, Post $post): JsonResponse
    {
        $this->interactions->unsavePost($request->user(), $post);

        return ApiResponse::success(['saved' => false, 'saves_count' => $post->saves_count]);
    }

    /**
     * Lentadagi ko‘rishlar to‘plami.
     * post_ids — ekranda 1.5+ soniya ko‘ringan kartochkalar; dwell — {post_id: soniya}, kartochka ustida to‘xtash vaqti.
     */
    public function views(Request $request, ViewRecorder $views): JsonResponse
    {
        $data = $request->validate([
            'post_ids' => ['present', 'array', 'max:30'],
            'post_ids.*' => ['integer'],
            'dwell' => ['sometimes', 'array', 'max:30'],
            'dwell.*' => ['integer', 'min:0', 'max:600'],
        ]);
        $viewer = $request->user();
        $fingerprint = $request->ip().'|'.$request->userAgent();
        $dwell = $viewer ? array_filter($data['dwell'] ?? [], fn ($s, $id) => is_numeric($id), ARRAY_FILTER_USE_BOTH) : [];
        $ids = array_unique([...$data['post_ids'], ...array_map('intval', array_keys($dwell))]);

        $posts = Post::query()->forFeed($viewer)->whereIn('posts.id', $ids)
            ->with('tags:id')->get(['posts.id', 'posts.user_id', 'posts.category_id']);

        $counted = $posts->whereIn('id', $data['post_ids'])
            ->filter(fn (Post $post) => $views->record($post, $viewer, $fingerprint))
            ->count();

        foreach ($posts as $post) {
            if (isset($dwell[$post->id])) {
                $views->recordDwell($post, $viewer, (int) $dwell[$post->id]);
            }
        }

        return ApiResponse::success(['counted' => $counted]);
    }

    /** "Qiziq emas" — post yashiriladi, shunga o‘xshash postlar kamroq chiqadi. */
    public function notInterested(Request $request, Post $post, TasteService $taste): JsonResponse
    {
        $this->authorize('view', $post);
        $taste->notInterested($request->user()->id, $post);

        return ApiResponse::success(['dismissed' => true], 'Tushunarli. Bunday postlar kamroq chiqadi.');
    }

    /** O‘qish vaqti (soniya) — sahifadan chiqishda yuboriladi. */
    public function readTime(Request $request, Post $post, ViewRecorder $views): JsonResponse
    {
        $data = $request->validate(['seconds' => ['required', 'integer', 'min:1', 'max:3600']]);
        if ($request->user() && $request->user()->can('view', $post)) {
            $views->recordReadTime($post, $request->user(), (int) $data['seconds']);
        }

        return ApiResponse::success(null);
    }
}
