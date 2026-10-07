{{-- Umumiy oynalar (resources/js/ui.js): rasm ko‘rish, tasdiqlash, ulashish. --}}

{{-- Rasm/video ko‘rish --}}
<div x-data="lightboxView" x-show="$store.lightbox.open" x-cloak @keydown.window="onKey($event)"
     class="fixed inset-0 z-[80] select-none bg-black text-white" role="dialog" aria-modal="true" aria-label="Rasmni ko‘rish"
     x-transition:enter="transition duration-150" x-transition:enter-start="opacity-0" x-transition:leave="transition duration-100" x-transition:leave-end="opacity-0">
    <div class="absolute inset-x-0 top-0 z-10 flex items-center gap-2 bg-gradient-to-b from-black/60 to-transparent px-2 pb-6 pt-[calc(0.5rem+env(safe-area-inset-top))]">
        <button type="button" class="grid size-11 place-items-center rounded-full hover:bg-white/10" @click="close()" aria-label="Yopish"><x-ico name="x" /></button>
        <span class="flex-1 text-[14px] tabular-nums text-white/80" x-show="$store.lightbox.items.length > 1" x-text="($store.lightbox.index + 1) + ' / ' + $store.lightbox.items.length"></span>
        <a :href="$store.lightbox.current?.src" target="_blank" rel="noopener" class="ml-auto grid size-11 place-items-center rounded-full hover:bg-white/10" aria-label="Asl hajmda ochish" title="Asl hajmda ochish">
            <x-ico name="external" size="size-[19px]" />
        </a>
    </div>

    <div class="absolute inset-0 flex items-center justify-center overflow-hidden" @click.self="close()"
         @touchstart.passive="touchStart($event)" @touchmove.passive="touchMove($event)" @touchend="touchEnd($event)">
        <template x-for="item in ($store.lightbox.current ? [$store.lightbox.current] : [])" :key="$store.lightbox.index">
            <div class="flex size-full items-center justify-center p-2 pb-16 pt-16 md:p-14" :style="stageStyle" @click.self="close()">
                <template x-if="item.type === 'video'">
                    <video :src="item.src" :poster="item.poster || ''" controls autoplay playsinline class="max-h-full max-w-full rounded-lg bg-black"></video>
                </template>
                <template x-if="item.type !== 'video'">
                    <img :src="item.src" alt="" data-zoomable draggable="false" class="max-h-full max-w-full object-contain" :class="zoom > 1 ? 'cursor-zoom-out' : 'cursor-zoom-in'"
                         :style="imageStyle" @dblclick="toggleZoom($event)">
                </template>
            </div>
        </template>
    </div>

    <template x-if="$store.lightbox.items.length > 1">
        <div>
            <button type="button" class="absolute left-3 top-1/2 hidden size-12 -translate-y-1/2 place-items-center rounded-full bg-white/10 hover:bg-white/20 disabled:opacity-0 md:grid"
                    @click="go(-1)" :disabled="$store.lightbox.index === 0" aria-label="Oldingisi"><x-ico name="chevron-left" /></button>
            <button type="button" class="absolute right-3 top-1/2 hidden size-12 -translate-y-1/2 place-items-center rounded-full bg-white/10 hover:bg-white/20 disabled:opacity-0 md:grid"
                    @click="go(1)" :disabled="$store.lightbox.index === $store.lightbox.items.length - 1" aria-label="Keyingisi"><x-ico name="chevron-right" /></button>
            <div class="absolute inset-x-0 bottom-[calc(1rem+env(safe-area-inset-bottom))] flex justify-center gap-1.5">
                <template x-for="(it, i) in $store.lightbox.items" :key="i">
                    <span class="h-1.5 rounded-full transition-all" :class="i === $store.lightbox.index ? 'w-5 bg-white' : 'w-1.5 bg-white/40'"></span>
                </template>
            </div>
        </div>
    </template>

    <p x-show="$store.lightbox.current?.caption" class="absolute inset-x-0 bottom-[calc(2.5rem+env(safe-area-inset-bottom))] mx-auto max-w-xl px-5 text-center text-[14px] leading-relaxed text-white/90"
       x-text="$store.lightbox.current?.caption"></p>
