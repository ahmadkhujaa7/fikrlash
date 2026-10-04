@extends('layouts.app')

@section('title', $post->user->name.': “'.$post->excerpt(60).'”')
@section('description', $post->excerpt(160))
@section('og_type', 'article')
@if ($post->image_path)
    @section('og_image', $post->imageUrl())
@endif

@push('meta')
    @if ($post->isPublished())
        <script type="application/ld+json" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
            {!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'SocialMediaPosting',
                'headline' => $post->excerpt(110),
                'articleBody' => $post->content,
                'datePublished' => $post->published_at?->toIso8601String(),
                'dateModified' => ($post->edited_at ?? $post->published_at)?->toIso8601String(),
                'url' => $post->url(),
                'author' => ['@type' => 'Person', 'name' => $post->user->name, 'url' => $post->user->profileUrl()],
                'interactionStatistic' => [
                    ['@type' => 'InteractionCounter', 'interactionType' => 'https://schema.org/LikeAction', 'userInteractionCount' => $post->likes_count],
                    ['@type' => 'InteractionCounter', 'interactionType' => 'https://schema.org/CommentAction', 'userInteractionCount' => $post->comments_count],
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
        </script>
    @endif
@endpush

@section('content')
    <div class="flex items-center gap-2 border-b border-line px-2 py-2 sm:px-3">
        {{-- Orqaga: sayt ichidan kelgan bo‘lsa — oldingi joyga (lenta o‘sha joyidan davom etadi), aks holda bosh sahifaga --}}
        <a href="{{ route('home') }}" x-data @click.prevent="backOr('{{ route('home') }}')"
           class="grid size-10 place-items-center rounded-full text-ink-soft hover:bg-sunken hover:text-ink" aria-label="Orqaga"><x-ico name="arrow-left" /></a>
        <p class="text-[15px] font-medium">Fikr</p>
    </div>

    @include('posts._detail')
@endsection
