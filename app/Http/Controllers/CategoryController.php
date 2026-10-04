<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\Feed\FeedService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(private FeedService $feed) {}

    public function index(): View
    {
        return view('categories.index', ['categories' => Category::cachedActive()]);
    }

    public function show(Request $request, Category $category): View
    {
        abort_unless($category->is_active, 404);

        $posts = $this->feed->byCategory($category->id, $request->user());
        if ($request->ajax()) {
            return view('partials.feed-page', ['posts' => $this->feed->withViewerState($posts, $request->user())]);
        }

        return view('categories.show', [
            'category' => $category,
            'posts' => $this->feed->withViewerState($posts, $request->user()),
        ]);
    }
}
