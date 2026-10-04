@extends('layouts.app')
@section('title', 'Postni tahrirlash')

@section('content')
    <div class="flex items-center gap-2 border-b border-line px-2 py-2 sm:px-3">
        <a href="{{ $post->isPublished() ? route('posts.show', $post) : route('profile.show', $post->user->username) }}" class="grid size-10 place-items-center rounded-full text-ink-soft hover:bg-sunken hover:text-ink" aria-label="Orqaga"><x-ico name="arrow-left" /></a>
        <h1 class="text-[15px] font-medium">{{ $post->isPublished() ? 'Postni tahrirlash' : 'Qoralamani tahrirlash' }}</h1>
    </div>
    @include('partials.composer', ['post' => $post])
@endsection
