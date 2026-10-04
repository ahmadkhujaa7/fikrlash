{{--
  Post kartochkasi. $detail = true — post sahifasidagi to‘liq ko‘rinish.
  is_liked / is_saved — FeedService::withViewerState() orqali oldindan yuklangan (N+1 yo‘q).
--}}
@php
    use App\Enums\PostStatus;
    use App\Enums\PostVisibility;
    use App\Support\ContentFormatter;
    use App\Support\Time;
    use Illuminate\Support\Number;

    $detail = $detail ?? false;
    $isArticle = $post->isArticle();
    $me = auth()->user();
    $author = $post->user;
    $url = route('posts.show', $post);
    $time = $post->published_at ?? $post->created_at;
    $heart = 'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z';
    $canDismiss = ! $detail && $me && ! $post->isOwnedBy($me) && $post->isPublished();
    $bookmark = 'M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z';
@endphp
<article id="post-{{ $post->id }}" data-item @unless ($detail) tabindex="-1" @endunless
         @if ($post->isPublished()) data-track-view data-post-id="{{ $post->id }}" @endif
         @if ($canDismiss) x-data="dismissable('{{ route('api.v1.posts.not-interested', $post) }}')" @endif
         class="px-4 sm:px-6 {{ $detail ? 'pt-8 pb-6' : 'py-5 sm:py-6' }}">
    @if ($canDismiss)
        <div x-show="dismissed" x-cloak class="flex items-center gap-3 rounded-2xl bg-sunken px-4 py-3.5 text-[13px] text-ink-soft">
            <x-ico name="eye-slash" size="size-4" class="text-muted" />
            <span>Yashirildi. Bunday postlarni kamroq ko‘rasiz.</span>
        </div>
    @endif
    <div @if ($canDismiss) x-show="!dismissed" @endif>

    @if ($detail && $isArticle)
        <p class="article-kicker">Maqola · {{ $post->readMinutes() }} daqiqalik o‘qish</p>
        <h1 class="article-title mt-3">{{ $post->title }}</h1>
    @endif

    <header class="flex items-center gap-3 {{ $detail && $isArticle ? 'mt-6' : '' }}">
        <a href="{{ $author->profileUrl() }}" class="shrink-0" tabindex="-1" aria-hidden="true">
            <x-avatar :user="$author" :size="$detail ? 'md' : 'sm'" />
        </a>
        <div class="flex min-w-0 flex-1 {{ $detail ? 'flex-col' : 'flex-col sm:flex-row sm:items-baseline sm:gap-2' }}">
            <a href="{{ $author->profileUrl() }}" class="flex min-w-0 items-center gap-1 text-sm font-medium text-ink hover:underline"><span class="truncate">{{ $author->name }}</span><x-verified :user="$author" /></a>
            @if ($detail)
                <span class="meta">{{ '@'.$author->username }}</span>
            @else
                <a href="{{ $url }}" data-post-link class="meta shrink-0 hover:text-ink">
                    <time datetime="{{ $time->toIso8601String() }}" title="{{ Time::full($time) }}">{{ Time::short($time) }}</time>
                </a>
                @if ($post->isLong() && ! $isArticle)
                    {{-- Uzun post: taxminiy o‘qish vaqti --}}
                    <span class="meta hidden shrink-0 sm:inline">{{ $post->readMinutes() }} daqiqalik o‘qish</span>
                @endif
            @endif
        </div>

        @if ($post->visibility === PostVisibility::Followers)
            <span class="text-muted" title="Faqat obunachilar ko‘radi"><x-ico name="lock" size="size-4" /></span>
        @endif

        <x-dropdown label="Post amallari">
            <x-slot:trigger class="-mr-2 !p-1.5"><x-ico name="dots" size="size-5" /></x-slot:trigger>
            <x-dropdown-item icon="link" @click="sharePost('{{ $url }}', ''); open = false">Havolani ulashish</x-dropdown-item>
            @if ($me && $post->isOwnedBy($me))
                <x-dropdown-item icon="pencil" :href="route('posts.edit', $post)">Tahrirlash</x-dropdown-item>
                <form method="POST" action="{{ route('posts.destroy', $post) }}" x-data @submit="confirm('Postni o‘chirasizmi?') || $event.preventDefault()">
                    @csrf @method('DELETE')
                    <x-dropdown-item icon="trash" type="submit" danger>O‘chirish</x-dropdown-item>
                </form>
            @elseif ($me)
                @if ($canDismiss)
                    <x-dropdown-item icon="eye-slash" @click="open = false; dismiss()">Qiziq emas</x-dropdown-item>
                @endif
                <x-dropdown-item icon="flag" @click="$dispatch('report', { type: 'post', id: {{ $post->id }} }); open = false" danger>Shikoyat qilish</x-dropdown-item>
            @endif
        </x-dropdown>
    </header>

    @if ($post->status !== PostStatus::Published)
        <div class="mt-4">
            @switch($post->status)
                @case(PostStatus::Draft) <x-badge tone="neutral">Qoralama</x-badge> @break
                @case(PostStatus::PendingModeration) <x-badge tone="amber">Moderator tekshiruvida — hozircha faqat sizga ko‘rinadi</x-badge> @break
                @case(PostStatus::Hidden) <x-badge tone="anor">Yashirilgan</x-badge> @break
            @endswitch
        </div>
    @endif

    @if ($isArticle && $detail)
        <div class="mt-8">@include('partials.article-body')</div>
    @elseif ($isArticle)
        {{-- Maqola lentada: muqova, sarlavha, qisqa mazmun --}}
        <a href="{{ $url }}" data-post-link class="group mt-3.5 block">
            @if ($post->image_path)
                <div class="mb-4 aspect-[16/9] overflow-hidden rounded-2xl bg-sunken">
                    <img src="{{ $post->imageUrl() }}" alt="" loading="lazy" decoding="async" class="size-full object-cover transition-transform duration-500 group-hover:scale-[1.02]">
                </div>
            @endif
            <h2 class="font-serif text-[1.45rem] font-semibold leading-[1.2] tracking-[-0.015em] text-ink transition-colors group-hover:text-lapis-deep sm:text-[1.6rem]">{{ $post->title }}</h2>
            @if ($summary = $post->summary(260))
                <p class="mt-2 line-clamp-3 font-serif text-[1.0625rem] leading-relaxed text-ink-soft">{{ $summary }}</p>
            @endif
            <p class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-lapis-soft px-2.5 py-1 text-[12px] font-medium text-lapis">
                <x-ico name="newspaper" size="size-3.5" /> Maqola · {{ $post->readMinutes() }} daqiqalik o‘qish
            </p>
        </a>
    @elseif ($detail)
        <div class="prose-post mt-6 !text-[1.375rem] !leading-[1.62]">{!! ContentFormatter::toHtml($post->content) !!}</div>
    @elseif ($post->isLong())
        <div class="prose-post mt-3" x-data="{ more: false }">
            <div class="clamp-post cursor-pointer" :class="{ 'clamp-post': !more }" data-post-open="{{ $url }}">{!! ContentFormatter::toHtml($post->content) !!}</div>
            <button type="button" x-show="!more" @click="more = true" class="mt-2 font-sans text-sm font-medium text-ink underline decoration-line-strong underline-offset-4 hover:decoration-ink">Davomini o‘qish</button>
        </div>
    @else
        <div class="prose-post mt-3 cursor-pointer" data-post-open="{{ $url }}">{!! ContentFormatter::toHtml($post->content) !!}</div>
    @endif

    @if ($post->image_path && ! $isArticle)
        <a href="{{ $detail ? $post->imageUrl() : $url }}" class="mt-5 block overflow-hidden rounded-2xl bg-sunken" @if ($detail) target="_blank" rel="noopener" @else data-post-link @endif>
            <img src="{{ $post->imageUrl() }}" alt="Post rasmi" loading="lazy" class="{{ $detail ? 'w-full' : 'max-h-[28rem] w-full object-cover' }}">
        </a>
    @endif

    @if ($detail)
        <div class="mt-6 flex flex-wrap items-center gap-x-4 gap-y-2">
            <time class="meta" datetime="{{ $time->toIso8601String() }}">{{ Time::full($time) }}@if ($post->edited_at)<span>, tahrirlangan</span>@endif</time>
            @if ($post->relationLoaded('tags'))
                @foreach ($post->tags as $tag)
                    <a href="{{ route('tags.show', $tag->slug) }}" class="meta hover:text-lapis">#{{ $tag->name }}</a>
                @endforeach
            @endif
        </div>
    @endif

    @if ($post->isPublished())
        <footer class="{{ $detail ? 'mt-6 border-y border-line py-2' : 'mt-4 -ml-2.5 -mr-2' }} flex items-center gap-1 text-[13px] text-muted">
            @auth
                <button type="button"
                        @class(['group flex items-center gap-1.5 rounded-full px-2.5 py-1.5 tabular-nums transition-colors hover:bg-anor-soft hover:text-anor', 'text-anor' => $post->is_liked])
                        x-data="toggle({ active: {{ $post->is_liked ? 'true' : 'false' }}, count: {{ (int) $post->likes_count }}, url: '{{ route('api.v1.posts.like', $post) }}', onKey: 'liked', countKey: 'likes_count' })"
                        @click="flip" :class="{ 'text-anor': active }" aria-pressed="{{ $post->is_liked ? 'true' : 'false' }}" :aria-pressed="active" aria-label="Yoqtirish">
                    <svg class="size-[18px]" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" fill="{{ $post->is_liked ? 'currentColor' : 'none' }}" :fill="active ? 'currentColor' : 'none'" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $heart }}"/></svg>
                    <span x-text="count || ''">{{ $post->likes_count ?: '' }}</span>
                </button>
            @else
                <a href="{{ route('login') }}" class="flex items-center gap-1.5 rounded-full px-2 py-1.5 tabular-nums hover:text-anor" aria-label="Yoqtirish uchun kiring">
                    <x-ico name="heart" size="size-[18px]" />{{ $post->likes_count ?: '' }}
                </a>
            @endauth

            <a href="{{ $url }}#comments" @unless ($detail) data-post-link @endunless @if ($detail) @click.prevent="focusComment({{ $post->id }})" @endif class="flex items-center gap-1.5 rounded-full px-2.5 py-1.5 tabular-nums transition-colors hover:bg-sunken hover:text-ink" aria-label="Izohlar">
                <x-ico name="chat" size="size-[18px]" />
                <span x-data="{ n: {{ (int) $post->comments_count }} }" x-text="n || ''"
                      @post-comments.window="$event.detail.url === '{{ route('posts.comments', $post) }}' && (n = $event.detail.count)">{{ $post->comments_count ?: '' }}</span>
            </a>

            <span class="flex items-center gap-1.5 px-2.5 py-1.5 tabular-nums" title="Ko‘rishlar">
                <x-ico name="eye" size="size-[18px]" />{{ $post->views_count ? Number::abbreviate($post->views_count) : '' }}
            </span>

            <div class="ml-auto flex items-center">
                @auth
                    <button type="button"
                            @class(['rounded-full p-2 transition-colors hover:bg-sunken hover:text-ink', 'text-lapis' => $post->is_saved])
                            x-data="toggle({ active: {{ $post->is_saved ? 'true' : 'false' }}, count: 0, url: '{{ route('api.v1.posts.save', $post) }}', onKey: 'saved' })"
                            @click="flip().then(() => toast(active ? 'Saqlanganlarga qo‘shildi.' : 'Saqlanganlardan olib tashlandi.'))"
                            :class="{ 'text-lapis': active }" aria-pressed="{{ $post->is_saved ? 'true' : 'false' }}" :aria-pressed="active" aria-label="Saqlash">
                        <svg class="size-[18px]" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" fill="{{ $post->is_saved ? 'currentColor' : 'none' }}" :fill="active ? 'currentColor' : 'none'" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $bookmark }}"/></svg>
                    </button>
                @endauth
                <button type="button" class="rounded-full p-2 transition-colors hover:bg-sunken hover:text-ink" @click="sharePost('{{ $url }}', '')" aria-label="Ulashish">
                    <x-ico name="share" size="size-[18px]" />
                </button>
            </div>
        </footer>
    @endif
    </div>
</article>
