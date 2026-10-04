@props(['icon' => 'bulb', 'title', 'text' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-start px-4 py-16 sm:px-6']) }} data-empty>
    <x-ico :name="$icon" size="size-6" class="mb-5 text-muted" />
    <p class="font-serif text-2xl font-medium tracking-[-0.01em] text-ink">{{ $title }}</p>
    @if ($text)<p class="mt-2 max-w-md text-[15px] leading-relaxed text-muted">{{ $text }}</p>@endif
    @if (! $slot->isEmpty())<div class="mt-6">{{ $slot }}</div>@endif
</div>
