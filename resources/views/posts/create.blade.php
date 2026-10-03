@extends('layouts.app')
@section('title', 'Fikr yozish')

@section('content')
    <div class="flex items-center gap-3 border-b border-line px-2 py-2">
        <a href="{{ url()->previous() }}" class="btn-ghost rounded-full p-2" aria-label="Orqaga"><x-ico name="arrow-left" /></a>
        <h1 class="font-semibold">Yangi fikr</h1>
    </div>
    @include('partials.composer')
    <div class="mx-5 mt-2 rounded-2xl bg-sunken px-4 py-3 text-sm text-ink-soft">
        <p class="font-medium text-ink">Yaxshi post uchun maslahat</p>
        <p class="mt-1">Bitta aniq fikrdan boshlang, o‘z tajribangizni qo‘shing va oxirida o‘quvchiga savol bering — shunda muhokama boshlanadi. <span class="text-muted">@username</span> bilan odamni eslatishingiz, <span class="text-muted">#teg</span> bilan mavzu belgilashingiz mumkin.</p>
    </div>
@endsection
