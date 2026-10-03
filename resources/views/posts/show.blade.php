@extends('layouts.app')

@section('title', $post->user->name.': “'.$post->excerpt(60).'”')
@section('description', $post->ai_summary ?: $post->excerpt(160))
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
    <div class="sticky top-[57px] z-20 flex items-center gap-3 border-b border-line bg-surface/90 px-2 py-2 backdrop-blur lg:top-0"
         @auth @if ($post->isPublished()) x-data="readTimer('{{ route('api.v1.posts.read', $post) }}')" @endif @endauth>
        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('home') }}" class="btn-ghost rounded-full p-2" aria-label="Orqaga"><x-ico name="arrow-left" /></a>
        <h1 class="font-semibold">Fikr</h1>
    </div>

    @include('partials.post-card', ['post' => $post, 'detail' => true])

    @if ($post->ai_summary && $post->isLong())
        <aside class="mx-4 mb-4 rounded-2xl bg-firuza-soft/60 px-4 py-3 sm:mx-5" aria-label="Qisqacha mazmun">
            <p class="flex items-center gap-1.5 text-sm font-semibold text-firuza"><x-ico name="sparkles" size="size-4" /> Qisqacha</p>
            <p class="mt-1 text-sm leading-relaxed text-ink-soft">{{ $post->ai_summary }}</p>
        </aside>
    @endif

    @if ($post->isPublished())
        <section id="comments" class="border-t border-line" x-data="comments({ url: '{{ route('posts.comments', $post) }}' })">
            <h2 class="sr-only">Izohlar</h2>
            @auth
                @can('interact', $post)
                    <form method="POST" action="{{ route('comments.store', $post) }}" @submit.prevent="submit($el)" class="flex gap-3 border-b border-line px-4 py-3 sm:px-5">
                        @csrf
                        <input type="hidden" name="parent_id" :value="replyTo">
                        <x-avatar :user="auth()->user()" size="sm" />
                        <div class="min-w-0 flex-1">
                            <p x-show="replyTo" x-cloak class="mb-1 flex items-center gap-2 text-sm text-muted">
                                <span><span x-text="'@' + replyName"></span> ga javob</span>
                                <button type="button" class="text-lapis hover:underline" @click="cancelReply">bekor qilish</button>
                            </p>
                            <textarea name="content" x-model="content" x-ref="input" rows="1" maxlength="{{ config('fikrlash.comments.max_length') }}"
                                      @input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
                                      @keydown.ctrl.enter="submit($el.form)" @keydown.meta.enter="submit($el.form)"
                                      placeholder="Izoh yozing…" aria-label="Izoh matni"
                                      class="block w-full resize-none border-0 bg-transparent px-0 py-2 text-[15px] focus:outline-none focus:ring-0"></textarea>
                            <div class="flex justify-end" x-show="content.trim().length > 0" x-cloak>
                                <button type="submit" class="btn btn-primary btn-sm" :disabled="sending">Yuborish</button>
                            </div>
                        </div>
                    </form>
                @endcan
            @else
                <p class="border-b border-line px-5 py-4 text-sm text-muted">
                    Izoh qoldirish uchun <a href="{{ route('login') }}" class="font-medium text-lapis hover:underline">kiring</a>
                    yoki <a href="{{ route('register') }}" class="font-medium text-lapis hover:underline">ro‘yxatdan o‘ting</a>.
                </p>
            @endauth

            <div x-ref="thread">
                <div class="flex justify-center py-8"><x-spinner /></div>
            </div>
            <noscript><p class="px-5 py-4 text-sm text-muted">Izohlarni ko‘rish uchun JavaScript yoqing.</p></noscript>
        </section>
    @endif

    @if ($related->isNotEmpty())
        <section class="border-t-8 border-sunken">
            <h2 class="px-5 pb-1 pt-5 font-serif text-lg font-semibold">O‘xshash fikrlar</h2>
            <div class="stream">
                @foreach ($related as $item)
                    @include('partials.post-card', ['post' => $item])
                @endforeach
            </div>
        </section>
    @endif
@endsection
