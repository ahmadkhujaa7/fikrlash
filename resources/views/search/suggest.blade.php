{{-- Sarlavhadagi qidiruv maydoni ostidagi tezkor takliflar (real vaqtda). --}}
@php use Illuminate\Support\Str; @endphp
@if (mb_strlen($q) >= \App\Http\Controllers\SearchController::MIN_LENGTH)
    <div class="max-h-[min(70vh,32rem)] overflow-y-auto py-2">
        @if ($users->isEmpty() && $tags->isEmpty() && $posts->isEmpty())
            <p class="px-4 py-6 text-center text-sm text-muted">“{{ $q }}” bo‘yicha hech narsa topilmadi</p>
        @endif

        @if ($users->isNotEmpty())
            <p class="px-4 pb-1 pt-2 text-[12px] font-medium text-muted">Odamlar</p>
            @foreach ($users as $person)
                <a href="{{ $person->profileUrl() }}" data-suggest class="flex items-center gap-3 px-4 py-2 hover:bg-sunken focus:bg-sunken focus:outline-none">
                    <x-avatar :user="$person" size="xs" />
                    <span class="min-w-0 flex-1 truncate text-sm"><span class="font-medium text-ink">{{ $person->name }}</span><x-verified :user="$person" size="xs" class="ml-1" /> <span class="text-muted">{{ '@'.$person->username }}</span></span>
                </a>
            @endforeach
        @endif

        @if ($tags->isNotEmpty())
            <p class="px-4 pb-1 pt-3 text-[12px] font-medium text-muted">Teglar</p>
            @foreach ($tags as $tag)
                <a href="{{ route('tags.show', $tag->slug) }}" data-suggest class="flex items-center justify-between gap-3 px-4 py-2 text-sm hover:bg-sunken focus:bg-sunken focus:outline-none">
                    <span class="font-serif text-[1rem] text-ink">#{{ $tag->name }}</span>
                    <span class="text-[12px] text-muted">{{ $tag->posts_count }} ta fikr</span>
                </a>
            @endforeach
        @endif

        @if ($posts->isNotEmpty())
            <p class="px-4 pb-1 pt-3 text-[12px] font-medium text-muted">Fikrlar</p>
            @foreach ($posts as $post)
                <a href="{{ route('posts.show', $post) }}" data-suggest class="block px-4 py-2 hover:bg-sunken focus:bg-sunken focus:outline-none">
                    <span class="line-clamp-2 font-serif text-[0.975rem] leading-snug text-ink">{{ Str::limit(preg_replace('/\s+/u', ' ', $post->content), 140) }}</span>
                    <span class="mt-0.5 flex items-center gap-1 text-[12px] text-muted">{{ $post->user->name }}<x-verified :user="$post->user" size="xs" /></span>
                </a>
            @endforeach
        @endif
    </div>
    <a href="{{ route('search', ['q' => $q]) }}" data-suggest class="flex items-center gap-2 border-t border-line px-4 py-3 text-sm font-medium text-lapis hover:bg-sunken focus:bg-sunken focus:outline-none">
        <x-ico name="search" size="size-4" /> “{{ Str::limit($q, 40) }}” bo‘yicha barcha natijalar
    </a>
@endif
