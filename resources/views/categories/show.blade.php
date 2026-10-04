@extends('layouts.app')
@section('title', $category->name)
@section('description', $category->description ?: $category->name.' mavzusidagi fikrlar — Fikrlash.uz')

@section('content')
    <x-page-header :title="$category->name" :text="$category->description" class="border-b border-line">
        @auth
            <button type="button" class="btn btn-sm {{ $isFollowing ? 'btn-primary' : 'btn-secondary' }}"
                    x-data="categoryFollow({ following: {{ $isFollowing ? 'true' : 'false' }}, url: '{{ route('api.v1.categories.follow', $category) }}' })"
                    @click="flip" :class="{ 'btn-primary': following, 'btn-secondary': !following }"
                    x-text="following ? 'Qiziqaman ✓' : 'Qiziqaman'">{{ $isFollowing ? 'Qiziqaman ✓' : 'Qiziqaman' }}</button>
        @endauth
    </x-page-header>
    @include('partials.feed', ['emptyTitle' => 'Bu mavzuda hali fikr yo‘q', 'emptyText' => 'Birinchi bo‘lib yozing!'])
@endsection
