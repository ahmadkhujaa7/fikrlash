@props(['icon' => null, 'href' => null, 'danger' => false])
@php $cls = 'flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm hover:bg-sunken '.($danger ? 'text-anor' : 'text-ink'); @endphp
@if ($href)
    <a href="{{ $href }}" role="menuitem" {{ $attributes->merge(['class' => $cls]) }}>@if ($icon)<x-ico :name="$icon" size="size-4" />@endif {{ $slot }}</a>
@else
    <button type="button" role="menuitem" {{ $attributes->merge(['class' => $cls]) }}>@if ($icon)<x-ico :name="$icon" size="size-4" />@endif {{ $slot }}</button>
@endif
