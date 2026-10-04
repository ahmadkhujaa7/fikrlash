@extends('layouts.app')

@section('content')
    <h1 class="sr-only">Lenta</h1>

    @auth
        @include('partials.composer', ['compact' => true])
    @else
        <section class="px-4 pb-10 pt-14 sm:px-5 sm:pt-20">
            <p class="display max-w-xl">Har bir katta ish kichik fikrdan boshlanadi.</p>
            <p class="mt-5 max-w-md text-[17px] leading-relaxed text-ink-soft">O‘zbek tilida fikr, g‘oya va savollarni yozing, boshqalarnikini o‘qing va muhokama qiling.</p>
            <div class="mt-8 flex flex-wrap gap-2">
                <a href="{{ route('register') }}" class="btn btn-primary btn-lg">Yozishni boshlash</a>
                <a href="{{ route('login') }}" class="btn btn-secondary btn-lg">Kirish</a>
            </div>
        </section>
    @endauth

    <div class="sticky top-16 z-20 bg-paper/90 backdrop-blur-md">
        <x-tabs :items="array_values(array_filter([
            ['label' => 'Siz uchun', 'href' => route('home'), 'active' => $tab === 'for-you'],
            ['label' => 'Eng yangi', 'href' => route('home', ['tab' => 'latest']), 'active' => $tab === 'latest'],
            auth()->check() ? ['label' => 'Obunalar', 'href' => route('home', ['tab' => 'following']), 'active' => $tab === 'following'] : null,
        ]))" />
    </div>

    @include('partials.feed', [
        'emptyTitle' => $tab === 'following' ? 'Obunalaringizda hali post yo‘q' : 'Hali hech kim yozmadi',
        'emptyText' => $tab === 'following' ? 'Qiziqarli odamlarga obuna bo‘ling — ularning fikrlari shu yerda chiqadi.' : 'Birinchi fikrni siz yozing.',
    ])
@endsection
