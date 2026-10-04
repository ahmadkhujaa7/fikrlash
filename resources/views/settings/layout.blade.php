@extends('layouts.app')

@section('sidebar')
    <div class="space-y-3 px-1 text-[13px] leading-relaxed text-muted">
        <p>Telefon raqamingiz va jins, tug‘ilgan sana kabi ma'lumotlar boshqalarga ko‘rsatilmaydi.</p>
        <p><a href="{{ route('privacy') }}" class="text-ink underline underline-offset-4">Maxfiylik siyosati</a></p>
    </div>
@endsection

@section('content')
    <x-page-header title="Sozlamalar" class="!pb-4" />
    <x-tabs :items="[
        ['label' => 'Profil', 'href' => route('settings.profile'), 'active' => request()->routeIs('settings.profile')],
        ['label' => 'Xavfsizlik', 'href' => route('settings.security'), 'active' => request()->routeIs('settings.security')],
        ['label' => 'Akkaunt', 'href' => route('settings.account'), 'active' => request()->routeIs('settings.account')],
    ]" />
    @if (session('status'))
        <div class="mx-5 mt-5 rounded-2xl bg-firuza-soft px-4 py-3 text-sm">{{ session('status') }}</div>
    @endif
    <div class="space-y-10 px-4 sm:px-6 py-6">
        @yield('settings')
    </div>
@endsection
