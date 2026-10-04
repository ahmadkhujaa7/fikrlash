@extends('layouts.app')
@section('title', 'Bildirishnomalar')

@php
    use App\Enums\NotificationType as T;
    use App\Support\NotificationPresenter;
    $icons = [
        T::Followed->value => ['user', 'text-ink-soft bg-sunken'],
        T::PostLiked->value => ['heart', 'text-anor bg-anor-soft'],
        T::PostCommented->value => ['chat', 'text-ink-soft bg-sunken'],
        T::CommentReplied->value => ['reply', 'text-ink-soft bg-sunken'],
        T::Mentioned->value => ['hashtag', 'text-ink-soft bg-sunken'],
        T::PostModerated->value => ['shield', 'text-ink-soft bg-sunken'],
        T::System->value => ['bulb', 'text-ink-soft bg-sunken'],
    ];
@endphp

@section('content')
    <x-page-header title="Bildirishnomalar" class="border-b border-line" />
    <ul class="stream">
        @forelse ($notifications as $n)
            @php
                $p = NotificationPresenter::present($n);
                [$icon, $tone] = $icons[$n->type->value];
            @endphp
            <li class="relative flex gap-4 px-4 py-5 sm:px-6 {{ $n->isRead() ? '' : 'bg-sunken/70' }}">
                <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-full {{ $tone }}">
                    <x-ico :name="$icon" size="size-5" :solid="$n->type === T::PostLiked" />
                </span>
                <div class="min-w-0 flex-1">
                    @if ($n->actor)
                        <a href="{{ $n->actor->profileUrl() }}" class="relative z-10 mb-1 inline-block"><x-avatar :user="$n->actor" size="xs" /></a>
                    @endif
                    <p class="text-[15px] leading-snug">
                        @if ($n->actor)<span class="font-semibold">{{ $n->actor->name }}</span>@endif
                        {{ $p['text'] }}
                    </p>
                    @if ($p['excerpt'])
                        <p class="mt-1 line-clamp-2 text-sm text-muted">{{ $p['excerpt'] }}</p>
                    @endif
                    <p class="mt-1 text-xs text-muted">{{ \App\Support\Time::short($n->created_at) }}</p>
                    @if ($p['url'])
                        <a href="{{ $p['url'] }}" class="absolute inset-0" aria-label="Ochish"></a>
                    @endif
                </div>
            </li>
        @empty
            <x-empty-state icon="bell" title="Hozircha jimjitlik" text="Kimdir fikringizni yoqtirsa, izoh qoldirsa yoki sizga obuna bo‘lsa — shu yerda ko‘rasiz." />
        @endforelse
    </ul>
    {{ $notifications->links() }}
@endsection
