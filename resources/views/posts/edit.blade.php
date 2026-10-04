@extends('layouts.app')
@section('title', $post->isArticle() ? 'Maqolani tahrirlash' : 'Postni tahrirlash')
@section('screen', '1')

@section('sidebar')
    <div class="space-y-3 px-1 text-[13px] leading-relaxed text-muted">
        <p>Tahrirlangan post yonida “tahrirlangan” belgisi chiqadi.</p>
        <p><kbd class="kbd">Ctrl</kbd> + <kbd class="kbd">Enter</kbd> — saqlash.</p>
        @if ($post->isArticle())
            <p>Olib tashlangan rasmlar saqlanganda serverdan ham o‘chiriladi.</p>
        @endif
    </div>
@endsection

@section('content')
    @if ($post->isArticle())
        @include('partials.article-editor', ['post' => $post])
    @else
        <div class="app-bar">
            <a href="{{ $post->isPublished() ? route('posts.show', $post) : route('profile.show', $post->user->username) }}" @click.prevent="backOr($el.href)" x-data class="icon-btn" aria-label="Orqaga"><x-ico name="arrow-left" /></a>
            <h1 class="text-[15px] font-medium">{{ $post->isPublished() ? 'Postni tahrirlash' : 'Qoralamani tahrirlash' }}</h1>
        </div>
        @include('partials.composer', ['post' => $post])
    @endif
@endsection
