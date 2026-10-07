<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Services\Social\AnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Foydalanuvchi e'lonni bildirishnomalar sahifasida bosib ochdi — statistika uchun. */
class AnnouncementController extends Controller
{
    public function open(Request $request, Announcement $announcement, AnnouncementService $announcements): JsonResponse
    {
        abort_unless($announcements->markOpened($announcement, $request->user()), 404);

        return response()->json(['ok' => true]);
    }
}
