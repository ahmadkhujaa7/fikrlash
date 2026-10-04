@extends('layouts.auth')
@section('title', 'Telefonni tasdiqlash')

@section('content')
    <x-auth-steps :current="2" />

    <h1 class="display !text-[2rem] sm:!text-[2.25rem]">Raqamni tasdiqlang</h1>
    <p class="mt-2 text-ink-soft">
        <span class="font-medium text-ink">{{ $maskedPhone }}</span> raqamiga 6 xonali kod yuborildi.
        <a href="{{ $changeUrl ?? route('register') }}" class="whitespace-nowrap text-lapis hover:underline">Raqamni o‘zgartirish</a>
    </p>

    @if (session('status'))
        <div class="mt-4 rounded-2xl bg-firuza-soft px-4 py-3 text-sm text-ink">{{ session('status') }}</div>
    @endif

    @if ($devCode ?? null)
        {{-- Faqat lokal sinovda (SMS_DRIVER=log): haqiqiy SMS yuborilmaydi, kod shu yerda ko‘rinadi. --}}
        <div class="mt-4 rounded-2xl border border-dashed border-line-strong px-4 py-3 text-sm text-ink-soft">
            Sinov rejimi: SMS yuborilmaydi. Kod — <span class="font-mono font-semibold tracking-widest text-ink">{{ $devCode }}</span>
        </div>
    @endif

    <form method="POST" action="{{ $action }}" class="mt-8" x-data="otpInput" @submit="submitting = true">
        @csrf
        <label class="field-label" id="code-label">Tasdiqlash kodi</label>
        <input type="hidden" name="code" :value="code">
        {{-- 6 ta katak: avtomatik keyingisiga o‘tadi, nusxa qo‘yish va SMS'dan avtomatik to‘ldirish ishlaydi --}}
        <div class="flex justify-between gap-2" role="group" aria-labelledby="code-label" @paste.prevent="paste($event)">
            <template x-for="(d, i) in digits" :key="i">
                <input type="text" inputmode="numeric" maxlength="6" :autocomplete="i === 0 ? 'one-time-code' : 'off'"
                       :aria-label="(i + 1) + '-raqam'" x-model="digits[i]"
                       @input="onInput(i, $event)" @keydown.backspace="onBackspace(i, $event)"
                       @keydown.left.prevent="focus(i - 1)" @keydown.right.prevent="focus(i + 1)" @focus="$event.target.select()"
                       class="size-12 rounded-xl border border-line bg-sunken/60 text-center font-serif text-2xl tabular-nums text-ink transition-colors focus:border-lapis focus:bg-surface focus:outline-none sm:size-14 @error('code') !border-anor @enderror"
                       :data-otp="i">
            </template>
        </div>
        @error('code')<p class="field-error">{{ $message }}</p>@enderror

        <button type="submit" class="btn btn-primary btn-lg mt-6 w-full" :disabled="code.length !== 6 || submitting">
            <x-spinner class="!size-4 !text-current" x-show="submitting" x-cloak />
            Tasdiqlash va kirish
        </button>
    </form>

    <form method="POST" action="{{ $resendAction }}" class="mt-5 text-center" x-data="{ wait: {{ $resendIn }} }"
          x-init="const t = setInterval(() => wait > 0 ? wait-- : clearInterval(t), 1000)">
        @csrf
        <p class="text-sm text-muted" x-show="wait > 0">Yangi kodni <span class="tabular-nums" x-text="Math.floor(wait / 60) + ':' + String(wait % 60).padStart(2, '0')"></span> dan keyin so‘rash mumkin</p>
        <button type="submit" x-show="wait === 0" x-cloak class="text-sm font-medium text-lapis hover:underline">Kodni qayta yuborish</button>
    </form>
    <p class="mt-6 text-center text-[13px] text-muted">Kod {{ config('fikrlash.otp.ttl_minutes') }} daqiqa amal qiladi. SMS kelmasa, raqam to‘g‘riligini tekshiring.</p>
@endsection
