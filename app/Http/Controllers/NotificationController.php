<?php

namespace App\Http\Controllers;

use App\Services\Social\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function index(Request $request): View
    {
        $items = $request->user()->notifications()
            ->with(['actor', 'subject'])
            ->latest('id')
            ->paginate(config('fikrlash.notifications.per_page'));

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
