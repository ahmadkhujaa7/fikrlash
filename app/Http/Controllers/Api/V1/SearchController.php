<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Http\Resources\TagResource;
use App\Http\Resources\UserResource;
use App\Services\Feed\FeedService;
use App\Services\Feed\SearchService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SearchController extends Controller
{
    public function __invoke(Request $request, SearchService $search, FeedService $feed): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'type' => ['nullable', Rule::in(['all', 'posts', 'users', 'tags'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $type = $data['type'] ?? 'all';
        $viewer = $request->user();
        $result = [];

        if (in_array($type, ['all', 'posts'], true)) {
            $posts = $search->posts($data['q'], $viewer, (int) ($data['page'] ?? 1));
            $result['posts'] = PostResource::collection($feed->withViewerState($posts, $viewer))->resolve($request);
            $result['posts_has_more'] = $posts->hasMorePages();
        }
        if (in_array($type, ['all', 'users'], true)) {
            $result['users'] = UserResource::collection($search->users($data['q']))->resolve($request);
        }
        if (in_array($type, ['all', 'tags'], true)) {
            $result['tags'] = TagResource::collection($search->tags($data['q']))->resolve($request);
        }

        return ApiResponse::success($result);
    }
}
