{{-- Suhbatlar ro‘yxati (ro‘yxat sahifasi va uning avtomatik yangilanishi uchun). --}}
@php
    use App\Services\Chat\MessagePresenter;
    use App\Support\Time;
@endphp
<div data-inbox-list>
    @forelse ($conversations as $conversation)
        @php
            $other = $conversation->otherUser($me);
            $last = $conversation->lastMessage;
            $unread = (int) $conversation->unread_count;
            $at = $conversation->last_message_at;
            $when = $at ? ($at->isToday() ? $at->format('H:i') : ($at->isYesterday() ? 'Kecha' : Time::date($at))) : '';
            $online = MessagePresenter::presence($other)['online'];
        @endphp
        @if ($other)
            <a href="{{ route('messages.show', $conversation) }}"
               class="flex items-center gap-3.5 px-4 py-3.5 transition-colors hover:bg-sunken/60 active:bg-sunken sm:px-6">
                <span class="relative shrink-0">
                    <x-avatar :user="$other" size="md" />
                    @if ($online)<span class="absolute bottom-0 right-0 size-3 rounded-full bg-firuza ring-[2.5px] ring-paper" title="Onlayn"></span>@endif
                </span>
                <span class="min-w-0 flex-1">
                    <span class="flex items-baseline gap-2">
                        <span class="flex min-w-0 items-center gap-1 text-[15px] font-medium text-ink"><span class="truncate">{{ $other->name }}</span><x-verified :user="$other" /></span>
                        <span class="ml-auto shrink-0 text-[12px] tabular-nums {{ $unread ? 'font-medium text-lapis' : 'text-muted' }}">{{ $when }}</span>
                    </span>
                    <span class="mt-0.5 flex items-center gap-2">
                        <span class="flex min-w-0 items-center gap-1 text-[14px] {{ $unread ? 'font-medium text-ink' : 'text-muted' }}">
                            @if ($last?->user_id === $me->id)<span class="shrink-0 text-muted">Siz:</span>@endif
                            @if ($last?->isVoice() && ! $last->isRemoved())<x-ico name="mic" size="size-4" class="shrink-0 text-lapis" />@endif
                            <span class="truncate {{ $last?->isRemoved() ? 'italic' : '' }}">{{ $last?->snippet(70) ?? '—' }}</span>
                        </span>
                        @if ($conversation->my_blocked_at)
                            <span class="ml-auto shrink-0 rounded-full bg-anor-soft px-2 py-0.5 text-[11px] font-medium text-anor">Bloklangan</span>
                        @elseif ($unread)
                            <span class="ml-auto grid h-5 min-w-5 shrink-0 place-items-center rounded-full bg-lapis px-1.5 text-[11px] font-semibold tabular-nums text-white">{{ $unread > 99 ? '99+' : $unread }}</span>
                        @endif
                    </span>
                </span>
            </a>
        @endif
    @empty
        <x-empty-state icon="chat" title="Hali xabarlar yo‘q" text="Kimgadir yozish uchun “Yangi xabar” tugmasini bosing yoki uning profilidagi “Xabar” tugmasidan foydalaning." />
    @endforelse

    @if ($conversations->hasMorePages())
        <div class="flex justify-center py-6"><a href="{{ $conversations->nextPageUrl() }}" class="btn btn-secondary btn-sm">Yana ko‘rsatish</a></div>
    @endif
</div>
