<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReportReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Posts\CreateReportRequest;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Services\Social\ReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function store(CreateReportRequest $request, ReportService $reports): JsonResponse
    {
        $data = $request->validated();
        $target = match ($data['type']) {
            'post' => Post::query()->findOrFail($data['id']),
            'comment' => Comment::query()->findOrFail($data['id']),
            'user' => User::query()->visible()->findOrFail($data['id']),
        };

        if ($target instanceof Post) {
            $this->authorize('view', $target);
        }

        $reports->create($request->user(), $target, ReportReason::from($data['reason']), $data['description'] ?? null);

        return ApiResponse::success(null, 'Rahmat! Xabaringiz moderatorlarga yuborildi.', 201);
    }
}
