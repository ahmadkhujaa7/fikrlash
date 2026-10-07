{{-- Admin e'loni: ro‘yxatda — sarlavha va qisqa mazmun; bosilganda to‘liq ochiladi (statistika uchun "ochdi" belgilanadi). --}}
@php
    use App\Support\ContentFormatter;
    use App\Support\Time;
    use Illuminate\Support\Str;
@endphp
<li x-data="announcement(@js(route('announcements.open', $a)))" class="px-4 py-4 sm:px-6 {{ $n->isRead() ? '' : 'bg-sunken/70' }}">
    <button type="button" @click="show()" class="group flex w-full items-start gap-3.5 rounded-2xl border border-line bg-paper p-3.5 text-left shadow-[0_1px_2px_rgb(0_0_0/0.04)] transition-[border-color,box-shadow] hover:border-line-strong hover:shadow-[0_8px_24px_-16px_rgb(0_0_0/0.35)]">
        <span class="grid size-10 shrink-0 place-items-center rounded-full text-white" style="background-image:linear-gradient(160deg,var(--brand-a),var(--brand-b))">
            <x-ico name="megaphone" size="size-5" />
        </span>
        <span class="min-w-0 flex-1">
            <span class="flex items-center gap-2 text-[12px] font-semibold uppercase tracking-[0.08em] text-lapis">
                E’lon
                @unless ($n->isRead())<span class="rounded-full bg-anor px-1.5 py-px text-[10px] tracking-normal text-white normal-case">yangi</span>@endunless
                <span class="font-normal normal-case tracking-normal text-muted">· {{ Time::short($n->created_at) }}</span>
            </span>
            <span class="mt-1 block font-serif text-[1.18rem] font-semibold leading-snug text-ink">{{ $a->title }}</span>
            <span class="mt-1 line-clamp-2 text-[14px] leading-relaxed text-ink-soft">{{ Str::limit(preg_replace('/\s+/u', ' ', $a->body), 180) }}</span>
            <span class="mt-2 inline-flex items-center gap-1 text-[13px] font-medium text-lapis group-hover:underline">Batafsil o‘qish <x-ico name="chevron-right" size="size-3.5" /></span>
        </span>
        @if ($a->imageUrl())
            <img src="{{ $a->imageUrl() }}" alt="" loading="lazy" class="size-[72px] shrink-0 rounded-xl object-cover">
        @endif
    </button>

    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[75] flex items-end justify-center sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-label="{{ $a->title }}"
             @keydown.escape.window="open && close()">
            <div class="absolute inset-0 bg-ink/45 backdrop-blur-[2px]" @click="close()" x-show="open" x-transition.opacity></div>
            <article class="relative flex max-h-[90dvh] w-full max-w-lg flex-col overflow-hidden rounded-t-[28px] bg-surface shadow-xl sm:rounded-[28px]"
                     x-show="open" x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-8 opacity-0 sm:translate-y-2">
                <button type="button" class="absolute right-3 top-3 z-10 grid size-10 place-items-center rounded-full bg-paper/85 text-ink shadow-sm backdrop-blur hover:bg-paper" @click="close()" aria-label="Yopish"><x-ico name="x" /></button>
                <div class="overflow-y-auto overscroll-contain">
                    @if ($a->imageUrl())
                        <button type="button" class="block w-full cursor-zoom-in" data-lightbox="" data-full="{{ $a->imageUrl() }}" aria-label="Rasmni kattalashtirish">
                            <img src="{{ $a->imageUrl() }}" alt="" class="max-h-[46dvh] w-full bg-sunken object-cover">
                        </button>
                    @endif
                    <div class="px-6 pb-[calc(1.5rem+env(safe-area-inset-bottom))] pt-6 sm:pb-7">
                        <p class="flex items-center gap-2 text-[12px] font-semibold uppercase tracking-[0.08em] text-lapis">
                            <x-ico name="megaphone" size="size-4" /> E’lon · <span class="font-normal normal-case tracking-normal text-muted">{{ Time::full($a->sent_at ?? $a->created_at) }}</span>
                        </p>
                        <h2 class="mt-2 pr-8 font-serif text-[1.75rem] font-semibold leading-[1.18] tracking-[-0.01em] text-ink">{{ $a->title }}</h2>
                        <div class="prose-post mt-4 whitespace-normal !text-[1.08rem] !leading-[1.65]">{!! ContentFormatter::toHtml($a->body, rich: true) !!}</div>
                        @if ($a->link_url)
                            @php $external = ! str_starts_with($a->link_url, url('/')); @endphp
                            <a href="{{ $a->link_url }}" class="btn btn-primary mt-6 w-full sm:w-auto" @if ($external) target="_blank" rel="noopener" @endif>
                                {{ $a->link_label ?: 'Batafsil' }} <x-ico :name="$external ? 'external' : 'chevron-right'" size="size-4" />
                            </a>
                        @endif
                    </div>
                </div>
            </article>
        </div>
    </template>
</li>
