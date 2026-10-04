@extends('layouts.app')
@section('title', 'Mavzular')

@section('content')
    <x-page-header title="Mavzular" text="Qiziq mavzularni belgilang — “Siz uchun” lentasi ularga moslashadi." class="border-b border-line" />
    <ul class="stream">
        @foreach ($categories as $category)
            <li class="flex items-center gap-4 px-4 py-6 sm:px-6">
                <a href="{{ route('categories.show', $category) }}" class="min-w-0 flex-1">
                    <span class="block font-serif text-[1.5rem] font-medium tracking-[-0.01em] hover:underline">{{ $category->name }}</span>
                    <span class="mt-1 block text-[15px] text-muted">{{ $category->description }}</span>
                </a>
                @auth
                    @php $isOn = in_array($category->id, $followed, true); @endphp
                    <button type="button" class="btn btn-sm {{ $isOn ? 'btn-primary' : 'btn-secondary' }}"
                            x-data="categoryFollow({ following: {{ $isOn ? 'true' : 'false' }}, url: '{{ route('api.v1.categories.follow', $category) }}' })"
                            @click="flip" :class="{ 'btn-primary': following, 'btn-secondary': !following }"
                            x-text="following ? 'Qiziqaman ✓' : 'Qiziqaman'">{{ $isOn ? 'Qiziqaman ✓' : 'Qiziqaman' }}</button>
                @endauth
            </li>
        @endforeach
    </ul>
@endsection
