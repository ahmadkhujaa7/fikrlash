<?php

namespace App\Http\Controllers;

use App\Services\Chat\ChatService;
use App\Services\Social\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Menyudagi belgilar: o‘qilmagan bildirishnomalar va xabarlar (bitta so‘rov bilan). */
class BadgeController extends Controller
{
    public function __invoke(Request $request, NotificationService $notifications, ChatService $chat): JsonResponse
    {
        return response()->json([
            'notifications' => $notifications->unreadCount($request->user()),
            'messages' => $chat->unreadCount($request->user()),
        ])->header('Cache-Control', 'no-store, private');
    }
}
