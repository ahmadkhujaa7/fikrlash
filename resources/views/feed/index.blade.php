@extends('layouts.app')

@section('content')
    <h1 class="sr-only">Lenta</h1>

    @auth
        @include('partials.composer', ['compact' => true])
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

    <div class="sticky top-16 z-20 flex items-center justify-between gap-3 border-b border-line bg-paper/90 px-4 py-3 backdrop-blur-md sm:px-6">
        <nav class="seg" aria-label="Lenta turi">
            <a href="{{ route('home') }}" @if ($tab === 'for-you') aria-current="page" @endif>Siz uchun</a>
            <a href="{{ route('home', ['tab' => 'latest']) }}" @if ($tab === 'latest') aria-current="page" @endif>Eng yangi</a>
            @auth
                <a href="{{ route('home', ['tab' => 'following']) }}" @if ($tab === 'following') aria-current="page" @endif>Obunalar</a>
            @endauth
        </nav>
    </div>

    @include('partials.feed', [
        'emptyTitle' => $tab === 'following' ? 'Obunalaringizda hali post yo‘q' : 'Hali hech kim yozmadi',
        'emptyText' => $tab === 'following' ? 'Qiziqarli odamlarga obuna bo‘ling — ularning fikrlari shu yerda chiqadi.' : 'Birinchi fikrni siz yozing.',
    ])
@endsection
