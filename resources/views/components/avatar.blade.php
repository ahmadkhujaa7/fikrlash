@props(['user', 'size' => 'md'])
@php
    $sizes = ['xs' => 'size-7 text-[13px]', 'sm' => 'size-9 text-sm', 'md' => 'size-10 text-base', 'lg' => 'size-16 text-2xl', 'xl' => 'size-24 text-4xl'];
    $cls = $sizes[$size] ?? $sizes['md'];
@endphp
@if ($user?->avatarUrl())
    <img src="{{ $user->avatarUrl() }}" alt="" loading="lazy" {{ $attributes->merge(['class' => "$cls shrink-0 rounded-full object-cover bg-sunken"]) }}>
@else
    <span {{ $attributes->merge(['class' => "$cls shrink-0 rounded-full inline-flex items-center justify-center bg-sunken font-serif font-medium text-ink-soft ring-1 ring-inset ring-line"]) }} aria-hidden="true">{{ $user?->initials() ?? '?' }}</span>
@endif
