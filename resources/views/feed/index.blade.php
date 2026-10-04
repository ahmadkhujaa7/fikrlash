@extends('layouts.app')

@section('content')
    <h1 class="sr-only">Lenta</h1>

    @auth
        @include('partials.composer', ['compact' => true])
        {{-- Yangi foydalanuvchiga (yon panel ko‘rinmaydigan ekranlarda): lenta qanday o‘rganishini tushuntirish --}}
        @if (app(\App\Services\Feed\TasteService::class)->profile(auth()->id())['empty'])
            <div class="flex gap-3 border-b border-line bg-lapis-soft/50 px-4 py-3.5 text-[13px] leading-relaxed text-ink-soft sm:px-6 lg:hidden" x-data="{ open: true }" x-show="open">
                <x-ico name="sparkles" size="size-4" class="mt-0.5 text-lapis" />
                <p class="flex-1">Lenta siz o‘qigan, yoqtirgan va saqlagan postlardan o‘rganadi. Qiziq bo‘lmasa — post menyusida <span class="font-medium text-ink">“Qiziq emas”</span>.</p>
                <button type="button" class="-m-1 grid size-7 shrink-0 place-items-center rounded-full text-muted hover:text-ink" @click="open = false" aria-label="Yopish"><x-ico name="x" size="size-4" /></button>
            </div>
        @endif
    @else
        {{-- Mehmon uchun: lojuvard koshin paneli — platformaning yuzi --}}
        <section class="girih m-2 rounded-[22px] bg-lapis-deep px-6 pb-9 pt-16 text-white sm:px-9 sm:pb-10 sm:pt-24" style="--girih-opacity:.13">
            <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(120%_90%_at_0%_100%,rgb(9_18_64/0.9),transparent_60%)]"></div>
            <p class="max-w-[26rem] font-serif text-[2.6rem] font-medium leading-[1.04] tracking-[-0.025em] sm:text-[3.25rem]">Har bir katta ish kichik fikrdan boshlanadi.</p>
            <p class="mt-5 max-w-[24rem] text-[16px] leading-relaxed text-white/75">O‘zbek tilida fikr, g‘oya va savollaringizni yozing. Boshqalarnikini o‘qing, muhokama qiling.</p>
            <div class="mt-8 flex flex-wrap gap-2">
                <a href="{{ route('register') }}" class="btn btn-lg bg-white text-lapis-deep hover:bg-white/90">Yozishni boshlash</a>
                <a href="{{ route('login') }}" class="btn btn-lg border border-white/30 text-white hover:border-white/70">Kirish</a>
            </div>
        </section>
    @endauth

    @include('partials.feed', ['emptyTitle' => 'Hali hech kim yozmadi', 'emptyText' => 'Birinchi fikrni siz yozing.'])
@endsection
