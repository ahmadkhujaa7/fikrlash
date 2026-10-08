<?php

namespace App\Http\Controllers;

use App\Exceptions\ChatException;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use App\Services\Chat\ChatMediaStore;
use App\Services\Chat\ChatService;
use App\Services\Chat\MessagePresenter;
use App\Services\Chat\VoiceStore;
use App\Services\Feed\SearchService;
use App\Support\UploadLimits;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * Shaxsiy xabarlar (veb). Real vaqt — qisqa so‘rovlar (poll) orqali: alohida WebSocket server
 * kerak emas, oddiy hostingda va `php artisan serve` bilan ham ishlaydi.
 */
class MessageController extends Controller
{
    public function __construct(private ChatService $chat, private MessagePresenter $present) {}

    /** Suhbatlar ro‘yxati. ?fragment=list — faqat ro‘yxat (avtomatik yangilanish uchun). */
    public function index(Request $request): View
    {
        $me = $request->user();

        $conversations = Conversation::query()
            ->join('conversation_participants as me', function ($join) use ($me) {
                $join->on('me.conversation_id', '=', 'conversations.id')->where('me.user_id', '=', $me->id);
            })
            ->whereNotNull('conversations.last_message_id')
            ->whereColumn('conversations.last_message_id', '>', 'me.cleared_message_id')
            ->select('conversations.*', 'me.last_read_message_id as my_read_id', 'me.blocked_at as my_blocked_at')
            ->selectSub(fn ($q) => $q->from('messages')->selectRaw('COUNT(*)')
                ->whereColumn('messages.conversation_id', 'conversations.id')
                ->where('messages.user_id', '!=', $me->id)
                ->whereNull('messages.removed_at')
                ->whereColumn('messages.id', '>', 'me.last_read_message_id')
                ->whereColumn('messages.id', '>', 'me.cleared_message_id'), 'unread_count')
            ->with(['participants.user', 'lastMessage'])
            ->orderByDesc('conversations.last_message_at')
            ->simplePaginate(30);

        $view = $request->query('fragment') === 'list' ? 'messages._list' : 'messages.index';

        return view($view, ['conversations' => $conversations, 'me' => $me]);
    }

