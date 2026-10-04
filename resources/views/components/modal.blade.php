{{-- Modal: x-data tashqarida "open" o‘zgaruvchisini boshqaradi. Esc va fon bosilganda yopiladi. --}}
@props(['show' => 'open', 'title' => null, 'maxWidth' => 'max-w-md'])
<template x-teleport="body">
    <div x-show="{{ $show }}" x-cloak class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4"
         role="dialog" aria-modal="true" @keydown.escape.window="{{ $show }} = false">
        <div x-show="{{ $show }}" x-transition.opacity class="absolute inset-0 bg-ink/40 backdrop-blur-[2px]" @click="{{ $show }} = false"></div>
        <div x-show="{{ $show }}" x-transition x-trap.noscroll="{{ $show }}"
             class="relative w-full {{ $maxWidth }} rounded-t-3xl bg-surface p-6 shadow-xl sm:rounded-3xl">
            @if ($title)
                <div class="mb-4 flex items-start justify-between gap-4">
                    <h2 class="font-serif text-2xl font-medium tracking-[-0.01em]">{{ $title }}</h2>
                    <button type="button" class="btn-ghost -mr-2 -mt-1 rounded-full p-2" @click="{{ $show }} = false" aria-label="Yopish">
                        <x-ico name="x" />
                    </button>
                </div>
            @endif
            {{ $slot }}
        </div>
    </div>
</template>
