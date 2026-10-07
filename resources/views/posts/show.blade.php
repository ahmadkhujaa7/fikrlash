@extends('layouts.app')
@php
    $isArticle = $post->isArticle();
@endphp

@section('title', $isArticle ? $post->title : $post->user->name.': “'.$post->excerpt(60).'”')
@section('description', $post->summary(160))
@section('og_type', 'article')
@section('screen', '1')
@if ($post->image_path)
    @section('og_image', $post->imageUrl())
@endif

@push('meta')
    @if ($post->isPublished())
        <script type="application/ld+json" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
            {!! json_encode(array_filter([
                '@context' => 'https://schema.org',
                '@type' => $isArticle ? 'Article' : 'SocialMediaPosting',
                'headline' => $isArticle ? $post->title : $post->excerpt(110),
                'articleBody' => $post->content,
                'image' => $post->imageUrl(),
                'datePublished' => $post->published_at?->toIso8601String(),
                'dateModified' => ($post->edited_at ?? $post->published_at)?->toIso8601String(),
                'url' => $post->url(),
                'author' => ['@type' => 'Person', 'name' => $post->user->name, 'url' => $post->user->profileUrl()],
                'interactionStatistic' => [
                    ['@type' => 'InteractionCounter', 'interactionType' => 'https://schema.org/LikeAction', 'userInteractionCount' => $post->likes_count],
                    ['@type' => 'InteractionCounter', 'interactionType' => 'https://schema.org/CommentAction', 'userInteractionCount' => $post->comments_count],
                ],
            ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
        </script>
    @endif
@endpush

@section('content')
    {{-- Ilova paneli. Orqaga: sayt ichidan kelgan bo‘lsa — oldingi joyga (lenta o‘sha joyidan davom etadi), aks holda bosh sahifaga --}}
    <div class="app-bar" x-data="{ scrolled: false }" @scroll.window.passive="scrolled = window.scrollY > 120">
        <a href="{{ route('home') }}" @click.prevent="backOr('{{ route('home') }}')" class="icon-btn" aria-label="Orqaga"><x-ico name="arrow-left" /></a>
        <div class="min-w-0 flex-1">
            <p class="text-[15px] font-medium transition-opacity" :class="scrolled && 'hidden'">{{ $isArticle ? 'Maqola' : 'Fikr' }}</p>
            {{-- Pastga tushilganda — muallif nomi (qaysi postni o‘qiyotganingiz doim ko‘rinadi) --}}
            <p x-show="scrolled" x-cloak class="flex min-w-0 items-center gap-2 text-[14px] font-medium">
                <x-avatar :user="$post->user" size="xs" />
                <span class="truncate">{{ $isArticle ? $post->title : $post->user->name }}</span>
            </p>
        </div>
        <button type="button" class="icon-btn" @click="sharePost(@js($post->url()), @js($post->isArticle() ? (string) $post->title : $post->summary(120)), {{ $post->id }})" aria-label="Ulashish"><x-ico name="share" size="size-[19px]" /></button>
    </div>

    @include('posts._detail')
@endsection