    /** Postni yuborish oynasi uchun: so‘nggi suhbatdoshlar yoki qidiruv natijalari. */
    public function recipients(Request $request, SearchService $search): JsonResponse
    {
        $me = $request->user();
        $q = trim((string) $request->query('q', ''));

        $users = $q === ''
            ? Conversation::query()->forUser($me)->whereNotNull('last_message_id')->latest('last_message_at')->limit(12)
                ->with('participants.user')->get()->map(fn (Conversation $c) => $c->otherUser($me))->filter()
            : $search->users($q, 15);

        return response()->json(['users' => $users
            ->reject(fn (User $u) => $u->is($me))
            ->unique('id')->values()
            ->map(function (User $u) use ($me) {
                $reason = $this->chat->cannotMessage($me, $u);

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'username' => $u->username,
                    'avatar' => $u->avatarUrl(),
                    'initials' => $u->initials(),
                    'tone' => $u->tone(),
                    'verified' => $u->isVerified(),
                    'can' => $reason === null,
                    'reason' => $reason,
                ];
            })]);
    }

    /** Postni bir yoki bir nechta odamga xabar sifatida yuborish (ixtiyoriy izoh bilan). */
    public function share(Request $request): JsonResponse
    {
        $me = $request->user();
        $data = $request->validate([
            'post_id' => ['required', 'integer', 'exists:posts,id'],
            'user_ids' => ['required', 'array', 'min:1', 'max:10'],
            'user_ids.*' => ['integer', 'distinct'],
            'body' => ['nullable', 'string', 'max:'.config('fikrlash.chat.message_max')],
        ], ['user_ids.required' => 'Kimga yuborishni tanlang.', 'user_ids.max' => 'Bir martada ko‘pi bilan 10 kishiga.']);

        $sent = 0;
        $failed = [];
        $users = User::query()->whereIn('id', $data['user_ids'])->get();

        foreach ($users as $user) {
            try {
                if ($reason = $this->chat->cannotMessage($me, $user)) {
                    throw new ChatException($reason);
                }
                $conversation = $this->chat->between($me, $user);
                $this->chat->send($conversation, $me, ['post_id' => $data['post_id'], 'body' => $data['body'] ?? null]);
                $sent++;
            } catch (ChatException $e) {
                $failed[] = ['name' => $user->name, 'reason' => $e->getMessage()];
            }
        }

        return response()->json([
            'sent' => $sent,
            'failed' => $failed,
            'message' => $sent
                ? ($sent === 1 ? 'Yuborildi.' : "{$sent} kishiga yuborildi.")
                : (count($failed) === 1 ? $failed[0]['name'].': '.$failed[0]['reason'] : 'Yuborib bo‘lmadi.'),
        ], $sent ? 200 : 422);
    }

    /** Profildagi "Xabar yozish": mavjud suhbatga yoki yangisiga. */
    public function with(Request $request, User $user): RedirectResponse
    {
        $me = $request->user();
        $existing = Conversation::query()->where('pair_key', Conversation::pairKey($me->id, $user->id))->first();
        if ($existing) {
            return redirect()->route('messages.show', $existing);
        }

        if ($reason = $this->chat->cannotMessage($me, $user)) {
            return back()->with('toast', $reason);
        }

        try {
            $conversation = $this->chat->between($me, $user);
        } catch (ChatException $e) {
            return back()->with('toast', $e->getMessage());
        }

        return redirect()->route('messages.show', $conversation);
    }

    public function show(Request $request, Conversation $conversation): View
    {
        $this->authorize('view', $conversation);
        $me = $request->user();
        $conversation->load('participants.user');
        $mine = $conversation->participantFor($me);
        $other = $conversation->otherUser($me);
        $theirs = $conversation->participants->firstWhere('user_id', '!=', $me->id);

        $size = (int) config('fikrlash.chat.page_size');
        $messages = $this->visible($conversation, $mine)->latest('id')->limit($size + 1)->get();
        $hasMore = $messages->count() > $size;
        $messages = $messages->take($size)->reverse()->values();

        if ($messages->isNotEmpty()) {
            $this->chat->markRead($conversation, $me, (int) $messages->last()->id);
        }

        return view('messages.show', [
            'conversation' => $conversation,
            'other' => $other,
            'config' => [
                'messages' => $this->present->many($messages, $me),
                'hasMore' => $hasMore,
                'peerRead' => (int) ($theirs?->last_read_message_id ?? 0),
                'presence' => MessagePresenter::presence($other),
                'peerName' => $other?->name,
                'cannotSend' => $other ? $this->chat->cannotMessage($me, $other, $conversation) : 'Suhbatdosh topilmadi.',
                'blocked' => $mine?->blocked_at !== null,
                'now' => now()->subSeconds(2)->toIso8601String(),
                'reactions' => config('fikrlash.chat.reactions'),
                'maxLength' => (int) config('fikrlash.chat.message_max'),
                'maxVoice' => (int) config('fikrlash.chat.voice_max_seconds'),
                'media' => [
                    'maxFiles' => (int) config('fikrlash.chat.max_attachments'),
                    'imageMaxWidth' => (int) config('fikrlash.chat.image_max_width'),
                    // Brauzer rasmni kichraytiradi; asl fayl uchun chegarani biroz kengroq olamiz.
                    'imageMaxBytes' => (int) config('fikrlash.chat.image_max_kb') * 1024 * 4,
                    'videoMaxBytes' => min((int) config('fikrlash.chat.video_max_kb') * 1024, UploadLimits::perFile()),
                    'videoMaxSeconds' => (int) config('fikrlash.chat.video_max_seconds'),
                    'postMaxBytes' => UploadLimits::perRequest(),
                ],
                'pollMs' => (int) config('fikrlash.chat.poll_seconds') * 1000,
                'urls' => [
                    'send' => route('messages.store', $conversation),
                    'poll' => route('messages.poll', $conversation),
                    'history' => route('messages.history', $conversation),
                    'read' => route('messages.read', $conversation),
                    'typing' => route('messages.typing', $conversation),
                    'message' => url('/messages/'.$conversation->id.'/__ID__'),
                ],
            ],
        ]);
    }

    /** Yangi xabarlar (id > after) va o‘zgarganlari (tahrir, reaksiya, o‘chirish — updated_at >= since). */
    public function poll(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);
        $me = $request->user();
        $conversation->load('participants.user');
        $mine = $conversation->participantFor($me);
        $other = $conversation->otherUser($me);
        $theirs = $conversation->participants->firstWhere('user_id', '!=', $me->id);

        $after = max(0, (int) $request->query('after'));
        $since = $this->since($request->query('since'));

        $new = $this->visible($conversation, $mine)->where('messages.id', '>', $after)->orderBy('id')->limit(100)->get();
        $updated = $after > 0
            ? $this->visible($conversation, $mine)->where('messages.id', '<=', $after)->where('messages.updated_at', '>=', $since)->limit(100)->get()
            : collect();

        return response()->json([
            'messages' => $this->present->many($new, $me),
            'updated' => $this->present->many($updated, $me),
            'peerRead' => (int) ($theirs?->last_read_message_id ?? 0),
            'typing' => $other ? $this->chat->isTyping($conversation, $other) : false,
            'presence' => MessagePresenter::presence($other),
            'now' => now()->subSeconds(2)->toIso8601String(),
        ]);
    }

    /** Eski xabarlar (yuqoriga aylantirilganda). */
    public function history(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);
        $me = $request->user();
        $mine = $conversation->participantFor($me);
        $size = (int) config('fikrlash.chat.page_size');

        $messages = $this->visible($conversation, $mine)
            ->where('messages.id', '<', max(0, (int) $request->query('before')))
            ->latest('id')->limit($size + 1)->get();

        return response()->json([
            'messages' => $this->present->many($messages->take($size)->reverse(), $me),
            'hasMore' => $messages->count() > $size,
        ]);
    }

    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);
        $max = (int) config('fikrlash.chat.message_max');
        $fileKb = max((int) config('fikrlash.chat.image_max_kb'), (int) config('fikrlash.chat.video_max_kb'));

        $data = $request->validate([
            'body' => ['nullable', 'string', "max:{$max}"],
            'voice' => ['nullable', 'file', 'max:'.config('fikrlash.chat.voice_max_kb')],
            'duration' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'waveform' => ['nullable', 'string', 'max:2000'],
            'files' => ['nullable', 'array', 'max:'.config('fikrlash.chat.max_attachments')],
            'files.*' => ['file', "max:{$fileKb}"],
            'posters' => ['nullable', 'array'],
            'posters.*' => ['nullable', 'file', 'max:4096'],
            'durations' => ['nullable', 'array'],
            'durations.*' => ['nullable', 'integer', 'min:0', 'max:36000'],
            'dims' => ['nullable', 'array'],
            'dims.*' => ['nullable', 'string', 'regex:/^\d{1,5}x\d{1,5}$/'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            // Kompyuterda (GPS yo‘q) aniqlik yuzlab km bo‘lishi mumkin — rad etilmaydi.
            'acc' => ['nullable', 'numeric', 'min:0'],
            'post_id' => ['nullable', 'integer'],
            'reply_to_id' => ['nullable', 'integer'],
        ], [
            'body.max' => "Xabar {$max} belgidan oshmasligi kerak.",
            'voice.max' => 'Ovozli xabar juda katta.',
            'files.max' => 'Bir xabarda ko‘pi bilan '.config('fikrlash.chat.max_attachments').' ta fayl.',
            'files.*.file' => 'Fayl yuklanmadi — hajmi juda katta bo‘lishi mumkin.',
            'files.*.uploaded' => 'Fayl yuklanmadi — hajmi juda katta bo‘lishi mumkin.',
            'files.*.max' => 'Fayl juda katta.',
        ]);

        try {
            $message = $this->chat->send($conversation->load('participants.user'), $request->user(), [
                'body' => $data['body'] ?? null,
                'voice' => $request->file('voice'),
                'duration' => $data['duration'] ?? null,
                'waveform' => $data['waveform'] ?? null,
                'files' => $request->file('files', []),
                'posters' => $request->file('posters', []),
                'durations' => $data['durations'] ?? [],
                'dims' => $data['dims'] ?? [],
                'location' => isset($data['lat'], $data['lng']) ? ['lat' => $data['lat'], 'lng' => $data['lng'], 'acc' => $data['acc'] ?? null] : null,
                'post_id' => $data['post_id'] ?? null,
                'reply_to_id' => $data['reply_to_id'] ?? null,
            ]);
        } catch (ChatException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => $this->presentOne($message, $request->user())], 201);
    }

    /** Rasm, video yoki video muqovasi — faqat suhbat ishtirokchilariga. */
    public function media(Request $request, Conversation $conversation, Message $message, MessageAttachment $attachment, ChatMediaStore $store): BaseResponse
    {
        $this->authorize('view', $conversation);
        abort_if($message->isRemoved() || $attachment->message_id !== $message->id, 404);
        abort_unless($this->visibleTo($message, $conversation->participantFor($request->user())), 404);

        return $store->response($attachment, $request->boolean('poster'));
    }

    public function update(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        $this->authorize('view', $conversation);
        $max = (int) config('fikrlash.chat.message_max');
        $data = $request->validate(['body' => ['nullable', 'string', "max:{$max}"]]);

        return $this->attempt(fn () => $this->chat->edit($message, $request->user(), (string) ($data['body'] ?? '')), $request);
    }

    public function destroy(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        $this->authorize('view', $conversation);

        return $this->attempt(fn () => $this->chat->remove($message, $request->user()), $request);
    }

    public function react(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        $this->authorize('view', $conversation);
        abort_unless($this->visibleTo($message, $conversation->participantFor($request->user())), 404);
        $data = $request->validate(['emoji' => ['required', 'string', 'max:16']]);

        return $this->attempt(fn () => $this->chat->react($message, $request->user(), $data['emoji']), $request);
    }

    public function voice(Request $request, Conversation $conversation, Message $message, VoiceStore $voices): BaseResponse
    {
        $this->authorize('view', $conversation);
        abort_if($message->isRemoved() || ! $message->isVoice(), 404);
        abort_unless($this->visibleTo($message, $conversation->participantFor($request->user())), 404);

        return $voices->response($message);
    }

    public function read(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);
        $this->chat->markRead($conversation, $request->user(), (int) $request->input('id'));

        return response()->json(['unread' => $this->chat->unreadCount($request->user())]);
    }

    public function typing(Request $request, Conversation $conversation): Response
    {
        $this->authorize('view', $conversation);
        $this->chat->typing($conversation, $request->user());

        return response()->noContent();
    }

    public function block(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('view', $conversation);
        $this->chat->setBlocked($conversation, $request->user(), true);

        return back()->with('toast', 'Bloklandi. Bu odam sizga endi yoza olmaydi.');
    }

    public function unblock(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('view', $conversation);
        $this->chat->setBlocked($conversation, $request->user(), false);

        return back()->with('toast', 'Blokdan chiqarildi.');
    }

    public function clear(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('view', $conversation);
        $this->chat->clear($conversation, $request->user());

        return redirect()->route('messages.index')->with('toast', 'Suhbat tozalandi.');
    }

    /** Bu ishtirokchiga ko‘rinadigan xabarlar (tozalangandan keyingilari). */
    private function visible(Conversation $conversation, ?ConversationParticipant $participant): Builder
    {
        return Message::query()
            ->where('messages.conversation_id', $conversation->id)
            ->where('messages.id', '>', (int) ($participant?->cleared_message_id ?? 0))
            ->with(['reactions', 'replyTo.user', 'attachments', 'post.user']);
    }

    private function visibleTo(Message $message, ?ConversationParticipant $participant): bool
    {
        return $participant !== null && $message->id > $participant->cleared_message_id;
    }

    private function since(mixed $value): Carbon
    {
        try {
            $since = Carbon::parse((string) $value);
        } catch (\Throwable) {
            $since = now()->subMinute();
        }

        // Juda eski "since" bilan butun tarixni qayta yuklatib bo‘lmaydi.
        return $since->max(now()->subDay());
    }

    private function attempt(callable $action, Request $request): JsonResponse
    {
        try {
            $message = $action();
        } catch (ChatException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => $this->presentOne($message, $request->user())]);
    }

    private function presentOne(Message $message, User $viewer): array
    {
        return $this->present->one($message->fresh(['reactions', 'replyTo.user', 'attachments', 'post.user']), $viewer);
    }
}
