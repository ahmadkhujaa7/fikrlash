<?php

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Services\Social\AnnouncementService;
use App\Services\Social\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function index(Request $request, AnnouncementService $announcements): View
    {
        $items = $request->user()->notifications()
            ->with(['actor', 'subject'])
            ->latest('id')
            ->paginate(config('fikrlash.notifications.per_page'));

        // Sahifada ko‘rsatilgan e'lonlar — statistikada "ko‘rdi" bo‘lib hisoblanadi.
        $announcements->markSeen($request->user(), $items->getCollection()
            ->filter(fn ($n) => $n->type === NotificationType::Announcement && $n->subject)
            ->pluck('subject_id'));

        // Hammasi o‘qilgan deb belgilanadi; xotiradagi modellar eski holatda — yangilari sahifada ajralib turadi.
        $this->notifications->markAllRead($request->user());

        return view('notifications.index', ['notifications' => $items]);
    }

    public function readAll(Request $request): RedirectResponse|JsonResponse
    {
        $this->notifications->markAllRead($request->user());

        return $request->expectsJson() ? response()->json(['ok' => true]) : back();
    }
}
