@php
    $sidebar = app(\App\Services\Feed\SidebarService::class);
    $trendingTags ??= $sidebar->trendingTags();
    $suggestedUsers ??= $sidebar->suggestedUsers(auth()->user());
    $categories ??= \App\Models\Category::cachedActive();
@endphp
<div class="space-y-5">
    @unless (request()->routeIs('search'))
        <form action="{{ route('search') }}" method="GET" role="search">
            <label class="relative block">
                <span class="sr-only">Qidiruv</span>
                <x-ico name="search" class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-muted" />
                <input type="search" name="q" placeholder="Fikr, odam yoki #teg" class="field rounded-full !bg-sunken !pl-11 !border-transparent focus:!bg-surface">
            </label>
        </form>
    @endunless

    @if ($trendingTags->isNotEmpty())
        <section class="panel p-5">
            <h2 class="mb-3 font-serif text-lg font-semibold">Hafta mavzulari</h2>
            <ul class="space-y-1">
                @foreach ($trendingTags as $tag)
                    <li>
                        <a href="{{ route('tags.show', $tag->slug) }}" class="-mx-2 flex items-baseline justify-between rounded-lg px-2 py-1.5 hover:bg-sunken">
                            <span class="font-medium text-ink">#{{ $tag->name }}</span>
                            <span class="text-xs text-muted">{{ $tag->recent_posts }} ta fikr</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($suggestedUsers->isNotEmpty())
        <section class="panel p-5">
            <h2 class="mb-3 font-serif text-lg font-semibold">Kimni o‘qish mumkin</h2>
            <ul class="space-y-3">
                @foreach ($suggestedUsers as $person)
                    <li class="flex items-center gap-3">
                        <a href="{{ $person->profileUrl() }}"><x-avatar :user="$person" size="sm" /></a>
                        <a href="{{ $person->profileUrl() }}" class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold">{{ $person->name }}</span>
                            <span class="block truncate text-xs text-muted">{{ $person->followers_count }} obunachi</span>
                        </a>
                        @auth
                            @include('partials.follow-button', ['target' => $person, 'following' => false, 'small' => true])
                        @endauth
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="px-1">
        <h2 class="mb-3 px-1 text-sm font-semibold text-muted">Mavzular</h2>
        <div class="flex flex-wrap gap-2">
            @foreach ($categories as $category)
                <a href="{{ route('categories.show', $category) }}" class="chip">{{ $category->name }}</a>
            @endforeach
        </div>
    </section>

    <footer class="flex flex-wrap gap-x-4 gap-y-1 px-2 text-xs text-muted">
        <a href="{{ route('about') }}" class="hover:text-ink">Loyiha haqida</a>
        <a href="{{ route('terms') }}" class="hover:text-ink">Foydalanish shartlari</a>
        <a href="{{ route('privacy') }}" class="hover:text-ink">Maxfiylik</a>
        <span>© {{ now()->year }} Fikrlash.uz</span>
    </footer>
</div>
