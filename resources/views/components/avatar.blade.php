@props(['user', 'size' => 'md'])
@php
    $sizes = ['xs' => 'size-7 text-xs', 'sm' => 'size-9 text-sm', 'md' => 'size-11 text-base', 'lg' => 'size-16 text-xl', 'xl' => 'size-24 text-3xl'];
    $cls = $sizes[$size] ?? $sizes['md'];
    // Rasmsiz foydalanuvchi uchun username'dan barqaror rang
    $palette = ['bg-lapis-soft text-lapis', 'bg-firuza-soft text-firuza', 'bg-amber-soft text-amber', 'bg-anor-soft text-anor'];
    $tone = $palette[crc32((string) $user?->username) % count($palette)];
@endphp
@if ($user?->avatarUrl())
    <img src="{{ $user->avatarUrl() }}" alt="" loading="lazy" {{ $attributes->merge(['class' => "$cls shrink-0 rounded-full object-cover bg-sunken"]) }}>
@else
    <span {{ $attributes->merge(['class' => "$cls $tone shrink-0 rounded-full inline-flex items-center justify-center font-semibold font-serif"]) }} aria-hidden="true">{{ $user?->initials() ?? '?' }}</span>
@endif