</div>

{{-- Tasdiqlash --}}
<div x-data x-show="$store.confirm.open" x-cloak data-confirm-dialog class="fixed inset-0 z-[90] flex items-end justify-center sm:items-center sm:p-4"
     role="alertdialog" aria-modal="true" :aria-label="$store.confirm.title"
     @keydown.escape.window="$store.confirm.open && $store.confirm.answer(false)">
    <div class="absolute inset-0 bg-ink/40 backdrop-blur-[2px]" @click="$store.confirm.answer(false)" x-show="$store.confirm.open" x-transition.opacity></div>
    <div class="relative w-full max-w-sm rounded-t-[28px] bg-surface p-6 pb-[calc(1.5rem+env(safe-area-inset-bottom))] shadow-xl sm:rounded-[28px] sm:pb-6"
         x-show="$store.confirm.open" x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-6 opacity-0 sm:translate-y-2"
         x-effect="$store.confirm.open && $nextTick(() => $refs.ok.focus())">
        <h2 class="font-serif text-[1.45rem] font-medium leading-snug tracking-[-0.01em]" x-text="$store.confirm.title"></h2>
        <p x-show="$store.confirm.text" class="mt-2 text-[14.5px] leading-relaxed text-ink-soft" x-text="$store.confirm.text"></p>
        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" class="btn btn-secondary" @click="$store.confirm.answer(false)">Bekor qilish</button>
            <button type="button" x-ref="ok" class="btn" :class="$store.confirm.tone === 'danger' ? 'btn-danger' : 'btn-primary'" @click="$store.confirm.answer(true)" x-text="$store.confirm.ok"></button>
        </div>
    </div>
</div>

