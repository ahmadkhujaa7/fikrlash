<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\PostResource;
use App\Models\Category;
use App\Services\Feed\FeedService;
use App\Services\Feed\RecommendationService;
use App\Services\Social\InterestService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(CategoryResource::collection(Category::cachedActive()));
    }

    public function posts(Request $request, Category $category, FeedService $feed): JsonResponse
    {
        abort_unless($category->is_active, 404);

        return ApiResponse::success(PostResource::collection(
            $feed->withViewerState($feed->byCategory($category->id, $request->user()), $request->user())
        ), meta: ['category' => (new CategoryResource($category))->resolve()]);
    }

    public function follow(Request $request, Category $category, InterestService $interests, RecommendationService $recommendations): JsonResponse
    {
        abort_unless($category->is_active, 404);
        $interests->followCategory($request->user(), $category->id);
        $recommendations->forget($request->user());

        return ApiResponse::success(['following' => true]);
    }

    public function unfollow(Request $request, Category $category, InterestService $interests, RecommendationService $recommendations): JsonResponse
    {
        $interests->unfollowCategory($request->user(), $category->id);
        $recommendations->forget($request->user());

        return ApiResponse::success(['following' => false]);
    }
}
