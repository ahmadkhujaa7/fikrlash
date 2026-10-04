@extends('layouts.app')
@section('title', $user->name.' (@'.$user->username.')')
@section('description', $user->bio ?: $user->name.' — Fikrlash.uz dagi fikrlari va g‘oyalari.')
@section('og_type', 'profile')

@section('content')
    @include('profile.header')

    <x-tabs :items="[
        ['label' => 'Fikrlar', 'href' => route('profile.show', $user->username), 'active' => $tab === 'posts'],
        ['label' => 'Javoblar', 'href' => route('profile.replies', $user->username), 'active' => $tab === 'replies'],
    ]" />

    @if ($tab === 'posts')
        @include('partials.feed', [
            'emptyTitle' => $isOwner ? 'Birinchi fikringizni yozing' : 'Hali fikr yozilmagan',
            'emptyText' => $isOwner ? 'Bu yerda sizning postlaringiz chiqadi.' : null,
        ])
    @else
        <div class="stream">
            @forelse ($comments as $comment)
                <article class="px-4 py-4 sm:px-6">
                    <p class="text-sm text-muted">
                        <a href="{{ $comment->post->user->profileUrl() }}" class="hover:underline">{{ '@'.$comment->post->user->username }}</a> postiga:
                        <a href="{{ $comment->url() }}" class="text-ink-soft hover:underline">“{{ $comment->post->excerpt(70) }}”</a>
                    </p>
                    <div class="mt-1.5 break-words text-[15px] leading-relaxed [&_a]:text-lapis">{!! \App\Support\ContentFormatter::toHtml($comment->content) !!}</div>
                    <p class="mt-1 text-sm text-muted">{{ \App\Support\Time::short($comment->created_at) }}</p>
                </article>
            @empty
                <x-empty-state icon="chat" title="Javoblar yo‘q" />
            @endforelse
        </div>
        {{ $comments->links() }}
    @endif
@endsection
