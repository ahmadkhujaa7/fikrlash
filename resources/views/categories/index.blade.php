@extends('layouts.app')
@section('title', 'Mavzular')

@section('content')
    <x-page-header title="Mavzular" text="Har bir postning mavzusini tizim o‘zi aniqlaydi. Bu yerda mavzular bo‘yicha o‘qishingiz mumkin." class="border-b border-line" />
    <ul class="stream">
        @foreach ($categories as $category)
            <li class="flex items-center gap-4 px-4 py-6 sm:px-6">
                <a href="{{ route('categories.show', $category) }}" class="min-w-0 flex-1">
                    <span class="block font-serif text-[1.5rem] font-medium tracking-[-0.01em] hover:underline">{{ $category->name }}</span>
                    <span class="mt-1 block text-[15px] text-muted">{{ $category->description }}</span>
                </a>
                <x-ico name="chevron-right" class="text-muted" />
            </li>
        @endforeach
    </ul>
@endsection
