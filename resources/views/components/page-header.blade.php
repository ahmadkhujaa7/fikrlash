@props(['title', 'text' => null])
<header {{ $attributes->merge(['class' => 'flex items-end justify-between gap-6 px-4 pb-6 pt-12 sm:px-6']) }}>
    <div class="min-w-0">
        <h1 class="display !text-[2.25rem]">{{ $title }}</h1>
        @if ($text)<p class="mt-3 max-w-lg text-[15px] leading-relaxed text-muted">{{ $text }}</p>@endif
    </div>
    @if (! $slot->isEmpty())<div class="shrink-0">{{ $slot }}</div>@endif
</header>
