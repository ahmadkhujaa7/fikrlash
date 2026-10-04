@php
    $sidebar = app(\App\Services\Feed\SidebarService::class);
    $trendingTags ??= $sidebar->trendingTags();
    $suggestedUsers ??= $sidebar->suggestedUsers(auth()->user());
@endphp
<div class="space-y-9 px-1">
    @auth
        {{-- Lenta qanday ishlaydi: algoritm nimani o‘rgangani ochiq ko‘rsatiladi --}}
        <section class="rounded-2xl border border-line bg-paper p-5">
            <h2 class="flex items-center gap-2 text-[13px] font-medium text-ink">
                <span class="grid size-6 place-items-center rounded-full bg-lapis-soft text-lapis"><x-ico name="sparkles" size="size-3.5" /></span>
                Lentangiz sizga moslashadi
            </h2>
            <p class="mt-3 text-[13px] leading-relaxed text-muted">Nimani o‘qisangiz, yoqtirsangiz va saqlasangiz — tizim shundan o‘rganib, sizga mos fikrlarni ko‘rsatadi. Qiziq bo‘lmagan postni <span class="text-ink-soft">“Qiziq emas”</span> deb belgilang.</p>
        </section>
    @endauth

    @if ($trendingTags->isNotEmpty())
        <section>
            <h2 class="rail-title">Shu hafta muhokama qilinmoqda</h2>
            <ol class="space-y-3">
                @foreach ($trendingTags as $tag)
                    <li>
                        <a href="{{ route('tags.show', $tag->slug) }}" class="group flex items-baseline gap-3">
                            <span class="w-4 shrink-0 text-right text-[12px] tabular-nums text-muted/70">{{ $loop->iteration }}</span>
                            <span class="flex-1 truncate font-serif text-[1.075rem] text-ink group-hover:text-lapis">#{{ $tag->name }}</span>
                            <span class="meta tabular-nums">{{ $tag->recent_posts }} ta fikr</span>
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
                            <span class="flex items-center gap-1 text-sm font-medium hover:underline"><span class="truncate">{{ $person->name }}</span><x-verified :user="$person" size="xs" /></span>
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

    <footer class="flex flex-wrap gap-x-4 gap-y-1.5 text-[13px] text-muted">
        <a href="{{ route('about') }}" class="hover:text-ink">Loyiha haqida</a>
        <a href="{{ route('terms') }}" class="hover:text-ink">Shartlar</a>
        <a href="{{ route('privacy') }}" class="hover:text-ink">Maxfiylik</a>
        <a href="{{ route('docs.api') }}" class="hover:text-ink">API</a>
        <span>© {{ now()->year }} {{ \App\Support\Branding::name() }}</span>
    </footer>
</div>
