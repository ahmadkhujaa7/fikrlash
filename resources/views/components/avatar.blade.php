@props(['user', 'size' => 'md'])
@php
    $sizes = ['xs' => 'size-7 text-[13px]', 'sm' => 'size-9 text-[15px]', 'md' => 'size-10 text-base', 'lg' => 'size-16 text-2xl', 'xl' => 'size-24 text-4xl'];
    $cls = $sizes[$size] ?? $sizes['md'];
@endphp
@if ($user?->avatarUrl())
    <img src="{{ $user->avatarUrl() }}" alt="" loading="lazy" {{ $attributes->merge(['class' => "$cls shrink-0 rounded-full object-cover bg-sunken"]) }}>
@else
    {{-- Rasm yo‘q: foydalanuvchining doimiy rangi + serif bosh harf --}}
    <span {{ $attributes->merge(['class' => "$cls tone-".($user?->tone() ?? 0)." bg-tone shrink-0 rounded-full inline-flex items-center justify-center font-serif font-medium text-white/95 select-none"]) }} aria-hidden="true">{{ $user?->initials() ?? '?' }}</span>
@endif
