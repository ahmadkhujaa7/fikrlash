<?php

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Models\Announcement;
use App\Models\Message;
use App\Models\User;
use App\Services\Chat\ChatService;
use App\Services\Social\NotificationService;
use App\Support\NotificationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Menyudagi belgilar: o‘qilmagan bildirishnomalar va xabarlar (bitta so‘rov bilan).
 * ?latest=1 — brauzer bildirishnomasi uchun eng so‘nggi o‘qilmagan bildirishnoma va xabar ham qaytadi.
 */
class BadgeController extends Controller
{
    public function __invoke(Request $request, NotificationService $notifications, ChatService $chat): JsonResponse
    {
        $user = $request->user();
        $data = [
            'notifications' => $notifications->unreadCount($user),
            'messages' => $chat->unreadCount($user),
        ];

        if ($request->boolean('latest')) {
            $data['latest'] = [
                'notification' => $data['notifications'] > 0 ? $this->latestNotification($user) : null,
                'message' => $data['messages'] > 0 ? $this->latestMessage($user) : null,
            ];
        }

        return response()->json($data)->header('Cache-Control', 'no-store, private');
    }

    private function latestNotification(User $user): ?array
    {
        $n = $user->notifications()->unread()->with(['actor', 'subject'])->latest('id')->first();
        if (! $n) {
            return null;
        }

        $p = NotificationPresenter::present($n);
        $isAnnouncement = $n->type === NotificationType::Announcement;

        return [
            'id' => $n->id,
            'title' => $isAnnouncement ? 'E’lon' : ($n->actor?->name ?? config('app.name')),
            'body' => Str::limit($isAnnouncement && $n->subject instanceof Announcement ? $n->subject->title : $p['text'], 140),
            'url' => $isAnnouncement ? route('notifications.index') : ($p['url'] ?? route('notifications.index')),
            'icon' => $n->actor?->avatarUrl(),
        ];
    }

    private function latestMessage(User $user): ?array
    {
        $message = Message::query()
            ->join('conversation_participants as p', function ($join) use ($user) {
                $join->on('p.conversation_id', '=', 'messages.conversation_id')->where('p.user_id', '=', $user->id);
            })
            ->where('messages.user_id', '!=', $user->id)
            ->whereNull('messages.removed_at')
            ->whereColumn('messages.id', '>', 'p.last_read_message_id')
            ->whereColumn('messages.id', '>', 'p.cleared_message_id')
            ->select('messages.*')
            ->with('user')
            ->latest('messages.id')
            ->first();

        if (! $message) {
            return null;
        }

        return [
            'id' => $message->id,
            'title' => $message->user?->name ?? 'Yangi xabar',
            'body' => $message->snippet(140),
            'url' => route('messages.show', $message->conversation_id),
            'icon' => $message->user?->avatarUrl(),
        ];
    }
}
