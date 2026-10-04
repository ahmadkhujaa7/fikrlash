@extends('layouts.app')

@php
    $question = \App\Models\Setting::read('daily_question');
    $me = auth()->user();
    if ($me) {
        $hour = now()->hour;
        $greeting = match (true) {
            $hour >= 5 && $hour < 11 => 'Xayrli tong',
            $hour >= 11 && $hour < 17 => 'Xayrli kun',
            $hour >= 17 && $hour < 23 => 'Xayrli kech',
            default => 'Xayrli tun',
        };
        $firstName = \Illuminate\Support\Str::of($me->name)->trim()->before(' ');
        // O‘zbek imlosida oy nomlari kichik harf bilan: "Dushanba, 5-oktabr".
        $today = \Illuminate\Support\Str::ucfirst(mb_strtolower(now()->translatedFormat('l')).', '.now()->day.'-'.mb_strtolower(now()->translatedFormat('F')));
    } else {
        // Mehmonga platforma "tirik" ekanini ko‘rsatadigan raqamlar (10 daqiqa keshlanadi).
        $stats = \Illuminate\Support\Facades\Cache::remember('home:stats', now()->addMinutes(10), fn () => [
            'posts' => \App\Models\Post::query()->where('status', 'published')->count(),
            'authors' => \App\Models\User::query()->visible()->count(),
        ]);
    }
@endphp

@section('content')
    @auth
        {{-- Salomlashish: kun vaqti va sana --}}
        <header class="px-4 pt-6 sm:px-6 sm:pt-7">
            <div class="min-w-0">
                <h1 class="truncate font-serif text-[1.55rem] font-medium leading-tight tracking-[-0.015em] text-ink sm:text-[1.75rem]">{{ $greeting }}, {{ $firstName }}</h1>
                <p class="mt-1 text-[13px] text-muted">{{ $today }}</p>
            </div>
        </header>

        @if ($question)
            {{-- Kun savoli (admin panelda belgilanadi) --}}
            <section class="mx-4 mt-5 flex flex-col gap-3 rounded-2xl border border-lapis/15 bg-lapis-soft/60 p-4 sm:mx-6 sm:flex-row sm:items-center sm:gap-5 sm:p-5">
                <div class="min-w-0 flex-1">
                    <p class="flex items-center gap-1.5 text-[12px] font-medium text-lapis"><x-ico name="sparkles" size="size-3.5" /> Kun savoli</p>
                    <p class="mt-1.5 font-serif text-[1.2rem] leading-snug text-ink">{{ $question }}</p>
                </div>
                <button type="button" class="btn btn-accent btn-sm shrink-0 self-start sm:self-auto" @click="$dispatch('compose-focus')">Fikr bildirish</button>
            </section>
        @endif

        @include('partials.composer', ['compact' => true, 'placeholder' => $question])

        {{-- Mobil va planshet: trend teglar (yon panel bu ekranlarda ko‘rinmaydi) --}}
        @if ($trendingTags->isNotEmpty())
            <nav class="flex gap-2 overflow-x-auto border-b border-line px-4 py-3 [scrollbar-width:none] sm:px-6 lg:hidden" aria-label="Trend teglar">
                @foreach ($trendingTags as $tag)
                    <a href="{{ route('tags.show', $tag->slug) }}" class="chip shrink-0">#{{ $tag->name }}</a>
                @endforeach
            </nav>
        @endif

        {{-- Yangi foydalanuvchiga (yon panel ko‘rinmaydigan ekranlarda): lenta qanday o‘rganishini tushuntirish --}}
        @if (app(\App\Services\Feed\TasteService::class)->profile($me->id)['empty'])
            <div class="flex gap-3 border-b border-line bg-lapis-soft/50 px-4 py-3.5 text-[13px] leading-relaxed text-ink-soft sm:px-6 lg:hidden" x-data="{ open: true }" x-show="open">
                <x-ico name="sparkles" size="size-4" class="mt-0.5 text-lapis" />
                <p class="flex-1">Lenta siz o‘qigan, yoqtirgan va saqlagan postlardan o‘rganadi. Qiziq bo‘lmasa — post menyusida <span class="font-medium text-ink">“Qiziq emas”</span>.</p>
                <button type="button" class="-m-1 grid size-7 shrink-0 place-items-center rounded-full text-muted hover:text-ink" @click="open = false" aria-label="Yopish"><x-ico name="x" size="size-4" /></button>
            </div>
        @endif
    @else
        <h1 class="sr-only">{{ \App\Support\Branding::name() }}</h1>
        {{-- Mehmon uchun: lojuvard koshin paneli — platformaning yuzi --}}
        <section class="girih m-2 rounded-[22px] bg-lapis-deep px-6 pb-8 pt-14 text-white sm:px-9 sm:pb-9 sm:pt-20" style="--girih-opacity:.13">
            <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(120%_90%_at_0%_100%,rgb(9_18_64/0.9),transparent_60%)]"></div>
            <p class="max-w-[26rem] font-serif text-[2.5rem] font-medium leading-[1.04] tracking-[-0.025em] sm:text-[3.25rem]">Har bir katta ish kichik fikrdan boshlanadi.</p>
            <p class="mt-5 max-w-[24rem] text-[16px] leading-relaxed text-white/75">O‘zbek tilida fikr, g‘oya va savollaringizni yozing. Boshqalarnikini o‘qing, muhokama qiling.</p>
            <div class="mt-8 flex flex-wrap gap-2">
                <a href="{{ route('register') }}" class="btn btn-lg bg-white text-lapis-deep hover:bg-white/90">Yozishni boshlash</a>
                <a href="{{ route('login') }}" class="btn btn-lg border border-white/30 text-white hover:border-white/70">Kirish</a>
            </div>
            @if ($stats['posts'] > 0)
                <dl class="mt-10 flex gap-8 border-t border-white/15 pt-5">
                    <div><dt class="text-[12px] text-white/60">Fikrlar</dt><dd class="mt-0.5 font-serif text-[1.6rem] font-medium tabular-nums">{{ \Illuminate\Support\Number::format($stats['posts']) }}</dd></div>
                    <div><dt class="text-[12px] text-white/60">Mualliflar</dt><dd class="mt-0.5 font-serif text-[1.6rem] font-medium tabular-nums">{{ \Illuminate\Support\Number::format($stats['authors']) }}</dd></div>
                </dl>
            @endif
        </section>

        @if ($trendingTags->isNotEmpty())
            <nav class="flex gap-2 overflow-x-auto border-b border-line px-4 py-3 [scrollbar-width:none] sm:px-6 lg:hidden" aria-label="Trend teglar">
                @foreach ($trendingTags as $tag)
                    <a href="{{ route('tags.show', $tag->slug) }}" class="chip shrink-0">#{{ $tag->name }}</a>
                @endforeach
            </nav>
        @endif
    @endauth

    @include('partials.feed', ['emptyTitle' => 'Hali hech kim yozmadi', 'emptyText' => 'Birinchi fikrni siz yozing.', 'endText' => 'Hozircha shu. Yangi fikrlar paydo bo‘lishi bilan shu yerda chiqadi.'])
@endsection
