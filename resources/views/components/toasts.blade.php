<div x-data class="pointer-events-none fixed inset-x-0 bottom-20 z-[60] flex flex-col items-center gap-2 px-4 lg:bottom-6" aria-live="polite">
    <template x-for="t in $store.toasts.items" :key="t.id">
        <div x-transition.opacity class="pointer-events-auto flex max-w-md items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium shadow-lg"
             :class="t.type === 'error' ? 'bg-anor text-white' : 'bg-ink text-paper'">
            <span x-text="t.message"></span>
            <button type="button" class="opacity-70 hover:opacity-100" @click="$store.toasts.dismiss(t.id)" aria-label="Yopish">
                <x-ico name="x" size="size-4" />
            </button>
        </div>
    </template>
</div>
