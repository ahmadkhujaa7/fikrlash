<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TokenResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class TokenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(TokenResource::collection($request->user()->tokens()->latest()->get()));
    }

    public function destroy(Request $request, int $token): JsonResponse
    {
        $deleted = $request->user()->tokens()->whereKey($token)->delete();

        return $deleted
            ? ApiResponse::success(null, 'Token bekor qilindi.')
            : ApiResponse::error('Token topilmadi.', 404);
    }

    /** Joriy tokendan boshqa barcha tokenlarni bekor qilish. */
    public function destroyOthers(Request $request): JsonResponse
    {
        $current = $request->user()->currentAccessToken();
        $count = $request->user()->tokens()
            ->when($current instanceof PersonalAccessToken, fn ($q) => $q->whereKeyNot($current->getKey()))
            ->delete();

        return ApiResponse::success(['revoked' => $count], 'Boshqa tokenlar bekor qilindi.');
    }
}
