<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\AvatarRequest;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Http\Resources\PostResource;
use App\Http\Resources\UserResource;
use App\Services\Account\AccountService;
use App\Services\Feed\FeedService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MeController extends Controller
{
    public function __construct(private AccountService $accounts) {}

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->accounts->updateProfile($request->user(), $request->validated());

        return ApiResponse::success(new UserResource($user), 'Profil yangilandi.');
    }

    public function avatar(AvatarRequest $request): JsonResponse
    {
        $user = $this->accounts->updateAvatar($request->user(), $request->file('avatar'));

        return ApiResponse::success(new UserResource($user), 'Rasm yangilandi.');
    }

    public function saved(Request $request, FeedService $feed): JsonResponse
    {
        return ApiResponse::success(PostResource::collection($feed->withViewerState($feed->saved($request->user()), $request->user())));
    }

    public function export(Request $request): JsonResponse
    {
        return ApiResponse::success($this->accounts->export($request->user()));
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'string']]);
        if (! Hash::check($request->input('password'), $request->user()->password)) {
            return ApiResponse::error('Parol noto‘g‘ri.', 422, ['password' => ['Parol noto‘g‘ri.']]);
        }

        $this->accounts->delete($request->user());

        return ApiResponse::success(null, 'Akkaunt o‘chirildi. '.config('fikrlash.accounts.deletion_grace_days').' kundan keyin butunlay o‘chiriladi.');
    }
}
