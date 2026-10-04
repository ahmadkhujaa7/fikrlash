@extends('layouts.auth')
@section('title', 'Ro‘yxatdan o‘tish')

@section('content')
    <h1 class="display !text-[2.25rem]">Fikrlaringiz uchun joy</h1>
    <p class="mt-2 text-ink-soft">Telefon raqamingizga tasdiqlash kodi yuboramiz.</p>

    @unless ($registrationOpen)
        <div class="mt-6 rounded-2xl bg-amber-soft px-4 py-3 text-sm text-ink">Ro‘yxatdan o‘tish vaqtincha yopiq. Keyinroq urinib ko‘ring.</div>
    @else
        <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-4" novalidate>
            @csrf
            <x-input name="name" label="Ism va familiya" autocomplete="name" required maxlength="100" placeholder="Aziz Karimov" />
            <x-input name="username" label="Username" autocomplete="username" required maxlength="30" placeholder="aziz_karimov"
                     hint="Lotin harflari, raqamlar va _ belgisi. Profil manzili: fikrlash.uz/@username" />
            <x-input name="phone" label="Telefon raqam" type="tel" inputmode="tel" autocomplete="tel" required placeholder="+998 90 123 45 67" />
            <x-input name="password" label="Parol" type="password" autocomplete="new-password" required minlength="8"
                     hint="Kamida 8 belgi, harf va raqam bo‘lsin." />
            <x-input name="password_confirmation" label="Parolni takrorlang" type="password" autocomplete="new-password" required />

            <label class="flex items-start gap-3 text-sm text-ink-soft">
                <input type="checkbox" name="terms" value="1" class="mt-0.5 size-4 accent-[var(--ink)]" @checked(old('terms'))>
                <span><a href="{{ route('terms') }}" target="_blank" class="text-lapis hover:underline">Foydalanish shartlari</a> va
                      <a href="{{ route('privacy') }}" target="_blank" class="text-lapis hover:underline">maxfiylik siyosati</a>ga roziman.</span>
            </label>
            @error('terms')<p class="field-error !mt-0">{{ $message }}</p>@enderror

            <button type="submit" class="btn btn-primary btn-lg w-full">Kod olish</button>
        </form>
    @endunless

    <p class="mt-6 text-sm text-muted">Akkauntingiz bormi? <a href="{{ route('login') }}" class="font-medium text-lapis hover:underline">Kirish</a></p>
@endsection
