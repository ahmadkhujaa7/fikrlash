@extends('layouts.app')
@section('title', '#'.($tag?->name ?? $slug))

@section('content')
    <div class="border-b border-line px-5 py-5">
        <h1 class="font-serif text-2xl font-semibold">#{{ $tag?->name ?? $slug }}</h1>
    </div>
    @if ($posts)
        @include('partials.feed', ['emptyTitle' => 'Bu teg bilan post yo‘q'])
    @else
        <x-empty-state icon="hashtag" title="Bu teg bilan hali post yo‘q" text="Postingizga #{{ $slug }} qo‘shing — birinchi bo‘lasiz." />
    @endif
@endsection
