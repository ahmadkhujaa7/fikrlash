{{--
  Post kartochkasi. $detail = true — post sahifasidagi to‘liq ko‘rinish.
  is_liked / is_saved — FeedService::withViewerState() orqali oldindan yuklangan (N+1 yo‘q).
--}}
@php
    use App\Enums\PostStatus;
    use App\Enums\PostVisibility;
    use App\Support\ContentFormatter;
    use App\Support\Time;

    $detail = $detail ?? false;
    $me = auth()->user();
    $author = $post->user;
    $url = route('posts.show', $post);
    $time = $post->published_at ?? $post->created_at;
@endphp
<article id="post-{{ $post->id }}" data-item
         @if ($post->isPublished()) data-track-view data-post-id="{{ $post->id }}" @endif
         class="flex gap-3 px-4 py-4 sm:px-5 {{ $detail ? 'pt-5' : 'transition-colors hover:bg-paper/50' }}">
    <a href="{{ $author->profileUrl() }}" class="shrink-0" aria-hidden="true" tabindex="-1">
        <x-avatar :user="$author" :size="$detail ? 'md' : 'md'" />
    </a>

    <div class="min-w-0 flex-1">
        <header class="flex items-start gap-2">
            <div class="min-w-0 flex-1 leading-tight">
                <a href="{{ $author->profileUrl() }}" class="font-semibold text-ink hover:underline">{{ $author->name }}</a>
                <span class="text-muted">{{ '@'.$author->username }}</span>
                @unless ($detail)
                    <a href="{{ $url }}" class="whitespace-nowrap text-muted hover:underline">
                        <time datetime="{{ $time->toIso8601String() }}" title="{{ Time::full($time) }}">{{ Time::short($time) }}</time>
                    </a>
                @endunless
                @if ($post->visibility === PostVisibility::Followers)
                    <span class="inline-flex translate-y-0.5 text-muted" title="Faqat obunachilar ko‘radi"><x-ico name="lock" size="size-4" /></span>
                @endif
            </div>

            <x-dropdown label="Post amallari">
                <x-slot:trigger class="-my-1.5 -mr-2 !p-1.5 text-muted"><x-ico name="dots" /></x-slot:trigger>
                <x-dropdown-item icon="link" @click="sharePost('{{ $url }}', ''); open = false">Havolani ulashish</x-dropdown-item>
                @if ($me && $post->isOwnedBy($me))
                    <x-dropdown-item icon="pencil" :href="route('posts.edit', $post)">Tahrirlash</x-dropdown-item>
                    <form method="POST" action="{{ route('posts.destroy', $post) }}" x-data @submit="confirm('Postni o‘chirasizmi?') || $event.preventDefault()">
                        @csrf @method('DELETE')
                        <x-dropdown-item icon="trash" type="submit" danger>O‘chirish</x-dropdown-item>
                    </form>
                @elseif ($me)
                    <x-dropdown-item icon="flag" @click="$dispatch('report', { type: 'post', id: {{ $post->id }} }); open = false" danger>Shikoyat qilish</x-dropdown-item>
                @endif
            </x-dropdown>
        </header>

        @if ($post->status !== PostStatus::Published)
            <div class="mt-1.5">
                @switch($post->status)
                    @case(PostStatus::Draft) <x-badge tone="neutral">Qoralama</x-badge> @break
                    @case(PostStatus::PendingModeration) <x-badge tone="amber">Moderator tekshiruvida — hozircha faqat sizga ko‘rinadi</x-badge> @break
                    @case(PostStatus::Hidden) <x-badge tone="anor">Yashirilgan</x-badge> @break
                @endswitch
            </div>
        @endif

        @if ($detail)
            <div class="prose-post mt-3 !text-[1.1875rem] !leading-[1.7]">{!! ContentFormatter::toHtml($post->content) !!}</div>
        @else
            @if ($post->isLong())
                <div class="prose-post mt-1" x-data="{ more: false }">
                    <div class="clamp-post" :class="{ 'clamp-post': !more }">{!! ContentFormatter::toHtml($post->content) !!}</div>
                    <button type="button" x-show="!more" @click="more = true" class="mt-1 font-sans text-sm font-medium text-lapis hover:underline">Ko‘proq o‘qish</button>
                </div>
            @else
                <div class="prose-post mt-1">{!! ContentFormatter::toHtml($post->content) !!}</div>
            @endif
        @endif

        @if ($post->image_path)
            <a href="{{ $detail ? $post->imageUrl() : $url }}" class="mt-3 block overflow-hidden rounded-2xl border border-line bg-sunken" @if ($detail) target="_blank" rel="noopener" @endif>
                <img src="{{ $post->imageUrl() }}" alt="Post rasmi" loading="lazy" class="{{ $detail ? 'w-full' : 'max-h-[30rem] w-full object-cover' }}">
            </a>
        @endif

        @if ($post->category || ($detail && $post->relationLoaded('tags') && $post->tags->isNotEmpty()))
            <div class="mt-3 flex flex-wrap gap-1.5">
                @if ($post->category)
                    <a href="{{ route('categories.show', $post->category) }}" class="chip !bg-firuza-soft !text-firuza">{{ $post->category->name }}</a>
                @endif
                @if ($detail && $post->relationLoaded('tags'))
                    @foreach ($post->tags as $tag)
                        <a href="{{ route('tags.show', $tag->slug) }}" class="chip">#{{ $tag->name }}</a>
                    @endforeach
                @endif
            </div>
        @endif

        @if ($detail)
            <p class="mt-4 text-sm text-muted">
                <time datetime="{{ $time->toIso8601String() }}">{{ Time::full($time) }}</time>@if ($post->edited_at)<span>, tahrirlangan</span>@endif
            </p>
        @endif

        @if ($post->isPublished())
            <footer class="-ml-2 mt-2 flex items-center justify-between text-muted sm:max-w-md">
                @auth
                    <button type="button" class="group flex items-center gap-1.5 rounded-full px-2 py-1.5 text-sm hover:text-anor"
                            x-data="toggle({ active: {{ $post->is_liked ? 'true' : 'false' }}, count: {{ (int) $post->likes_count }}, url: '{{ route('api.v1.posts.like', $post) }}', onKey: 'liked', countKey: 'likes_count' })"
                            @click="flip" :class="active && 'text-anor'" :aria-pressed="active" aria-label="Yoqtirish">
                        <span class="rounded-full p-1 group-hover:bg-anor-soft">
                            <svg class="size-5" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" :fill="active ? 'currentColor' : 'none'" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z"/></svg>
                        </span>
                        <span x-text="count || ''">{{ $post->likes_count ?: '' }}</span>
                    </button>
                @else
                    <a href="{{ route('login') }}" class="flex items-center gap-1.5 rounded-full px-2 py-1.5 text-sm hover:text-anor" aria-label="Yoqtirish uchun kiring">
                        <span class="p-1"><x-ico name="heart" /></span>{{ $post->likes_count ?: '' }}
                    </a>
                @endauth

                <a href="{{ $url }}#comments" class="group flex items-center gap-1.5 rounded-full px-2 py-1.5 text-sm hover:text-lapis" aria-label="Izohlar">
                    <span class="rounded-full p-1 group-hover:bg-lapis-soft"><x-ico name="chat" /></span>
                    <span @if ($detail) x-data="{ n: {{ (int) $post->comments_count }} }" @comments-count.window="n = $event.detail" x-text="n || ''" @endif>{{ $post->comments_count ?: '' }}</span>
                </a>

                <span class="flex items-center gap-1.5 px-2 py-1.5 text-sm" title="Ko‘rishlar">
                    <span class="p-1"><x-ico name="eye" /></span>{{ $post->views_count ? \Illuminate\Support\Number::abbreviate($post->views_count) : '' }}
                </span>

                <div class="flex items-center">
                    @auth
                        <button type="button" class="group rounded-full p-2 hover:text-firuza"
                                x-data="toggle({ active: {{ $post->is_saved ? 'true' : 'false' }}, count: 0, url: '{{ route('api.v1.posts.save', $post) }}', onKey: 'saved' })"
                                @click="flip().then(() => toast(active ? 'Saqlanganlarga qo‘shildi.' : 'Saqlanganlardan olib tashlandi.'))"
                                :class="active && 'text-firuza'" :aria-pressed="active" aria-label="Saqlash">
                            <svg class="size-5" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" :fill="active ? 'currentColor' : 'none'" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z"/></svg>
                        </button>
                    @endauth
                    <button type="button" class="rounded-full p-2 hover:text-lapis" @click="sharePost('{{ $url }}', '')" aria-label="Ulashish">
                        <x-ico name="share" />
                    </button>
                </div>
            </footer>
        @endif
    </div>
</article>
