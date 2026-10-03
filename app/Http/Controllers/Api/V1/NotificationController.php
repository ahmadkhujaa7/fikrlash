<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Services\Social\NotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        $items = $request->user()->notifications()->with(['actor', 'subject'])->latest('id')->cursorPaginate(config('fikrlash.notifications.per_page'));

        return ApiResponse::success(NotificationResource::collection($items), meta: ['unread' => $this->notifications->unreadCount($request->user())]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponse::success(['unread' => $this->notifications->unreadCount($request->user())]);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        $this->notifications->markRead($notification);

        return ApiResponse::success(null);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        return ApiResponse::success(['updated' => $this->notifications->markAllRead($request->user())]);
    }
}
