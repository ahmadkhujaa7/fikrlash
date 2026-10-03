<?php

namespace App\Http\Controllers;

use App\Http\Requests\Posts\CreateCommentRequest;
use App\Models\Comment;
use App\Models\CommentLike;
use App\Models\Post;
use App\Services\Social\CommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class CommentController extends Controller
{
    private const REPLIES_PER_COMMENT = 50;

    public function __construct(private CommentService $comments) {}

    public function index(Request $request, Post $post): View
    {
        $this->authorize('view', $post);

        $comments = $post->comments()
            ->whereNull('parent_id')->published()->fromVisibleAuthors()
            ->with([
                'user',
                'replies' => fn ($q) => $q->published()->fromVisibleAuthors()->with(['user', 'replyToUser'])->oldest()->limit(self::REPLIES_PER_COMMENT),
            ])
            ->oldest()->orderBy('id')
            ->cursorPaginate(20)
            ->withPath(route('posts.comments', $post));

        $this->markLiked($request, $comments->getCollection()->flatMap(fn (Comment $c) => [$c, ...$c->replies])
            ->each(fn (Comment $c) => $c->setRelation('post', $post)));

        return view('partials.comments-page', ['comments' => $comments, 'post' => $post]);
    }

    public function store(CreateCommentRequest $request, Post $post): JsonResponse|RedirectResponse
    {
        $this->authorize('interact', $post);

        $comment = $this->comments->create($request->user(), $post, $request->validated('content'), $request->validated('parent_id'));
        $comment->load(['user', 'replyToUser'])->setRelation('replies', collect())->setRelation('post', $post);
        $comment->setAttribute('is_liked', false);

        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('partials.comment', ['comment' => $comment, 'post' => $post])->render(),
                'parent_id' => $comment->parent_id,
                'comments_count' => $post->comments_count,
            ], 201);
        }

        return redirect()->to(route('posts.show', $post).'#comment-'.$comment->id);
    }

    public function destroy(Request $request, Comment $comment): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $comment);
        $this->comments->delete($comment);

        return $request->expectsJson()
            ? response()->json(['deleted' => true])
            : back()->with('toast', 'Izoh o‘chirildi.');
    }

    private function markLiked(Request $request, Collection $comments): void
    {
        $liked = [];
        if ($user = $request->user()) {
            $liked = array_flip(CommentLike::query()->where('user_id', $user->id)
                ->whereIn('comment_id', $comments->pluck('id'))->pluck('comment_id')->all());
        }

        $comments->each(fn (Comment $c) => $c->setAttribute('is_liked', isset($liked[$c->id])));
    }
}
