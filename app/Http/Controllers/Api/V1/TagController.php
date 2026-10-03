<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use App\Services\Feed\FeedService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function posts(Request $request, string $slug, FeedService $feed): JsonResponse
    {
        $tag = Tag::query()->where('slug', $slug)->firstOrFail();

        return ApiResponse::success(PostResource::collection(
            $feed->withViewerState($feed->byTag($tag->id, $request->user()), $request->user())
        ), meta: ['tag' => (new TagResource($tag))->resolve()]);
    }
}
