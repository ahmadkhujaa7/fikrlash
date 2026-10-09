@extends('layouts.app')
@section('title', 'Muallif paneli')

@php
    use App\Models\AuthorPayout;
    use App\Services\Monetization\MonetizationService as M;
    use Illuminate\Support\Carbon;
    $me = auth()->user();
    $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
    // O‘q uchun "yumaloq" maksimum: 1, 2, 5 × 10^n.
    $peak = max(1, (int) $daily->max('views'));
    $mag = 10 ** floor(log10($peak));
    $niceMax = (int) (collect([1, 2, 5, 10])->first(fn ($m) => $m * $mag >= $peak) * $mag);
    $bars = $daily->values()->map(fn ($d, $i) => [
        'i' => $i,
        'label' => mb_strtolower(Carbon::parse($d['date'])->translatedFormat('j-F')),
        'short' => Carbon::parse($d['date'])->format('d.m'),
        'views' => $d['views'],
        'amount' => M::money($d['amount']),
        'h' => $d['views'] ? max(2, round($d['views'] / $niceMax * 100, 2)) : 0,
    ]);
    $canWithdraw = $balance['balance'] >= $settings['min_payout'] && ! $pendingPayout;
@endphp

@section('content')
    <x-page-header title="Muallif paneli" class="!pb-4">
        @if ($me->isMonetized())
            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-soft px-3 py-1.5 text-[13px] font-medium text-[#B26E12] dark:text-[#F0B25A]"><x-author-badge :user="$me" size="sm" /> Muallif</span>
        @endif
    </x-page-header>
    @unless ($me->isMonetized())
        @php $reapplying = $application?->status === \App\Models\AuthorApplication::PENDING; @endphp
        <div class="mx-4 mb-6 flex flex-col gap-3 rounded-2xl bg-anor-soft/60 px-4 py-3 text-[13.5px] leading-relaxed text-ink-soft sm:mx-6 sm:flex-row sm:items-center">
            <p class="min-w-0 flex-1">
                @if ($reapplying)
                    <strong class="text-ink">Qayta so‘rovingiz ko‘rib chiqilmoqda.</strong> Tasdiqlangach, yangi maqolalaringiz yana daromad keltiradi.
                @else
                    <strong class="text-anor">Monetizatsiya to‘xtatilgan.</strong>
                    @if ($application?->admin_note) Sabab: {{ $application->admin_note }}. @endif
                    Yangi daromad hisoblanmaydi, lekin balansdagi mablag‘ni yechib olishingiz mumkin. Talablarga javob bersangiz, qayta so‘rov yuborishingiz mumkin.
                @endif
            </p>
            @unless ($reapplying)
                <a href="{{ route('monetization.index', ['apply' => 1]) }}" class="btn btn-secondary btn-sm shrink-0 self-start bg-paper sm:self-auto">Qayta so‘rov yuborish</a>
            @endunless
        </div>
    @endunless

    <div class="space-y-8 px-4 pb-12 sm:px-6" x-data="{ open: {{ $errors->has('amount') || $errors->has('account') || $errors->has('holder') ? 'true' : 'false' }} }">
        {{-- Balans --}}
        <section class="rounded-[24px] border border-line bg-paper p-5 sm:p-6">
            <p class="text-[13px] font-medium text-muted">Balans</p>
            <p class="mt-1 text-[clamp(2.6rem,9vw,3.4rem)] font-semibold leading-none tracking-[-0.02em] text-ink tabular-nums">{{ M::money($balance['balance']) }}</p>
            <dl class="mt-4 flex flex-wrap gap-x-6 gap-y-1.5 text-[13.5px] text-ink-soft">
                <div><dt class="inline">Jami ishlangan:</dt> <dd class="inline font-medium text-ink">{{ M::money($balance['earned']) }}</dd></div>
                <div><dt class="inline">To‘langan:</dt> <dd class="inline font-medium text-ink">{{ M::money($balance['paid']) }}</dd></div>
                @if ($balance['pending'] > 0)
                    <div><dt class="inline">Yechish kutilmoqda:</dt> <dd class="inline font-medium text-ink">{{ M::money($balance['pending']) }}</dd></div>
                @endif
            </dl>
            <div class="mt-5 flex flex-wrap items-center gap-3">
                <button type="button" class="btn btn-primary" @click="open = true" @disabled(! $canWithdraw)><x-ico name="wallet" size="size-[18px]" /> Pul yechish</button>
                <span class="text-[13px] text-muted">
                    @if ($pendingPayout) Oldingi so‘rovingiz ko‘rib chiqilmoqda.
                    @elseif ($balance['balance'] < $settings['min_payout']) Eng kam yechish summasi — {{ M::money($settings['min_payout']) }}.
                    @else Kartangizga o‘tkaziladi, odatda 1–3 ish kunida.
                    @endif
                </span>
            </div>
        </section>

        {{-- Ko‘rsatkichlar --}}
        <section class="grid grid-cols-2 gap-3">
            @foreach ([
                ['30 kunda ko‘rishlar', $fmt($month['views']), 'daromad keltirgan'],
                ['30 kunda daromad', M::money($month['amount']), null],
                ['7 kunda daromad', M::money($week['amount']), $fmt($week['views']).' ko‘rish'],
                ['Narx', M::money($settings['rate_amount']), $fmt($settings['rate_views']).' ko‘rish uchun'],
            ] as [$label, $value, $hint])
                <div class="rounded-2xl border border-line bg-paper p-4">
                    <p class="text-[12.5px] text-muted">{{ $label }}</p>
                    <p class="mt-1 text-[1.35rem] font-semibold tabular-nums text-ink">{{ $value }}</p>
                    @if ($hint)<p class="mt-0.5 text-[12px] text-muted">{{ $hint }}</p>@endif
                </div>
            @endforeach
        </section>

        {{-- Kunlik ko‘rishlar (ustunli grafik) --}}
        <section class="rounded-[24px] border border-line bg-paper p-5 sm:p-6" x-data="{ i: null }" @mouseleave="i = null">
            <div class="flex items-baseline justify-between gap-3">
                <h2 class="font-semibold">Kunlik daromadli ko‘rishlar</h2>
                <span class="text-[12.5px] text-muted">oxirgi 30 kun</span>
            </div>

            <div class="relative mt-5">
                {{-- Gorizontal chiziqlar va qiymatlar --}}
                <div class="pointer-events-none absolute inset-x-0 top-0 h-44">
                    @foreach ([1, 0.5, 0] as $f)
                        <div class="absolute inset-x-0 flex items-center gap-2" style="top: {{ (1 - $f) * 100 }}%">
                            <span class="w-9 -translate-y-1/2 text-right text-[11px] tabular-nums text-muted">{{ $fmt($niceMax * $f) }}</span>
                            <span class="h-px flex-1 -translate-y-1/2 bg-line"></span>
                        </div>
                    @endforeach
                </div>
                <div class="relative ml-11 flex h-44 items-end gap-[2px]">
                    @foreach ($bars as $b)
                        <button type="button" class="group/bar relative flex h-full flex-1 items-end justify-center focus:outline-none"
                                @mouseenter="i = {{ $b['i'] }}" @focus="i = {{ $b['i'] }}" @click="i = i === {{ $b['i'] }} ? null : {{ $b['i'] }}"
                                aria-label="{{ $b['label'] }}: {{ $b['views'] }} ko‘rish, {{ $b['amount'] }}">
                            <span class="chart-bar block w-full max-w-[24px] rounded-t-[4px] transition-opacity" :class="i !== null && i !== {{ $b['i'] }} && 'opacity-45'" style="height: {{ $b['h'] }}%"></span>
                        </button>
                    @endforeach
                </div>
                {{-- Tooltip --}}
                @foreach ($bars as $b)
                    <div x-show="i === {{ $b['i'] }}" x-cloak class="pointer-events-none absolute z-10 whitespace-nowrap rounded-xl bg-ink px-3 py-2 text-[12px] text-on-ink shadow-lg"
                         style="top: calc(11rem * {{ round((100 - $b['h']) / 100, 4) }} - 8px); left: calc(2.75rem + (100% - 2.75rem) * {{ ($b['i'] + 0.5) / max(1, $bars->count()) }}); transform: translate({{ $b['i'] < 5 ? '-15%' : ($b['i'] > $bars->count() - 6 ? '-85%' : '-50%') }}, -100%)">
                        <span class="block font-medium">{{ $b['label'] }}</span>
                        <span class="block tabular-nums opacity-90">{{ $fmt($b['views']) }} ko‘rish · {{ $b['amount'] }}</span>
                    </div>
                @endforeach
                <div class="ml-11 mt-2 flex justify-between text-[11px] tabular-nums text-muted">
                    <span>{{ $bars->first()['short'] }}</span><span>{{ $bars[intdiv($bars->count(), 2)]['short'] }}</span><span>{{ $bars->last()['short'] }}</span>
                </div>
            </div>

            <details class="mt-4 text-[13px]">
                <summary class="cursor-pointer text-ink-soft hover:text-ink">Jadval ko‘rinishi</summary>
                <table class="mt-3 w-full text-left tabular-nums">
                    <thead class="text-[12px] text-muted"><tr><th class="py-1 font-medium">Sana</th><th class="py-1 text-right font-medium">Ko‘rish</th><th class="py-1 text-right font-medium">Daromad</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($bars->reverse() as $b)
                            <tr><td class="py-1.5">{{ $b['label'] }}</td><td class="py-1.5 text-right">{{ $fmt($b['views']) }}</td><td class="py-1.5 text-right">{{ $b['amount'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </details>
        </section>

        {{-- Maqolalar --}}
        <section>
            <div class="flex items-baseline justify-between gap-3">
                <h2 class="font-semibold">Maqolalar</h2>
                <a href="{{ route('posts.create', ['type' => 'article']) }}" class="text-[13.5px] font-medium text-lapis hover:underline">+ Yangi maqola</a>
            </div>
            @if ($articles->isEmpty())
                <p class="mt-3 rounded-2xl border border-dashed border-line-strong px-4 py-6 text-center text-[14px] text-muted">Hali maqola yo‘q. Endi yozgan maqolalaringiz daromad keltiradi.</p>
            @else
                <ul class="mt-3 divide-y divide-line overflow-hidden rounded-2xl border border-line bg-paper">
                    @foreach ($articles as $row)
                        <li class="flex items-center gap-4 px-4 py-3.5">
                            <div class="min-w-0 flex-1">
                                <a href="{{ $row['post']->url() }}" class="line-clamp-2 font-serif text-[1.05rem] font-semibold leading-snug text-ink hover:text-lapis">{{ $row['post']->title }}</a>
                                <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[12.5px] text-muted">
                                    <span>{{ $row['post']->published_at?->format('d.m.Y') }}</span>
                                    <span>{{ $fmt($row['post']->views_count) }} ko‘rish</span>
                                    @if ($row['monetized'])
                                        <span>{{ $fmt($row['paid_views']) }} daromadli</span>
                                    @else
                                        <span class="rounded-full bg-sunken px-2 py-0.5">Monetizatsiyadan oldin</span>
                                    @endif
                                </p>
                            </div>
                            <span class="shrink-0 text-right text-[14.5px] font-semibold tabular-nums {{ $row['amount'] > 0 ? 'text-ink' : 'text-muted' }}">{{ M::money($row['amount']) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Pul yechish tarixi --}}
        @if ($payouts->isNotEmpty())
            <section>
                <h2 class="font-semibold">Pul yechish tarixi</h2>
                <ul class="mt-3 divide-y divide-line overflow-hidden rounded-2xl border border-line bg-paper">
                    @foreach ($payouts as $p)
                        @php $tone = ['pending' => 'bg-amber-soft text-amber', 'paid' => 'bg-firuza-soft text-firuza', 'rejected' => 'bg-anor-soft text-anor', 'cancelled' => 'bg-sunken text-muted'][$p->status] ?? 'bg-sunken text-muted'; @endphp
                        <li class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3.5">
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold tabular-nums text-ink">{{ M::money((float) $p->amount) }}</p>
                                <p class="text-[12.5px] text-muted">{{ $p->created_at->format('d.m.Y') }} · {{ $p->maskedAccount() }}@if ($p->admin_note) · {{ $p->admin_note }}@endif</p>
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-[12px] font-medium {{ $tone }}">{{ $p->label() }}</span>
                            @if ($p->status === AuthorPayout::PENDING)
                                <form method="POST" action="{{ route('monetization.payouts.cancel', $p) }}" data-confirm="So‘rov bekor qilinsinmi?" data-confirm-text="Summa balansingizga qaytadi." data-confirm-ok="Bekor qilish">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-[13px] font-medium text-anor hover:underline">Bekor qilish</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="text-[13px] leading-relaxed text-muted">
            <h2 class="mb-1.5 font-medium text-ink-soft">Qoidalar</h2>
            <p>@if ($me->monetized_at) Siz {{ $me->monetized_at->format('d.m.Y') }} dan beri muallifsiz. Shu sanadan keyin chop etilgan maqolalar daromad keltiradi.@endif Ko‘rish — ro‘yxatdan o‘tgan o‘quvchining maqolani birinchi marta ochib, kamida {{ $settings['min_read_seconds'] }} soniya o‘qishi; har bir o‘quvchi bir marta hisoblanadi. Statistika har 10 daqiqada yangilanadi (yarim soatlik kechikish bilan).</p>
        </section>

        {{-- Pul yechish oynasi --}}
        <x-modal title="Pul yechish">
            <form method="POST" action="{{ route('monetization.payouts.store') }}" class="space-y-4"
                  x-data="{ card: @js(old('account', '')), format() { this.card = this.card.replace(/\D/g, '').slice(0, 16).replace(/(\d{4})(?=\d)/g, '$1 '); } }">
                @csrf
                <p class="text-sm text-ink-soft">Balansingiz: <strong class="text-ink">{{ M::money($balance['balance']) }}</strong>. Eng kami — {{ M::money($settings['min_payout']) }}.</p>
                <x-input name="amount" label="Summa (so‘m)" type="number" :value="old('amount', (int) floor($balance['balance']))" min="1" step="1" inputmode="numeric" required />
                <div>
                    <label for="account" class="field-label">Karta raqami (Uzcard / Humo)</label>
                    <input id="account" name="account" x-model="card" @input="format()" inputmode="numeric" autocomplete="cc-number" placeholder="8600 0000 0000 0000" class="field tabular-nums tracking-wide" required>
                    @error('account')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <x-input name="holder" label="Karta egasining ismi" :value="old('holder', $me->name)" autocomplete="cc-name" required />
                <p class="text-[12.5px] text-muted">Karta raqami shifrlangan holda saqlanadi va faqat to‘lov uchun ishlatiladi.</p>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" class="btn btn-secondary" @click="open = false">Bekor qilish</button>
                    <button type="submit" class="btn btn-primary">So‘rov yuborish</button>
                </div>
            </form>
        </x-modal>
    </div>
@endsection
