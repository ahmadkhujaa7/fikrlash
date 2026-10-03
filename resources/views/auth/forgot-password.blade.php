@extends('layouts.auth')
@section('title', 'Parolni tiklash')

@section('content')
    <h1 class="font-serif text-3xl font-semibold leading-tight">Parolni tiklash</h1>
    <p class="mt-2 text-ink-soft">Ro‘yxatdan o‘tgan telefon raqamingizni kiriting — tasdiqlash kodini yuboramiz.</p>

    <form method="POST" action="{{ route('password.send') }}" class="mt-8 space-y-4">
        @csrf
        <x-input name="phone" label="Telefon raqam" type="tel" inputmode="tel" autocomplete="tel" required autofocus placeholder="+998 90 123 45 67" />
        <button type="submit" class="btn btn-primary btn-lg w-full">Kod yuborish</button>
    </form>
    <p class="mt-6 text-sm text-muted"><a href="{{ route('login') }}" class="font-medium text-lapis hover:underline">Kirish sahifasiga qaytish</a></p>
@endsection
