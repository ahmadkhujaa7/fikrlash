@php
    use App\Support\ContentFormatter;
    use App\Support\Time;
    $me = auth()->user();
    $isReply = $comment->parent_id !== null;
@endphp
<div id="comment-{{ $comment->id }}" data-item class="{{ $isReply ? 'pt-3' : 'px-4 py-5 sm:px-5' }}">
    <div class="flex gap-3">
        <a href="{{ $comment->user->profileUrl() }}" class="shrink-0" tabindex="-1" aria-hidden="true">
            <x-avatar :user="$comment->user" :size="$isReply ? 'xs' : 'sm'" />
        </a>
        <div class="min-w-0 flex-1">
            <div class="flex items-baseline gap-1.5 text-sm leading-tight">
                <a href="{{ $comment->user->profileUrl() }}" class="font-semibold hover:underline">{{ $comment->user->name }}</a>
                <span class="truncate text-muted">{{ '@'.$comment->user->username }}</span>
                <time class="shrink-0 text-muted" datetime="{{ $comment->created_at->toIso8601String() }}" title="{{ Time::full($comment->created_at) }}">{{ Time::short($comment->created_at) }}</time>
            </div>
            <div class="mt-1 break-words text-[15px] leading-relaxed text-ink [&_a]:text-lapis [&_a:hover]:underline">{!! ContentFormatter::toHtml($comment->content) !!}</div>

            <div class="-ml-2 mt-1 flex items-center gap-1 text-sm text-muted">
                @auth
                    <button type="button" @class(['flex items-center gap-1 rounded-full px-2 py-1 hover:text-anor', 'text-anor' => $comment->is_liked])
                            x-data="toggle({ active: {{ $comment->is_liked ? 'true' : 'false' }}, count: {{ (int) $comment->likes_count }}, url: '{{ route('api.v1.comments.like', $comment) }}', onKey: 'liked', countKey: 'likes_count' })"
                            @click="flip" :class="{ 'text-anor': active }" :aria-pressed="active" aria-label="Izohni yoqtirish">
                        <svg class="size-4" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" fill="{{ $comment->is_liked ? 'currentColor' : 'none' }}" :fill="active ? 'currentColor' : 'none'" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z"/></svg>
                        <span x-text="count || ''">{{ $comment->likes_count ?: '' }}</span>
                    </button>
                    <button type="button" class="rounded-full px-2 py-1 hover:text-ink" @click="reply({{ $comment->id }}, '{{ $comment->user->username }}')">Javob berish</button>
                    @can('delete', $comment)
                        <button type="button" class="rounded-full px-2 py-1 hover:text-anor" @click="remove({{ $comment->id }}, '{{ route('comments.destroy', $comment) }}')">O‘chirish</button>
                    @elsecan('interact', $comment)
                        <button type="button" class="rounded-full px-2 py-1 hover:text-anor" @click="$dispatch('report', { type: 'comment', id: {{ $comment->id }} })">Shikoyat</button>
                    @endcan
                @else
                    @if ($comment->likes_count)
                        <span class="flex items-center gap-1 px-2 py-1"><x-ico name="heart" size="size-4" /> {{ $comment->likes_count }}</span>
                    @endif
                @endauth
            </div>

            @unless ($isReply)
                <div data-replies class="mt-1 space-y-1 border-l border-line pl-4">
                    @foreach ($comment->replies as $reply)
                        @include('partials.comment', ['comment' => $reply])
                    @endforeach
                </div>
            @endunless
        </div>
    </div>
</div>
