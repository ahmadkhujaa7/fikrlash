<div x-data class="pointer-events-none fixed inset-x-0 bottom-[calc(5.5rem+env(safe-area-inset-bottom))] z-[100] flex flex-col items-center gap-2 px-4 md:bottom-8" aria-live="polite">
    <template x-for="t in $store.toasts.items" :key="t.id">
        <div x-transition.opacity class="pointer-events-auto flex max-w-md items-center gap-4 rounded-full px-5 py-3 text-sm shadow-[0_12px_40px_-12px_rgb(0_0_0/0.35)]"
             :class="t.type === 'error' ? 'bg-anor text-white' : 'bg-ink text-on-ink'">
            <span x-text="t.message"></span>
            <button type="button" class="opacity-60 hover:opacity-100" @click="$store.toasts.dismiss(t.id)" aria-label="Yopish">
                <x-ico name="x" size="size-4" />
            </button>
        </div>
    </template>
</div>
