@extends('layouts.app')
@section('title', 'Fikr yozish')

@section('sidebar')
    {{-- Yozayotgan odamga yordam: maslahatlar va tezkor tugmalar --}}
    <div class="space-y-6 px-1">
        <section class="rounded-2xl border border-line bg-paper p-5">
            <h2 class="text-[13px] font-medium text-ink">Yaxshi fikr qanday yoziladi</h2>
            <ul class="mt-3 space-y-2.5 text-[13px] leading-relaxed text-ink-soft">
                <li class="flex gap-2.5"><span class="mt-[0.45rem] size-1.5 shrink-0 rounded-full bg-lapis"></span>Bitta aniq fikrdan boshlang — birinchi jumla lentada eng ko‘p o‘qiladi.</li>
                <li class="flex gap-2.5"><span class="mt-[0.45rem] size-1.5 shrink-0 rounded-full bg-lapis"></span>O‘z tajribangiz yoki misol qo‘shing.</li>
                <li class="flex gap-2.5"><span class="mt-[0.45rem] size-1.5 shrink-0 rounded-full bg-lapis"></span>Oxirida savol bering — muhokama shunday boshlanadi.</li>
            </ul>
        </section>
        <section class="px-1">
            <h2 class="rail-title">Qulayliklar</h2>
            <dl class="space-y-2.5 text-[13px] text-ink-soft">
                <div class="flex items-center justify-between gap-3"><dt>Chop etish</dt><dd><kbd class="kbd">Ctrl</kbd> + <kbd class="kbd">Enter</kbd></dd></div>
                <div class="flex items-center justify-between gap-3"><dt>Teg tanlash yoki yaratish</dt><dd><kbd class="kbd">#</kbd></dd></div>
                <div class="flex items-center justify-between gap-3"><dt>Odamni eslatish</dt><dd><kbd class="kbd">@</kbd></dd></div>
                <div class="flex items-center justify-between gap-3"><dt>Rasm qo‘yish</dt><dd><kbd class="kbd">Ctrl</kbd> + <kbd class="kbd">V</kbd></dd></div>
            </dl>
            <p class="mt-4 text-[12px] leading-relaxed text-muted">Rasmni sahifaga sudrab tashlashingiz ham mumkin. Yozganlaringiz avtomatik saqlanadi — sahifani yopsangiz ham yo‘qolmaydi.</p>
        </section>
    </div>
@endsection

@section('content')
    <div class="flex items-center gap-2 border-b border-line px-2 py-2 sm:px-3">
        <a href="{{ url()->previous() === url()->current() ? route('home') : url()->previous() }}" class="grid size-10 place-items-center rounded-full text-ink-soft hover:bg-sunken hover:text-ink" aria-label="Orqaga"><x-ico name="arrow-left" /></a>
        <h1 class="text-[15px] font-medium">Yangi fikr</h1>
    </div>
    @include('partials.composer')
@endsection
