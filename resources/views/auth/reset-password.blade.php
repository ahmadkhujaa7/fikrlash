@extends('layouts.auth')
@section('title', 'Yangi parol')

@section('content')
    <h1 class="font-serif text-3xl font-semibold leading-tight">Yangi parol</h1>
    <p class="mt-2 text-ink-soft">{{ session('status') ?? $maskedPhone.' raqamiga yuborilgan kodni kiriting.' }}</p>

    <form method="POST" action="{{ route('password.reset') }}" class="mt-8 space-y-4">
        @csrf
        <input type="hidden" name="phone" value="{{ $phone }}">
        <x-input name="code" label="Tasdiqlash kodi" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus
                 class="[&_input]:text-center [&_input]:font-serif [&_input]:text-2xl [&_input]:tracking-[0.5em]" placeholder="••••••" />
        <x-input name="password" label="Yangi parol" type="password" autocomplete="new-password" required hint="Kamida 8 belgi, harf va raqam bo‘lsin." />
        <x-input name="password_confirmation" label="Parolni takrorlang" type="password" autocomplete="new-password" required />
        <button type="submit" class="btn btn-primary btn-lg w-full">Parolni yangilash</button>
    </form>
    <p class="mt-4 text-sm text-muted">Barcha qurilmalardagi sessiyalar yopiladi.</p>
    <p class="mt-2 text-sm"><a href="{{ route('password.request') }}" class="text-lapis hover:underline">Kod kelmadimi? Qayta yuborish</a></p>
@endsection
