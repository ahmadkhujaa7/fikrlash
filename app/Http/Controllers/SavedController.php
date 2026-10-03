<?php

namespace App\Http\Controllers;

use App\Services\Feed\FeedService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SavedController extends Controller
{
    public function index(Request $request, FeedService $feed): View
    {
        $posts = $feed->saved($request->user())->withPath(route('saved.index'));

        if ($request->ajax()) {
            return view('partials.feed-page', ['posts' => $feed->withViewerState($posts, $request->user())]);
        }

        return view('saved.index', ['posts' => $feed->withViewerState($posts, $request->user())]);
    }
}
