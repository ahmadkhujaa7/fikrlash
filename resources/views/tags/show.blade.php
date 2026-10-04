@extends('layouts.app')
@section('title', '#'.($tag?->name ?? $slug))

@section('content')
    <x-page-header :title="'#'.($tag?->name ?? $slug)" class="border-b border-line" />
    @if ($posts)
        @include('partials.feed', ['emptyTitle' => 'Bu teg bilan post yo‘q'])
    @else
        <x-empty-state icon="hashtag" title="Bu teg bilan hali post yo‘q" text="Postingizga #{{ $slug }} qo‘shing — birinchi bo‘lasiz." />
    @endif
@endsection
