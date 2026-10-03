@extends('layouts.app')
@section('title', 'Saqlanganlar')

@section('content')
    <div class="border-b border-line px-5 py-5">
        <h1 class="font-serif text-2xl font-semibold">Saqlanganlar</h1>
        <p class="mt-1 text-sm text-muted">Faqat sizga ko‘rinadi.</p>
    </div>
    @include('partials.feed', ['emptyTitle' => 'Hali hech narsa saqlanmagan', 'emptyText' => 'Post ostidagi belgi orqali keyinroq o‘qish uchun saqlab qo‘ying.'])
@endsection
