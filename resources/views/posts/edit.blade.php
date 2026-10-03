@extends('layouts.app')
@section('title', 'Postni tahrirlash')

@section('content')
    <div class="flex items-center gap-3 border-b border-line px-2 py-2">
        <a href="{{ $post->isPublished() ? route('posts.show', $post) : route('posts.drafts') }}" class="btn-ghost rounded-full p-2" aria-label="Orqaga"><x-ico name="arrow-left" /></a>
        <h1 class="font-semibold">{{ $post->isPublished() ? 'Postni tahrirlash' : 'Qoralamani tahrirlash' }}</h1>
    </div>
    @include('partials.composer', ['post' => $post])
@endsection
