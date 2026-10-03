<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\Feed\FeedService;
use App\Services\Feed\SidebarService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedController extends Controller
{
    public function __construct(private FeedService $feed, private SidebarService $sidebar) {}

    public function index(Request $request): View
    {
        $tab = $this->tab($request);

        return view('feed.index', [
            'tab' => $tab,
            'posts' => $this->load($request, $tab),
            'categories' => Category::cachedActive(),
            'trendingTags' => $this->sidebar->trendingTags(),
            'suggestedUsers' => $this->sidebar->suggestedUsers($request->user()),
        ]);
    }

    /** "Ko‘proq yuklash" uchun HTML bo‘lak (infinite scroll). */
    public function page(Request $request): View
    {
        return view('partials.feed-page', ['posts' => $this->load($request, $this->tab($request))]);
    }

    private function tab(Request $request): string
    {
        $tab = (string) $request->query('tab', FeedService::TAB_FOR_YOU);
        if (! in_array($tab, FeedService::TABS, true) || ($tab === FeedService::TAB_FOLLOWING && ! $request->user())) {
            return FeedService::TAB_FOR_YOU;
        }

        return $tab;
    }

    private function load(Request $request, string $tab)
    {
        $viewer = $request->user();
        $posts = match ($tab) {
            FeedService::TAB_LATEST => $this->feed->latest($viewer),
            FeedService::TAB_FOLLOWING => $this->feed->following($viewer),
            default => $this->feed->forYou($viewer, max(1, (int) $request->query('page', 1))),
        };

        $posts->withPath(route('feed.page'))->appends(['tab' => $tab]);

        return $this->feed->withViewerState($posts, $viewer);
    }
}
