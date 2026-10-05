@extends('layouts.app')
@section('title', 'Xabarlar')

@section('sidebar')
    <div class="space-y-4 px-1 text-[13px] leading-relaxed text-muted">
        <section class="rounded-2xl border border-line bg-paper p-5">
            <h2 class="flex items-center gap-2 text-[13px] font-medium text-ink"><x-ico name="lock" size="size-4" class="text-lapis" /> Shaxsiy yozishma</h2>
            <p class="mt-2">Xabarlar va ovozli xabarlarni faqat suhbatdagi ikki kishi ko‘radi. Yozgan matningizni tahrirlash yoki o‘chirish mumkin.</p>
        </section>
        <p class="px-1">Kim sizga yoza olishini <a href="{{ route('settings.account') }}" class="text-ink underline underline-offset-4">sozlamalarda</a> tanlaysiz.</p>
    </div>
@endsection

@section('content')
    <x-page-header title="Xabarlar" class="!pb-4 !pt-8 sm:!pt-10">
        <button type="button" class="btn btn-primary btn-sm" @click="$dispatch('new-chat')"><x-ico name="pencil" size="size-4" /> Yangi xabar</button>
    </x-page-header>

    {{-- Yangi suhbat: odamni qidirish --}}
    <div x-data="newChat('{{ route('compose.users') }}', '{{ url('/messages/with') }}')" @new-chat.window="show()" x-show="open" x-cloak
         class="border-y border-line bg-sunken/40 px-4 py-3 sm:px-6">
        <div class="relative">
            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-muted"><x-ico name="search" size="size-4" /></span>
            <input type="search" x-ref="q" x-model="q" @input.debounce.200ms="search()" @keydown.escape="open = false" placeholder="Kimga yozamiz? Ism yoki @username"
                   class="field !rounded-full !bg-paper !py-2.5 !pl-10" autocomplete="off" aria-label="Odamni qidirish">
        </div>
        <div class="mt-2 space-y-0.5" x-show="results.length">
            <template x-for="u in results" :key="u.username">
                <a :href="base + '/' + u.username" class="flex items-center gap-3 rounded-xl px-2 py-2 hover:bg-paper">
                    <template x-if="u.avatar_url"><img :src="u.avatar_url" alt="" class="size-9 rounded-full object-cover"></template>
                    <template x-if="!u.avatar_url"><span class="bg-tone grid size-9 place-items-center rounded-full font-serif text-[15px] text-white" :class="'tone-' + u.tone" x-text="u.initials"></span></template>
                    <span class="min-w-0"><span class="block truncate text-sm font-medium text-ink" x-text="u.name"></span><span class="block truncate text-[13px] text-muted" x-text="'@' + u.username"></span></span>
                </a>
            </template>
        </div>
        <p class="px-2 pt-2 text-[13px] text-muted" x-show="q.trim().length >= 2 && !loading && !results.length">Hech kim topilmadi.</p>
    </div>

    <div x-data="inbox('{{ route('messages.index', ['fragment' => 'list']) }}')" x-ref="wrap" class="stream">
        @include('messages._list')
    </div>
@endsection
