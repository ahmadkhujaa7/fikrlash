@extends('layouts.app')
@section('title', 'Mavzular')

@section('content')
    <div class="border-b border-line px-5 py-5">
        <h1 class="font-serif text-2xl font-semibold">Mavzular</h1>
        <p class="mt-1 text-sm text-muted">Qiziq mavzularni tanlang — “Siz uchun” lentasi ularga moslashadi.</p>
    </div>
    <ul class="stream">
        @foreach ($categories as $category)
            <li class="flex items-center gap-4 px-5 py-4">
                <a href="{{ route('categories.show', $category) }}" class="min-w-0 flex-1">
                    <span class="block font-serif text-lg font-semibold hover:underline">{{ $category->name }}</span>
                    <span class="block text-sm text-muted">{{ $category->description }}</span>
                </a>
                @auth
                    <button type="button" class="btn btn-sm"
                            x-data="categoryFollow({ following: {{ in_array($category->id, $followed, true) ? 'true' : 'false' }}, url: '{{ route('api.v1.categories.follow', $category) }}' })"
                            @click="flip" :class="following ? 'btn-secondary' : 'btn-primary'"
                            x-text="following ? 'Qiziqaman ✓' : 'Qiziqaman'"></button>
                @endauth
            </li>
        @endforeach
    </ul>
@endsection
