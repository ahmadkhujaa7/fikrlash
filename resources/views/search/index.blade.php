@extends('layouts.app')
@section('title', $q ? '“'.$q.'” — qidiruv' : 'Qidiruv')

@section('content')
    <div class="sticky top-[57px] z-20 border-b border-line bg-surface/95 backdrop-blur lg:top-0">
        <form action="{{ route('search') }}" method="GET" role="search" class="px-4 pt-3 sm:px-5">
            <label class="relative block">
                <span class="sr-only">Qidiruv</span>
                <x-ico name="search" class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-muted" />
                <input type="search" name="q" value="{{ $q }}" autofocus placeholder="Fikr, odam yoki #teg qidiring"
                       class="field rounded-full !border-transparent !bg-sunken !pl-11 focus:!bg-surface">
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
                @if ($type === 'all')<h2 class="px-5 pt-4 text-sm font-semibold text-muted">Odamlar</h2>@endif
                <ul class="stream">
                    @foreach ($users as $person)
                        <li>@include('partials.user-row', ['person' => $person])</li>
                    @endforeach
                </ul>
                @if ($type === 'all' && $users->count() >= 5)
                    <a href="{{ route('search', ['q' => $q, 'type' => 'users']) }}" class="block px-5 py-3 text-sm text-lapis hover:underline">Barcha odamlar</a>
                @endif
            </section>
        @endif

        @if ($tags->isNotEmpty() || $categories->isNotEmpty())
            <section class="flex flex-wrap gap-2 border-b border-line px-5 py-4">
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
