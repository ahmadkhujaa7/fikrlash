@php
    // O‘ng panelda platformaning ruhini ko‘rsatuvchi namunaviy fikr (har kuni boshqasi).
    $thoughts = [
        ['Kitob o‘qish odati haqida: kuniga 10 bet ham yiliga 12 ta kitob degani.', 'Kichik qadamlar katta natija beradi.'],
        ['Eng yaxshi g‘oyalar ko‘pincha sayr qilayotganda keladi.', 'Telefonni uyda qoldirib, 30 daqiqa piyoda yurib ko‘ring.'],
        ['Ta’limdagi eng katta muammo — savol berishdan qo‘rqish.', 'Bolalarga “bilmayman” deyishni o‘rgatish kerak.'],
    ];
    [$quote, $tail] = $thoughts[now()->dayOfYear % count($thoughts)];
@endphp
<!DOCTYPE html>
<html lang="uz" @class(['in-app' => \App\Support\AppClient::isApp()])>
<head>
    @include('layouts.head')
    <meta name="robots" content="noindex">
</head>
<body @if (session('toast')) data-toast="{{ session('toast') }}" @endif>
<div class="grid min-h-screen bg-paper lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
    <main id="main" class="flex min-h-[100dvh] flex-col px-5 pb-[calc(2rem+env(safe-area-inset-bottom))] pt-[calc(1.5rem+env(safe-area-inset-top))] sm:px-10 lg:px-16">
        <a href="{{ route('home') }}" class="self-start" aria-label="Bosh sahifa"><x-logo /></a>

        {{-- Mobil va planshet: kunning fikri — ixcham lojuvard panel (forma ko‘p joy talab qiladigan sahifalarda yo‘q) --}}
        @unless (View::hasSection('focus'))
            <figure class="girih -mx-2 mt-6 rounded-[22px] bg-lapis-deep px-5 pb-5 pt-9 text-white lg:hidden" style="--girih-opacity:.12" aria-hidden="true">
                <p class="font-serif text-[1.3rem] font-medium leading-[1.22] tracking-[-0.01em]">{{ $quote }}</p>
                <figcaption class="mt-3 text-[12px] text-white/60">Kunning fikri</figcaption>
            </figure>
        @endunless

        <div class="my-auto w-full max-w-[400px] {{ View::hasSection('focus') ? 'py-8' : 'py-10' }} lg:py-14">
            @yield('content')
        </div>
        <footer class="flex flex-wrap gap-x-5 gap-y-1 text-[13px] text-muted">
            <a href="{{ route('about') }}" class="hover:text-ink">Loyiha haqida</a>
            <a href="{{ route('terms') }}" class="hover:text-ink">Shartlar</a>
            <a href="{{ route('privacy') }}" class="hover:text-ink">Maxfiylik</a>
        </footer>
    </main>

    {{-- O‘ng panel: lojuvard koshin + kunning fikri --}}
    <aside class="girih m-3 hidden rounded-[28px] bg-lapis-deep text-white lg:flex lg:flex-col lg:justify-end lg:p-14" style="--girih-opacity:.12; --girih-size:150px" aria-hidden="true">
        <div class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(110%_80%_at_0%_100%,rgb(8_16_58/0.95),transparent_65%)]"></div>
        <figure class="max-w-lg">
            <span class="mb-5 block select-none font-serif text-[6.5rem] leading-[0.5] text-white/40">“</span>
            <p class="font-serif text-[2.6rem] font-medium leading-[1.12] tracking-[-0.02em]">{{ $quote }}</p>
            <p class="mt-6 font-serif text-xl italic text-white/70">{{ $tail }}</p>
            <figcaption class="mt-10 flex items-center gap-3 text-[13px] text-white/60">
                <span class="h-px w-8 bg-white/30"></span> Kunning fikri, {{ \App\Support\Branding::name() }}
            </figcaption>
        </figure>
    </aside>
</div>
<x-toasts />
</body>
</html>
