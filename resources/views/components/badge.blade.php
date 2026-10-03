@props(['tone' => 'neutral'])
@php
    $tones = [
        'neutral' => 'bg-sunken text-ink-soft', 'lapis' => 'bg-lapis-soft text-lapis', 'firuza' => 'bg-firuza-soft text-firuza',
        'anor' => 'bg-anor-soft text-anor', 'amber' => 'bg-amber-soft text-amber',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium '.($tones[$tone] ?? $tones['neutral'])]) }}>{{ $slot }}</span>
