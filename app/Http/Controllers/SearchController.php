<?php

namespace App\Http\Controllers;

use App\Models\Follow;
use App\Services\Feed\FeedService;
use App\Services\Feed\SearchService;
use App\Support\TextNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Qidiruv.
 *  - index   — to‘liq sahifa (JS'siz ham ishlaydi);
 *  - live    — yozilayotganda natijalar bo‘lagi (sahifa qayta yuklanmaydi);
 *  - suggest — sarlavhadagi qidiruv maydoni ostidagi tezkor takliflar.
 */
class SearchController extends Controller
{
    private const TYPES = ['all', 'posts', 'users', 'tags'];

    public const MIN_LENGTH = 2;

    public function __construct(private SearchService $search, private FeedService $feed) {}

    public function index(Request $request): View|RedirectResponse
    {
        [$q, $type] = $this->input($request);

        // "#ai" yuborilsa — to‘g‘ridan-to‘g‘ri teg sahifasi.
        if (preg_match('/^#[\p{L}\p{N}_]+$/u', $q)) {
            return redirect()->route('tags.show', TextNormalizer::tagSlug($q));
        }

        $results = $this->results($request, $q, $type);

        // Natijalar ichidagi "ko‘proq yuklash" (infinite scroll).
        if ($request->ajax() && $results['posts']) {
            return view('partials.feed-page', ['posts' => $results['posts']]);
        }

        return view('search.index', $results);
    }

    public function live(Request $request): View
    {
        [$q, $type] = $this->input($request);

        return view('search.results', $this->results($request, $q, $type));
    }

    public function suggest(Request $request): View
    {
        [$q] = $this->input($request);
        $viewer = $request->user();
        $ready = mb_strlen($q) >= self::MIN_LENGTH;

        return view('search.suggest', [
            'q' => $q,
            'users' => $ready ? $this->search->users($q, 3) : collect(),
            'tags' => $ready ? $this->search->tags($q, 4) : collect(),
            'posts' => $ready ? $this->search->quickPosts($q, $viewer, 4) : collect(),
        ]);
    }

    /** @return array{0: string, 1: string} */
    private function input(Request $request): array
    {
        $q = trim(mb_substr((string) $request->query('q', ''), 0, 100));
        $type = in_array($request->query('type'), self::TYPES, true) ? $request->query('type') : 'all';

        return [$q, $type];
    }

    private function results(Request $request, string $q, string $type): array
    {
        $viewer = $request->user();
        $results = ['q' => $q, 'type' => $type, 'posts' => null, 'users' => collect(), 'tags' => collect(), 'followingIds' => []];

        if (mb_strlen($q) < self::MIN_LENGTH) {
            return $results;
        }

        if (in_array($type, ['all', 'posts'], true)) {
            $posts = $this->search->posts($q, $viewer, max(1, (int) $request->query('page', 1)))
                ->withPath(route('search'))->appends(['q' => $q, 'type' => $type]);
            $results['posts'] = $this->feed->withViewerState($posts, $viewer);
        }
        if (in_array($type, ['all', 'users'], true)) {
            $results['users'] = $this->search->users($q, $type === 'users' ? 30 : 5);
        }
        if (in_array($type, ['all', 'tags'], true)) {
            $results['tags'] = $this->search->tags($q);
        }

        if ($viewer && $results['users']->isNotEmpty()) {
            $results['followingIds'] = Follow::query()->where('follower_id', $viewer->id)
                ->whereIn('following_id', $results['users']->pluck('id'))->pluck('following_id')->flip()->all();
        }

        return $results;
    }
}
