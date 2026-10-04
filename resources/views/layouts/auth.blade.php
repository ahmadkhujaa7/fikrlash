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
<html lang="uz">
<head>
    @include('layouts.head')
    <meta name="robots" content="noindex">
</head>
<body @if (session('toast')) data-toast="{{ session('toast') }}" @endif>
<div class="grid min-h-screen lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
    <main id="main" class="flex flex-col px-5 py-8 sm:px-10 lg:px-16">
        <a href="{{ route('home') }}" class="self-start" aria-label="Bosh sahifa"><x-logo /></a>
        <div class="my-auto w-full max-w-[400px] py-14">
            @yield('content')
        </div>
        <footer class="flex flex-wrap gap-x-5 gap-y-1 text-[13px] text-muted">
            <a href="{{ route('about') }}" class="hover:text-ink">Loyiha haqida</a>
            <a href="{{ route('terms') }}" class="hover:text-ink">Shartlar</a>
            <a href="{{ route('privacy') }}" class="hover:text-ink">Maxfiylik</a>
        </footer>
    </main>

    <aside class="relative hidden overflow-hidden border-l border-line bg-sunken lg:flex lg:flex-col lg:justify-end lg:p-16" aria-hidden="true">
        <blockquote class="relative max-w-lg">
            <span class="mb-4 block select-none font-serif text-[7rem] leading-[0.5] text-lapis">“</span>
            <p class="font-serif text-[2.6rem] font-medium leading-[1.12] tracking-[-0.02em] text-ink">{{ $quote }}</p>
            <p class="mt-6 font-serif text-xl italic text-ink-soft">{{ $tail }}</p>
        </blockquote>
    </aside>
</div>
<x-toasts />
</body>
</html>
