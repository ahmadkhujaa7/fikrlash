{{--
  Teg chiplari: matndagi #teglar avtomatik + qo‘lda qo‘shilganlar (mavjudini tanlash yoki yangi yaratish).
  Alpine holati — tagChips() (composer va articleEditor ichida). $field — id prefiksi, $hint — pastdagi izoh.
--}}
<div class="flex flex-wrap items-center gap-2">
    <template x-for="t in textTags" :key="'text-' + t">
        <span class="inline-flex items-center rounded-full bg-lapis-soft px-3 py-1 text-[13px] text-lapis" title="Matndan olingan teg" x-text="'#' + t"></span>
    </template>
    <template x-for="(t, i) in tags" :key="'chip-' + t">
        <span class="inline-flex items-center gap-1 rounded-full border border-line-strong bg-paper py-1 pl-3 pr-1 text-[13px] text-ink">
            <span x-text="'#' + t"></span>
            <button type="button" class="grid size-5 place-items-center rounded-full text-muted hover:bg-sunken hover:text-ink" @click="removeTag(i)" :aria-label="'#' + t + ' tegini olib tashlash'"><x-ico name="x" size="size-3" /></button>
        </span>
    </template>

    <div class="relative" x-show="tags.length < maxTags" @click.outside="tagSuggest.open = false">
        <label class="sr-only" for="{{ $field }}-tag">Teg qo‘shish</label>
        <input id="{{ $field }}-tag" x-ref="tagInput" x-model="tagQuery" type="text" autocomplete="off" enterkeyhint="done" maxlength="51"
               placeholder="+ Teg qo‘shish" @input.debounce.150ms="loadTagSuggestions()" @focus="loadTagSuggestions()" @keydown="tagKeydown($event)"
               class="w-36 rounded-full border border-dashed border-line-strong bg-transparent px-3 py-1 text-[13px] text-ink placeholder:text-muted focus:w-48 focus:border-lapis focus:outline-none">
        <div x-show="tagSuggest.open" x-cloak class="absolute bottom-full left-0 z-30 mb-2 w-64 overflow-hidden rounded-2xl border border-line bg-surface py-1.5 shadow-[0_16px_48px_-16px_rgb(0_0_0/0.3)] sm:bottom-auto sm:top-full sm:mb-0 sm:mt-2">
            <template x-for="(item, i) in tagSuggest.items" :key="item.key">
                <button type="button" @mousedown.prevent="addTag(item.name)" @mouseenter="tagSuggest.index = i"
                        class="flex w-full items-center justify-between gap-3 px-3.5 py-2 text-left text-sm" :class="i === tagSuggest.index ? 'bg-sunken' : ''">
                    <span class="truncate"><span x-show="item.isNew" class="mr-1 text-[12px] font-medium text-lapis">Yaratish</span><span class="font-serif text-[1rem]" x-text="'#' + item.name"></span></span>
                    <span class="shrink-0 text-[12px] text-muted" x-show="!item.isNew" x-text="item.count + ' ta fikr'"></span>
                </button>
            </template>
        </div>
    </div>
</div>
<p class="mt-2.5 text-[12px] leading-relaxed text-muted">{{ $hint ?? 'Teg qo‘shsangiz, fikringizni topish osonlashadi.' }} Matnda <span class="text-ink-soft">#so‘z</span> yozsangiz ham teg bo‘ladi. Ko‘pi bilan <span x-text="maxTags"></span> ta.</p>
