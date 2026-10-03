@props(['align' => 'right', 'label' => 'Ko‘proq'])
<div x-data="{ open: false }" class="relative" @keydown.escape="open = false" @click.outside="open = false">
    <button type="button" @click="open = !open" :aria-expanded="open" aria-haspopup="menu" aria-label="{{ $label }}"
            {{ $trigger->attributes->merge(['class' => 'btn-ghost rounded-full p-2']) }}>
        {{ $trigger }}
    </button>
    <div x-show="open" x-cloak x-transition.origin.top role="menu"
         class="absolute {{ $align === 'right' ? 'right-0' : 'left-0' }} z-30 mt-1 min-w-52 overflow-hidden rounded-2xl border border-line bg-surface py-1.5 shadow-lg">
        {{ $slot }}
    </div>
</div>
