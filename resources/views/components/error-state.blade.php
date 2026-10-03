@props(['title' => 'Yuklab bo‘lmadi', 'text' => 'Internet aloqasini tekshirib, qayta urinib ko‘ring.'])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center px-6 py-10 text-center']) }} role="alert">
    <x-ico name="warning" size="size-8" class="mb-3 text-amber" />
    <p class="font-semibold">{{ $title }}</p>
    <p class="mt-1 text-sm text-muted">{{ $text }}</p>
    @if (! $slot->isEmpty())<div class="mt-4">{{ $slot }}</div>@endif
</div>
