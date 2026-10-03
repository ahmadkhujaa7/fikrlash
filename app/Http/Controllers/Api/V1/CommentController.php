<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Posts\CreateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\CommentLike;
use App\Models\Post;
use App\Services\Social\CommentService;
use App\Services\Social\InteractionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function __construct(private CommentService $comments, private InteractionService $interactions) {}

    public function index(Request $request, Post $post): JsonResponse
    {
        $this->authorize('view', $post);

        $comments = $post->comments()->whereNull('parent_id')->published()->fromVisibleAuthors()
            ->with(['user', 'replies' => fn ($q) => $q->published()->fromVisibleAuthors()->with(['user', 'replyToUser'])->oldest()->limit(50)])
            ->oldest()->orderBy('id')->cursorPaginate(20);

        $this->markLiked($request, $comments->getCollection()->flatMap(fn (Comment $c) => [$c, ...$c->replies])
            ->each(fn (Comment $c) => $c->setRelation('post', $post)));

        return ApiResponse::success(CommentResource::collection($comments));
    }

    public function store(CreateCommentRequest $request, Post $post): JsonResponse
    {
        $this->authorize('interact', $post);
        $comment = $this->comments->create($request->user(), $post, $request->validated('content'), $request->validated('parent_id'));

        return ApiResponse::success(new CommentResource($comment->load(['user', 'replyToUser'])->setRelation('post', $post)), 'Izoh qo‘shildi.', 201);
    }

    public function update(CreateCommentRequest $request, Comment $comment): JsonResponse
    {
        $this->authorize('update', $comment);
        $this->comments->update($comment, $request->validated('content'));

        return ApiResponse::success(new CommentResource($comment->load('user')), 'Izoh yangilandi.');
    }

    public function destroy(Comment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);
        $this->comments->delete($comment);

        return ApiResponse::success(null, 'Izoh o‘chirildi.');
    }

    public function like(Request $request, Comment $comment): JsonResponse
    {
        $this->authorize('interact', $comment);
        abort_unless($request->user()->can('view', $comment->post), 404);
        $this->interactions->likeComment($request->user(), $comment);

        return ApiResponse::success(['liked' => true, 'likes_count' => $comment->likes_count]);
    }

    public function unlike(Request $request, Comment $comment): JsonResponse
    {
        $this->interactions->unlikeComment($request->user(), $comment);

        return ApiResponse::success(['liked' => false, 'likes_count' => $comment->likes_count]);
    }

    private function markLiked(Request $request, $comments): void
    {
        $liked = $request->user()
            ? array_flip(CommentLike::query()->where('user_id', $request->user()->id)->whereIn('comment_id', $comments->pluck('id'))->pluck('comment_id')->all())
            : [];
        $comments->each(fn (Comment $c) => $c->setAttribute('is_liked', isset($liked[$c->id])));
    }
}
