<?php

namespace App\Http\Controllers;

use App\Models\Follow;
use App\Services\Feed\FeedService;
use App\Services\Feed\SearchService;
use App\Support\TextNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    private const TYPES = ['all', 'posts', 'users', 'tags'];

    public function __construct(private SearchService $search, private FeedService $feed) {}

    public function index(Request $request): View|RedirectResponse
    {
        $q = trim(mb_substr((string) $request->query('q', ''), 0, 100));
        $type = in_array($request->query('type'), self::TYPES, true) ? $request->query('type') : 'all';

        // "#ai" — to‘g‘ridan-to‘g‘ri teg sahifasi.
        if (preg_match('/^#[\p{L}\p{N}_]+$/u', $q)) {
            return redirect()->route('tags.show', TextNormalizer::tagSlug($q));
        }

        $viewer = $request->user();
        $results = ['posts' => null, 'users' => collect(), 'tags' => collect(), 'categories' => collect()];

        if (mb_strlen($q) >= 2) {
            if (in_array($type, ['all', 'posts'], true)) {
                $results['posts'] = $this->feed->withViewerState(
                    $this->search->posts($q, $viewer, max(1, (int) $request->query('page', 1)))->withPath(route('search'))->appends(['q' => $q, 'type' => $type]),
                    $viewer,
                );
            }
            if (in_array($type, ['all', 'users'], true)) {
                $results['users'] = $this->search->users($q, $type === 'users' ? 30 : 5);
            }
            if (in_array($type, ['all', 'tags'], true)) {
                $results['tags'] = $this->search->tags($q);
                $results['categories'] = $this->search->categories($q);
            }
        }

        if ($request->ajax() && $results['posts']) {
            return view('partials.feed-page', ['posts' => $results['posts']]);
        }

        $followingIds = $viewer && $results['users']->isNotEmpty()
            ? Follow::query()->where('follower_id', $viewer->id)->whereIn('following_id', $results['users']->pluck('id'))->pluck('following_id')->flip()->all()
            : [];

        return view('search.index', ['q' => $q, 'type' => $type, 'followingIds' => $followingIds] + $results);
    }
}
