@php
    $sidebar = app(\App\Services\Feed\SidebarService::class);
    $trendingTags ??= $sidebar->trendingTags();
    $suggestedUsers ??= $sidebar->suggestedUsers(auth()->user());
    $categories ??= \App\Models\Category::cachedActive();
@endphp
<div class="space-y-10">
    @if ($trendingTags->isNotEmpty())
        <section>
            <h2 class="rail-title">Shu hafta muhokama qilinmoqda</h2>
            <ol class="space-y-3">
                @foreach ($trendingTags as $tag)
                    <li>
                        <a href="{{ route('tags.show', $tag->slug) }}" class="group flex items-baseline justify-between gap-3">
                            <span class="font-serif text-[1.05rem] text-ink group-hover:text-lapis">#{{ $tag->name }}</span>
                            <span class="meta tabular-nums">{{ $tag->recent_posts }}</span>
                        </a>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    @if ($suggestedUsers->isNotEmpty())
        <section>
            <h2 class="rail-title">Kimni o‘qish mumkin</h2>
            <ul class="space-y-4">
                @foreach ($suggestedUsers as $person)
                    <li class="flex items-center gap-3">
                        <a href="{{ $person->profileUrl() }}" tabindex="-1" aria-hidden="true"><x-avatar :user="$person" size="sm" /></a>
                        <a href="{{ $person->profileUrl() }}" class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium hover:underline">{{ $person->name }}</span>
                            <span class="block truncate text-[13px] text-muted">{{ $person->followers_count }} obunachi</span>
                        </a>
                        @auth
                            @include('partials.follow-button', ['target' => $person, 'following' => false, 'small' => true, 'quiet' => true])
                        @endauth
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section>
        <h2 class="rail-title">Mavzular</h2>
        <div class="flex flex-wrap gap-2">
            @foreach ($categories as $category)
                <a href="{{ route('categories.show', $category) }}" class="chip">{{ $category->name }}</a>
            @endforeach
        </div>
    </section>

    <footer class="flex flex-wrap gap-x-4 gap-y-1.5 text-[13px] text-muted">
        <a href="{{ route('about') }}" class="hover:text-ink">Loyiha haqida</a>
        <a href="{{ route('terms') }}" class="hover:text-ink">Shartlar</a>
        <a href="{{ route('privacy') }}" class="hover:text-ink">Maxfiylik</a>
        <a href="{{ route('docs.api') }}" class="hover:text-ink">API</a>
        <span>© {{ now()->year }} Fikrlash.uz</span>
    </footer>
</div>
