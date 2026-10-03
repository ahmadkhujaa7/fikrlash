@props(['icon' => 'bulb', 'title', 'text' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center px-6 py-16 text-center']) }} data-empty>
    <span class="mb-4 inline-flex size-14 items-center justify-center rounded-full bg-firuza-soft text-firuza">
        <x-ico :name="$icon" size="size-7" />
    </span>
    <p class="font-serif text-lg font-semibold text-ink">{{ $title }}</p>
    @if ($text)<p class="mt-1.5 max-w-sm text-sm text-muted">{{ $text }}</p>@endif
    @if (! $slot->isEmpty())<div class="mt-5">{{ $slot }}</div>@endif
</div>
