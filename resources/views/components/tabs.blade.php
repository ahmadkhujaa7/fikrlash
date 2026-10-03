{{-- Havolali tablar: [['label' => .., 'href' => .., 'active' => bool]] --}}
@props(['items'])
<nav {{ $attributes->merge(['class' => 'flex gap-6 overflow-x-auto border-b border-line px-4 sm:px-5']) }} aria-label="Bo‘limlar">
    @foreach ($items as $item)
        <a href="{{ $item['href'] }}" class="tab shrink-0" @if ($item['active']) aria-current="page" @endif>{{ $item['label'] }}</a>
    @endforeach
</nav>
