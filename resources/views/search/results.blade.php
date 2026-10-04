{{-- Qidiruv natijalari — to‘liq sahifada ham, real vaqtda (search.live) ham shu bo‘lak ishlatiladi. --}}
@php $ready = mb_strlen($q) >= \App\Http\Controllers\SearchController::MIN_LENGTH; @endphp

@if (! $ready)
    <x-empty-state icon="search" title="Nimani qidiramiz?" text="Yozishni boshlang — natijalar darhol chiqadi. Fikr matni, ism, @username yoki #teg bo‘yicha." />
@else
    <nav class="flex gap-7 overflow-x-auto border-b border-line px-4 [scrollbar-width:none] sm:px-6" aria-label="Natija turi">
        @foreach (['all' => 'Hammasi', 'posts' => 'Fikrlar', 'users' => 'Odamlar', 'tags' => 'Teglar'] as $key => $label)
            <a href="{{ route('search', ['q' => $q, 'type' => $key]) }}" class="tab shrink-0" @if ($type === $key) aria-current="page" @endif
               @click.prevent="setType('{{ $key }}')">{{ $label }}</a>
        @endforeach
    </nav>

    @if ($users->isNotEmpty())
        <section class="border-b border-line">
            @if ($type === 'all')<h2 class="px-4 pt-4 text-[13px] font-medium text-muted sm:px-6">Odamlar</h2>@endif
            <ul class="stream">
                @foreach ($users as $person)
                    <li>@include('partials.user-row', ['person' => $person])</li>
                @endforeach
            </ul>
            @if ($type === 'all' && $users->count() >= 5)
                <a href="{{ route('search', ['q' => $q, 'type' => 'users']) }}" @click.prevent="setType('users')" class="block px-4 py-3 text-sm text-lapis hover:underline sm:px-6">Barcha odamlar</a>
            @endif
        </section>
    @endif

    @if ($tags->isNotEmpty())
        <section class="flex flex-wrap gap-2 border-b border-line px-4 py-4 sm:px-6">
            @foreach ($tags as $tag)
                <a href="{{ route('tags.show', $tag->slug) }}" class="chip">#{{ $tag->name }} <span class="text-muted">{{ $tag->posts_count }}</span></a>
            @endforeach
        </section>
    @endif

    @if ($posts)
        @include('partials.feed', ['emptyTitle' => '“'.$q.'” bo‘yicha fikr topilmadi', 'emptyText' => 'Boshqa so‘z bilan urinib ko‘ring yoki apostrofsiz yozing.'])
    @elseif ($users->isEmpty() && $tags->isEmpty())
        <x-empty-state icon="search" title="Hech narsa topilmadi" text="Boshqa so‘z bilan urinib ko‘ring." />
    @endif
@endif
