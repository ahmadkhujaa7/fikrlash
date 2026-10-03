@extends('layouts.app')
@section('title', $category->name)
@section('description', $category->description ?: $category->name.' mavzusidagi fikrlar — Fikrlash.uz')

@section('content')
    <div class="flex items-start justify-between gap-4 border-b border-line px-5 py-5">
        <div>
            <h1 class="font-serif text-2xl font-semibold">{{ $category->name }}</h1>
            @if ($category->description)<p class="mt-1 text-sm text-muted">{{ $category->description }}</p>@endif
        </div>
        @auth
            <button type="button" class="btn btn-sm shrink-0 {{ $isFollowing ? 'btn-secondary' : 'btn-primary' }}"
                    x-data="categoryFollow({ following: {{ $isFollowing ? 'true' : 'false' }}, url: '{{ route('api.v1.categories.follow', $category) }}' })"
                    @click="flip" :class="{ 'btn-secondary': following, 'btn-primary': !following }"
                    x-text="following ? 'Qiziqaman ✓' : 'Qiziqaman'">{{ $isFollowing ? 'Qiziqaman ✓' : 'Qiziqaman' }}</button>
        @endauth
    </div>
    @include('partials.feed', ['emptyTitle' => 'Bu mavzuda hali fikr yo‘q', 'emptyText' => 'Birinchi bo‘lib yozing!'])
@endsection
