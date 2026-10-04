@extends('layouts.app')
@section('title', $q ? '“'.$q.'” — qidiruv' : 'Qidiruv')

@section('content')
    {{-- Real vaqtdagi qidiruv: yozish to‘xtagach (250 ms) natijalar orqa fonda yangilanadi, sahifa qayta yuklanmaydi. --}}
    <div x-data="liveSearch({ url: '{{ route('search.live') }}', q: @js($q), type: @js($type) })">
        <div class="sticky top-16 z-20 border-b border-line bg-paper/95 backdrop-blur-md sm:rounded-t-[27px]">
            <form action="{{ route('search') }}" method="GET" role="search" class="px-4 py-5 sm:px-6 sm:pt-7" @submit.prevent="run(true)">
                <label class="relative block">
                    <span class="sr-only">Qidiruv</span>
                    <span class="pointer-events-none absolute left-0 top-1/2 -translate-y-1/2 text-muted">
                        <x-ico name="search" size="size-6" x-show="!loading" />
                        <x-spinner class="!size-6" x-show="loading" x-cloak />
                    </span>
                    <input type="search" name="q" x-model="q" x-ref="input" value="{{ $q }}" autofocus autocomplete="off" enterkeyhint="search"
                           @input.debounce.250ms="run()" @keydown.escape="clear()"
                           placeholder="Fikr, odam yoki #teg"
                           class="block w-full border-0 bg-transparent py-2 pl-10 pr-10 font-serif text-[1.6rem] tracking-[-0.01em] text-ink placeholder:text-muted/70 focus:outline-none focus:ring-0 sm:text-[1.75rem]">
                    <button type="button" x-show="q.length" x-cloak @click="clear()"
                            class="absolute right-0 top-1/2 grid size-9 -translate-y-1/2 place-items-center rounded-full text-muted hover:bg-sunken hover:text-ink" aria-label="Tozalash">
                        <x-ico name="x" size="size-5" />
                    </button>
                    <input type="hidden" name="type" :value="type" value="{{ $type }}">
                </label>
            </form>
        </div>

        <div x-ref="results" aria-live="polite" :aria-busy="loading" class="transition-opacity duration-150" :class="{ 'opacity-60': loading }">
            @include('search.results')
        </div>
    </div>
@endsection
