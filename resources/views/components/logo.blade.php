{{--
  Sayt logosi. Admin panelda logo yuklangan bo‘lsa — rasm (tungi rejim uchun alohida varianti bo‘lishi mumkin),
  aks holda standart so‘z belgisi: serif "fikrlash" + lojuvard nuqta.
--}}
@props(['variant' => 'auto'])
@php
    $light = \App\Support\Branding::logoUrl();
    $dark = \App\Support\Branding::logoUrl(dark: true);
    $name = \App\Support\Branding::name();
@endphp
@if ($light)
    <picture {{ $attributes->merge(['class' => 'inline-flex items-center']) }}>
        @if ($dark && $variant === 'auto')<source srcset="{{ $dark }}" media="(prefers-color-scheme: dark)">@endif
        <img src="{{ $variant === 'on-dark' && $dark ? $dark : $light }}" alt="{{ $name }}" class="h-7 w-auto max-w-[180px] object-contain sm:h-8">
    </picture>
@else
    <span {{ $attributes->merge(['class' => 'inline-flex items-baseline font-serif text-[1.6rem] font-medium leading-none tracking-[-0.02em] '.($variant === 'on-dark' ? 'text-white' : 'text-ink')]) }}>
        fikrlash<span class="{{ $variant === 'on-dark' ? 'text-white/60' : 'text-lapis' }}">.</span>
    </span>
@endif
