<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\Feed\FeedService;
use App\Services\Social\InterestService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(private FeedService $feed) {}

    public function index(Request $request, InterestService $interests): View
    {
        return view('categories.index', [
            'categories' => Category::cachedActive(),
            'followed' => $request->user() ? $interests->followedCategoryIds($request->user()->id) : [],
        ]);
    }

    public function show(Request $request, Category $category, InterestService $interests): View
    {
        abort_unless($category->is_active, 404);

        $posts = $this->feed->byCategory($category->id, $request->user());
        if ($request->ajax()) {
            return view('partials.feed-page', ['posts' => $this->feed->withViewerState($posts, $request->user())]);
        }

        return view('categories.show', [
            'category' => $category,
            'posts' => $this->feed->withViewerState($posts, $request->user()),
            'isFollowing' => $request->user() && in_array($category->id, $interests->followedCategoryIds($request->user()->id), true),
        ]);
    }
}
