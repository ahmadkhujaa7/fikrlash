@extends('settings.layout')
@section('title', 'Xavfsizlik')

@section('settings')
    <form method="POST" action="{{ route('settings.password') }}" class="space-y-4">
        @csrf @method('PUT')
        <h2 class="font-semibold">Parolni o‘zgartirish</h2>
        <x-input name="current_password" label="Joriy parol" type="password" autocomplete="current-password" required />
        <x-input name="password" label="Yangi parol" type="password" autocomplete="new-password" required hint="Kamida 8 belgi, harf va raqam. Boshqa qurilmalardan chiqarib yuboriladi." />
        <x-input name="password_confirmation" label="Yangi parolni takrorlang" type="password" autocomplete="new-password" required />
        <button type="submit" class="btn btn-primary">Parolni yangilash</button>
    </form>

    <section class="space-y-4">
        <h2 class="font-semibold">Telefon raqam</h2>
        <p class="text-sm text-ink-soft">Joriy raqam: <span class="font-medium text-ink">{{ $maskedPhone }}</span></p>

        @if ($pendingPhone)
            <form method="POST" action="{{ route('settings.phone.verify') }}" class="space-y-3 rounded-2xl border border-line p-4">
                @csrf
                <p class="text-sm">{{ $pendingPhone }} raqamiga kod yuborildi.</p>
                <x-input name="code" label="Tasdiqlash kodi" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required />
                <button type="submit" class="btn btn-primary btn-sm">Tasdiqlash</button>
            </form>
        @endif

        <form method="POST" action="{{ route('settings.phone') }}" class="flex flex-col gap-3 sm:flex-row sm:items-start">
            @csrf
            <x-input name="phone" type="tel" inputmode="tel" placeholder="Yangi raqam: +998 ..." class="flex-1" />
            <button type="submit" class="btn btn-secondary">Kod yuborish</button>
        </form>
    </section>
@endsection
