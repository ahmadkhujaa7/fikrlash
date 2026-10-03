<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Services\Feed\FeedService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FeedController extends Controller
{
    public function __construct(private FeedService $feed) {}

    /** GET /feed?tab=for-you|latest|following */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tab' => ['nullable', Rule::in(FeedService::TABS)],
            'page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $viewer = $request->user();
        $tab = $data['tab'] ?? FeedService::TAB_FOR_YOU;

        $posts = match ($tab) {
            FeedService::TAB_LATEST => $this->feed->latest($viewer),
            FeedService::TAB_FOLLOWING => $viewer ? $this->feed->following($viewer) : abort(401),
            default => $this->feed->forYou($viewer, (int) ($data['page'] ?? 1)),
        };

        return ApiResponse::success(PostResource::collection($this->feed->withViewerState($posts, $viewer)), meta: ['tab' => $tab]);
    }
}
