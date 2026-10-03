<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Services\Feed\FeedService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TagController extends Controller
{
    public function __construct(private FeedService $feed) {}

    public function show(Request $request, string $slug): View
    {
        $tag = Tag::query()->where('slug', $slug)->first();
        $posts = $tag ? $this->feed->byTag($tag->id, $request->user()) : null;

        if ($request->ajax() && $posts) {
            return view('partials.feed-page', ['posts' => $this->feed->withViewerState($posts, $request->user())]);
        }

        return view('tags.show', [
            'tag' => $tag,
            'slug' => $slug,
            'posts' => $posts ? $this->feed->withViewerState($posts, $request->user()) : null,
        ]);
    }
}
