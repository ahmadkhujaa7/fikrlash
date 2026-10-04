@extends('layouts.auth')
@section('title', 'Telefonni tasdiqlash')

@section('content')
    <h1 class="display !text-[2.25rem]">Kodni kiriting</h1>
    <p class="mt-2 text-ink-soft">{{ $maskedPhone }} raqamiga 6 xonali kod yuborildi. Kod {{ config('fikrlash.otp.ttl_minutes') }} daqiqa amal qiladi.</p>

    @if (session('status'))
        <div class="mt-4 rounded-2xl bg-firuza-soft px-4 py-3 text-sm text-ink">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ $action }}" class="mt-8 space-y-4">
        @csrf
        <x-input name="code" label="Tasdiqlash kodi" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="\d{6}" required autofocus
                 class="[&_input]:text-center [&_input]:font-serif [&_input]:text-2xl [&_input]:tracking-[0.5em]" placeholder="••••••" />
        <button type="submit" class="btn btn-primary btn-lg w-full">Tasdiqlash</button>
    </form>

    <form method="POST" action="{{ $resendAction }}" class="mt-4" x-data="{ wait: {{ $resendIn }} }"
          x-init="const t = setInterval(() => wait > 0 ? wait-- : clearInterval(t), 1000)">
        @csrf
        <button type="submit" class="text-sm font-medium text-lapis hover:underline disabled:cursor-default disabled:text-muted disabled:no-underline" :disabled="wait > 0">
            <span x-show="wait > 0">Yangi kodni <span x-text="wait"></span> soniyadan keyin so‘rash mumkin</span>
            <span x-show="wait === 0">Kodni qayta yuborish</span>
        </button>
    </form>
    <p class="mt-6 text-sm text-muted"><a href="{{ route('register') }}" class="hover:text-ink">Raqamni o‘zgartirish</a></p>
@endsection
