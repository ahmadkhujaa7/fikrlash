@extends('layouts.app')

@section('content')
    <div class="sticky top-[57px] z-20 border-b border-line bg-surface/90 backdrop-blur lg:top-0">
        <h1 class="sr-only">Lenta</h1>
        <x-tabs class="!border-b-0" :items="array_values(array_filter([
            ['label' => 'Siz uchun', 'href' => route('home'), 'active' => $tab === 'for-you'],
            ['label' => 'Eng yangi', 'href' => route('home', ['tab' => 'latest']), 'active' => $tab === 'latest'],
            auth()->check() ? ['label' => 'Obunalar', 'href' => route('home', ['tab' => 'following']), 'active' => $tab === 'following'] : null,
        ]))" />
    </div>

    @auth
        <div class="border-b border-line">
            @include('partials.composer', ['compact' => true])
        </div>
    @else
        <section class="border-b border-line px-5 py-8 sm:px-8">
            <p class="font-serif text-[1.75rem] font-semibold leading-tight text-ink">Har bir katta ish<br>kichik fikrdan boshlanadi.</p>
            <p class="mt-3 max-w-md text-ink-soft">Fikrlash.uz — o‘zbek tilida fikr, g‘oya va savollarni yozib, boshqalar bilan muhokama qiladigan joy.</p>
            <div class="mt-5 flex flex-wrap gap-2">
                <a href="{{ route('register') }}" class="btn btn-primary">Ro‘yxatdan o‘tish</a>
                <a href="{{ route('login') }}" class="btn btn-secondary">Kirish</a>
            </div>
        </section>
    @endauth

    @include('partials.feed', [
        'emptyTitle' => $tab === 'following' ? 'Obunalaringizda hali post yo‘q' : 'Hali hech kim yozmadi',
        'emptyText' => $tab === 'following' ? 'Qiziqarli odamlarga obuna bo‘ling — ularning fikrlari shu yerda chiqadi.' : 'Birinchi fikrni siz yozing!',
    ])
@endsection
