@extends('layouts.auth')
@section('title', 'Kirish')

@section('content')
    <h1 class="display !text-[2.25rem]">Xush kelibsiz</h1>
    <p class="mt-2 text-ink-soft">Telefon raqam yoki username bilan kiring.</p>

    @if (session('status'))
        <div class="mt-6 rounded-2xl bg-firuza-soft px-4 py-3 text-sm text-ink">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-4">
        @csrf
        <x-input name="login" label="Telefon yoki username" autocomplete="username" required autofocus placeholder="+998 90 123 45 67" />
        <div>
            <x-input name="password" label="Parol" type="password" autocomplete="current-password" required />
            <a href="{{ route('password.request') }}" class="mt-2 inline-block text-sm text-lapis hover:underline">Parolni unutdingizmi?</a>
        </div>
        <label class="flex items-center gap-3 text-sm text-ink-soft">
            <input type="checkbox" name="remember" value="1" class="size-4 accent-[var(--ink)]" checked> Meni eslab qol
        </label>
        <button type="submit" class="btn btn-primary btn-lg w-full">Kirish</button>
    </form>

    <p class="mt-6 text-sm text-muted">Hali akkaunt yo‘qmi? <a href="{{ route('register') }}" class="font-medium text-lapis hover:underline">Ro‘yxatdan o‘ting</a></p>
@endsection
