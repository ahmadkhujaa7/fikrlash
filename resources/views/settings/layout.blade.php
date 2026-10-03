@extends('layouts.app')

@section('content')
    <div class="border-b border-line px-5 pt-5">
        <h1 class="font-serif text-2xl font-semibold">Sozlamalar</h1>
    </div>
    <x-tabs :items="[
        ['label' => 'Profil', 'href' => route('settings.profile'), 'active' => request()->routeIs('settings.profile')],
        ['label' => 'Xavfsizlik', 'href' => route('settings.security'), 'active' => request()->routeIs('settings.security')],
        ['label' => 'API tokenlar', 'href' => route('settings.tokens'), 'active' => request()->routeIs('settings.tokens')],
        ['label' => 'Akkaunt', 'href' => route('settings.account'), 'active' => request()->routeIs('settings.account')],
    ]" />
    @if (session('status'))
        <div class="mx-5 mt-5 rounded-2xl bg-firuza-soft px-4 py-3 text-sm">{{ session('status') }}</div>
    @endif
    <div class="space-y-10 px-5 py-6">
        @yield('settings')
    </div>
@endsection