{{-- Ulashish --}}
<div x-data="shareSheet" data-share-sheet x-show="open" x-cloak @share-post.window="show($event.detail)" @keydown.escape.window="open && close()"
     class="fixed inset-0 z-[70] flex items-end justify-center sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-label="Ulashish">
    <div class="absolute inset-0 bg-ink/40 backdrop-blur-[2px]" @click="close()" x-show="open" x-transition.opacity></div>
    <div class="relative flex max-h-[88dvh] w-full max-w-md flex-col rounded-t-[28px] bg-surface shadow-xl sm:max-h-[80vh] sm:rounded-[28px]"
         x-show="open" x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-8 opacity-0 sm:translate-y-2">
        <div class="mx-auto mt-2.5 h-1 w-10 shrink-0 rounded-full bg-line-strong sm:hidden"></div>
        <div class="flex items-center justify-between gap-3 px-5 pb-2 pt-3 sm:pt-5">
            <h2 class="font-serif text-[1.45rem] font-medium tracking-[-0.01em]">Ulashish</h2>
            <button type="button" class="-mr-2 grid size-10 place-items-center rounded-full text-muted hover:bg-sunken hover:text-ink" @click="close()" aria-label="Yopish"><x-ico name="x" /></button>
        </div>

        <template x-if="auth && post?.id">
            <div class="flex min-h-0 flex-1 flex-col">
                <label class="mx-5 flex items-center gap-2.5 rounded-full border border-line bg-sunken/70 px-4 py-2.5 focus-within:border-line-strong focus-within:bg-paper">
                    <x-ico name="search" size="size-[18px]" class="text-muted" />
                    <span class="sr-only">Kimga yuborish</span>
                    <input type="search" x-model="q" @input="onSearch()" placeholder="Kimga? Ism yoki @username" autocomplete="off" enterkeyhint="search"
                           class="w-full border-0 bg-transparent p-0 text-[15px] placeholder:text-muted/80 focus:outline-none focus:ring-0">
                </label>

                <p class="mt-3 px-5 text-[12px] font-medium uppercase tracking-[0.08em] text-muted" x-text="q.trim() ? 'Natijalar' : 'So‘nggi suhbatlar'"></p>
                <div class="relative min-h-[120px] flex-1 overflow-y-auto px-3 pb-2 pt-2">
                    <div x-show="loading && !users.length" class="grid h-24 place-items-center"><x-spinner /></div>
                    <p x-show="!loading && !users.length" class="px-2 py-6 text-center text-[14px] text-muted"
                       x-text="q.trim() ? 'Hech kim topilmadi.' : 'Hali hech kim bilan yozishmagansiz — yuqorida qidiring.'"></p>
                    <div class="grid grid-cols-4 gap-x-1 gap-y-3 sm:grid-cols-5">
                        <template x-for="u in users" :key="u.id">
                            <button type="button" class="flex min-w-0 flex-col items-center gap-1.5 rounded-2xl px-1 py-2 transition-colors hover:bg-sunken" :class="!u.can && 'opacity-45'"
                                    @click="toggle(u)" :aria-pressed="isSelected(u)" :title="u.can ? '@' + u.username : u.reason">
                                <span class="relative">
                                    <template x-if="u.avatar"><img :src="u.avatar" alt="" class="size-14 rounded-full object-cover"></template>
                                    <template x-if="!u.avatar"><span class="bg-tone grid size-14 place-items-center rounded-full font-serif text-xl text-white" :class="'tone-' + u.tone" x-text="u.initials"></span></template>
                                    <span x-show="isSelected(u)" class="absolute -bottom-0.5 -right-0.5 grid size-6 place-items-center rounded-full bg-lapis text-white ring-[3px] ring-surface"><x-ico name="check" size="size-3.5" stroke-width="3" /></span>
                                </span>
                                <span class="w-full truncate text-center text-[12.5px] leading-tight text-ink" x-text="u.name"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <div x-show="selected.length" x-cloak class="border-t border-line px-4 pb-1 pt-3">
                    <textarea x-model="body" rows="1" maxlength="1000" placeholder="Izoh yozing (ixtiyoriy)…"
                              class="block max-h-24 w-full resize-none rounded-2xl border border-line bg-sunken/70 px-4 py-2.5 text-[15px] placeholder:text-muted/80 focus:border-line-strong focus:bg-paper focus:outline-none focus:ring-0"></textarea>
                    <button type="button" class="btn btn-primary mt-2.5 w-full" @click="send()" :disabled="sending">
                        <template x-if="sending"><x-spinner class="!size-4 !text-current" /></template>
                        <span x-text="selected.length === 1 ? selected[0].name + 'ga yuborish' : selected.length + ' kishiga yuborish'"></span>
                    </button>
                </div>
            </div>
        </template>

        <div class="grid grid-cols-3 gap-2 border-t border-line px-4 pb-[calc(1rem+env(safe-area-inset-bottom))] pt-3 sm:pb-4" :class="!(auth && post?.id) && 'border-t-0'">
            <button type="button" class="flex flex-col items-center gap-1.5 rounded-2xl py-2 text-[12.5px] text-ink-soft hover:bg-sunken" @click="copyLink()">
                <span class="grid size-12 place-items-center rounded-full bg-sunken text-ink"><x-ico name="link" /></span> Havolani nusxalash
            </button>
            <a :href="telegramUrl" target="_blank" rel="noopener" class="flex flex-col items-center gap-1.5 rounded-2xl py-2 text-[12.5px] text-ink-soft hover:bg-sunken" @click="close()">
                <span class="grid size-12 place-items-center rounded-full bg-[#229ED9] text-white"><x-ico name="telegram" /></span> Telegram
            </a>
            <button type="button" x-show="canNative" class="flex flex-col items-center gap-1.5 rounded-2xl py-2 text-[12.5px] text-ink-soft hover:bg-sunken" @click="native()">
                <span class="grid size-12 place-items-center rounded-full bg-sunken text-ink"><x-ico name="share" /></span> Boshqa ilovalar
            </button>
        </div>
    </div>
</div>
