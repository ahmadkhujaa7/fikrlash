<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Http\Requests\Posts\CreatePostRequest;
use App\Http\Requests\Posts\UpdatePostRequest;
use App\Models\Post;
use App\Services\Feed\FeedService;
use App\Services\Feed\ViewRecorder;
use App\Services\Posts\PostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PostController extends Controller
{
    public function __construct(private PostService $posts, private FeedService $feed) {}

    public function show(Request $request, Post $post, ViewRecorder $views): View
    {
        $this->authorize('view', $post);

        $viewer = $request->user();
        $post->load(['user', 'tags']);
        $this->feed->withViewerState([$post], $viewer);

        if ($post->isPublished()) {
            $views->record($post, $viewer, $request->ip().'|'.$request->userAgent());
            if ($viewer) {
                $views->recordOpen($post, $viewer);
            }
        }

        $data = [
            'post' => $post,
            'related' => $post->isPublished() ? $this->feed->related($post, $viewer) : collect(),
        ];

        return view('posts.show', $data);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Post::class);

        return view('posts.create', [
            'type' => $request->query('type') === Post::TYPE_ARTICLE ? Post::TYPE_ARTICLE : Post::TYPE_POST,
        ]);
    }

    public function store(CreatePostRequest $request): RedirectResponse
    {
        $this->authorize('create', Post::class);

        $post = $this->posts->create($request->user(), $request->postData(), $request->file('image'));

        return $post->isPublished()
            ? redirect()->route('posts.show', $post)->with('toast', $post->isArticle() ? 'Maqolangiz chop etildi!' : 'Fikringiz chop etildi!')
            : redirect()->route('posts.drafts')->with('toast', 'Qoralama saqlandi.');
    }

    public function edit(Post $post): View
    {
        $this->authorize('update', $post);

        return view('posts.edit', ['post' => $post->load('tags')]);
    }

    public function update(UpdatePostRequest $request, Post $post): RedirectResponse
    {
        $this->authorize('update', $post);

        $this->posts->update($post, $request->postData(), $request->file('image'));

        return $post->isPublished()
            ? redirect()->route('posts.show', $post)->with('toast', 'Post yangilandi.')
            : redirect()->route('posts.drafts')->with('toast', 'Qoralama yangilandi.');
    }

    public function destroy(Request $request, Post $post): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $post);
        $this->posts->delete($post);
        $profile = route('profile.show', $request->user()->username);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Post o‘chirildi.', 'redirect' => $profile]);
        }

        return redirect($profile)->with('toast', 'Post o‘chirildi.');
    }

    public function drafts(Request $request): View
    {
        return view('posts.drafts', [
            'posts' => $request->user()->posts()->where('status', PostStatus::Draft)->latest()->paginate(20),
        ]);
    }
}
