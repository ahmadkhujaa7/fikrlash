@props(['align' => 'right', 'label' => 'Ko‘proq'])
<div x-data="{ open: false }" class="relative" @keydown.escape="open = false" @click.outside="open = false">
    <button type="button" @click="open = !open" :aria-expanded="open" aria-haspopup="menu" aria-label="{{ $label }}"
            {{ $trigger->attributes->merge(['class' => 'rounded-full p-2 text-muted transition-colors hover:bg-sunken hover:text-ink']) }}>
        {{ $trigger }}
    </button>
    <div x-show="open" x-cloak x-transition.opacity.duration.120ms role="menu"
         class="absolute {{ $align === 'right' ? 'right-0' : 'left-0' }} z-40 mt-2 min-w-56 overflow-hidden rounded-xl border border-line bg-surface py-1.5 shadow-[0_12px_40px_-12px_rgb(0_0_0/0.25)]">
        {{ $slot }}
    </div>
</div>
