@extends('layouts.app')
@section('title', 'Fikr yozish')

@section('content')
    <div class="flex items-center gap-2 border-b border-line px-2 py-2 sm:px-3">
        <a href="{{ url()->previous() === url()->current() ? route('home') : url()->previous() }}" class="grid size-10 place-items-center rounded-full text-ink-soft hover:bg-sunken hover:text-ink" aria-label="Orqaga"><x-ico name="arrow-left" /></a>
        <h1 class="text-[15px] font-medium">Yangi fikr</h1>
    </div>
    @include('partials.composer')
    <p class="hidden px-6 pb-6 pt-4 text-[13px] leading-relaxed text-muted md:block">
        Shunchaki yozing — mavzuni tizim o‘zi aniqlaydi va fikringizni unga qiziqadigan odamlarga ko‘rsatadi.
        <span class="text-ink-soft">Ctrl + Enter</span> — tezda chop etish.
    </p>
@endsection
