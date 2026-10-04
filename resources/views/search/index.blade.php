@extends('layouts.app')
@section('title', $q ? '“'.$q.'” — qidiruv' : 'Qidiruv')

@section('content')
    <div class="sticky top-16 z-20 border-b border-line bg-paper/95 backdrop-blur-md sm:rounded-t-[27px]">
        <form action="{{ route('search') }}" method="GET" role="search" class="px-4 pt-8 sm:px-6">
            <label class="relative block">
                <span class="sr-only">Qidiruv</span>
                <x-ico name="search" size="size-6" class="pointer-events-none absolute left-0 top-1/2 -translate-y-1/2 text-muted" />
                <input type="search" name="q" value="{{ $q }}" autofocus placeholder="Fikr, odam yoki #teg"
                       class="block w-full border-0 bg-transparent py-3 pl-10 pr-0 font-serif text-[1.75rem] tracking-[-0.01em] text-ink placeholder:text-muted/70 focus:outline-none focus:ring-0">
                <input type="hidden" name="type" value="{{ $type }}">
            </label>
        </form>
        @if (mb_strlen($q) >= 2)
            <x-tabs class="!border-b-0" :items="collect(['all' => 'Hammasi', 'posts' => 'Fikrlar', 'users' => 'Odamlar', 'tags' => 'Teglar'])
                ->map(fn ($label, $key) => ['label' => $label, 'href' => route('search', ['q' => $q, 'type' => $key]), 'active' => $type === $key])->values()->all()" />
        @endif
    </div>

    @if (mb_strlen($q) < 2)
        <x-empty-state icon="search" title="Nimani qidiramiz?" text="Fikr matni, ism, @username yoki #teg bo‘yicha qidiring." />
    @else
        @if ($users->isNotEmpty())
            <section class="border-b border-line">
                @if ($type === 'all')<h2 class="px-4 sm:px-6 pt-4 text-sm font-semibold text-muted">Odamlar</h2>@endif
                <ul class="stream">
                    @foreach ($users as $person)
                        <li>@include('partials.user-row', ['person' => $person])</li>
                    @endforeach
                </ul>
                @if ($type === 'all' && $users->count() >= 5)
                    <a href="{{ route('search', ['q' => $q, 'type' => 'users']) }}" class="block px-4 sm:px-6 py-3 text-sm text-lapis hover:underline">Barcha odamlar</a>
                @endif
            </section>
        @endif

        @if ($tags->isNotEmpty() || $categories->isNotEmpty())
            <section class="flex flex-wrap gap-2 border-b border-line px-4 sm:px-6 py-4">
                @foreach ($categories as $category)
                    <a href="{{ route('categories.show', $category) }}" class="chip !bg-firuza-soft !text-firuza">{{ $category->name }}</a>
                @endforeach
                @foreach ($tags as $tag)
                    <a href="{{ route('tags.show', $tag->slug) }}" class="chip">#{{ $tag->name }} <span class="text-muted">{{ $tag->posts_count }}</span></a>
                @endforeach
            </section>
        @endif

        @if ($posts)
            @include('partials.feed', ['emptyTitle' => '“'.$q.'” bo‘yicha fikr topilmadi', 'emptyText' => 'Boshqa so‘z bilan urinib ko‘ring yoki apostrofsiz yozing.'])
        @elseif ($users->isEmpty() && $tags->isEmpty() && $categories->isEmpty())
            <x-empty-state icon="search" title="Hech narsa topilmadi" />
        @endif
    @endif
@endsection
