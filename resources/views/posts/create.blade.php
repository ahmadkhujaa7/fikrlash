@extends('layouts.app')
@section('title', $type === 'article' ? 'Maqola yozish' : 'Fikr yozish')
@section('screen', '1')

@section('sidebar')
    {{-- Yozayotgan odamga yordam: maslahatlar va tezkor tugmalar --}}
    <div class="space-y-6 px-1">
        @if ($type === 'article')
            <section class="rounded-2xl border border-line bg-paper p-5">
                <h2 class="text-[13px] font-medium text-ink">Yaxshi maqola qanday yoziladi</h2>
                <ul class="mt-3 space-y-2.5 text-[13px] leading-relaxed text-ink-soft">
                    <li class="flex gap-2.5"><span class="mt-[0.45rem] size-1.5 shrink-0 rounded-full bg-lapis"></span>Sarlavha — va'da: o‘quvchi nimani bilib oladi?</li>
                    <li class="flex gap-2.5"><span class="mt-[0.45rem] size-1.5 shrink-0 rounded-full bg-lapis"></span>Uzun matnni kichik sarlavhalar bilan bo‘limlarga ajrating.</li>
                    <li class="flex gap-2.5"><span class="mt-[0.45rem] size-1.5 shrink-0 rounded-full bg-lapis"></span>Rasmni kerakli joyga qo‘ying — birinchi rasm lentada muqova bo‘ladi.</li>
                </ul>
            </section>
            <section class="px-1">
                <h2 class="rail-title">Tezkor yozish</h2>
                <dl class="space-y-2.5 text-[13px] text-ink-soft">
                    <div class="flex items-center justify-between gap-3"><dt>Kichik sarlavha</dt><dd><kbd class="kbd">##</kbd> + <kbd class="kbd">probel</kbd></dd></div>
                    <div class="flex items-center justify-between gap-3"><dt>Iqtibos</dt><dd><kbd class="kbd">&gt;</kbd> + <kbd class="kbd">probel</kbd></dd></div>
                    <div class="flex items-center justify-between gap-3"><dt>Ro‘yxat</dt><dd><kbd class="kbd">-</kbd> yoki <kbd class="kbd">1.</kbd></dd></div>
                    <div class="flex items-center justify-between gap-3"><dt>Ajratgich</dt><dd><kbd class="kbd">---</kbd> + <kbd class="kbd">Enter</kbd></dd></div>
                    <div class="flex items-center justify-between gap-3"><dt>Qalin / kursiv</dt><dd><kbd class="kbd">Ctrl</kbd> + <kbd class="kbd">B</kbd> / <kbd class="kbd">I</kbd></dd></div>
                    <div class="flex items-center justify-between gap-3"><dt>Rasm qo‘yish</dt><dd><kbd class="kbd">Ctrl</kbd> + <kbd class="kbd">V</kbd></dd></div>
                    <div class="flex items-center justify-between gap-3"><dt>Chop etish</dt><dd><kbd class="kbd">Ctrl</kbd> + <kbd class="kbd">Enter</kbd></dd></div>
                </dl>
                <p class="mt-4 text-[12px] leading-relaxed text-muted">Yozganlaringiz brauzerda avtomatik saqlanadi. “Qoralama” — serverda saqlab, keyin davom ettirish uchun.</p>
            </section>
        @else
            <section class="rounded-2xl border border-line bg-paper p-5">
                <h2 class="text-[13px] font-medium text-ink">Yaxshi fikr qanday yoziladi</h2>
                <ul class="mt-3 space-y-2.5 text-[13px] leading-relaxed text-ink-soft">
                    <li class="flex gap-2.5"><span class="mt-[0.45rem] size-1.5 shrink-0 rounded-full bg-lapis"></span>Bitta aniq fikrdan boshlang — birinchi jumla lentada eng ko‘p o‘qiladi.</li>
                    <li class="flex gap-2.5"><span class="mt-[0.45rem] size-1.5 shrink-0 rounded-full bg-lapis"></span>O‘z tajribangiz yoki misol qo‘shing.</li>
                    <li class="flex gap-2.5"><span class="mt-[0.45rem] size-1.5 shrink-0 rounded-full bg-lapis"></span>Oxirida savol bering — muhokama shunday boshlanadi.</li>
                </ul>
                <a href="{{ route('posts.create', ['type' => 'article']) }}" class="mt-4 flex items-center gap-2 text-[13px] font-medium text-lapis hover:underline">
                    <x-ico name="newspaper" size="size-4" /> Uzunroq yozmoqchimisiz? Maqola yozing
                </a>
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
        @endif
    </div>
@endsection

@section('content')
    @if ($type === 'article')
        @include('partials.article-editor')
    @else
        <div class="app-bar">
            <a href="{{ route('home') }}" @click.prevent="backOr($el.href)" x-data class="icon-btn" aria-label="Orqaga"><x-ico name="arrow-left" /></a>
            @include('posts._type-switch', ['current' => 'post'])
        </div>
        @include('partials.composer')
    @endif
@endsection
