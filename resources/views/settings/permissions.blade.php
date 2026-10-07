@extends('settings.layout')
@section('title', 'Ruxsatlar')

@php
    $items = [
        'notifications' => ['bell', 'Bildirishnomalar', 'Yangi xabar va bildirishnomalarni telefon yoki kompyuteringizda ko‘rsatish uchun.', 'bg-lapis-soft text-lapis'],
        'microphone' => ['mic', 'Mikrofon', 'Chatda ovozli xabar yozish uchun.', 'bg-firuza-soft text-firuza'],
        'camera' => ['camera', 'Kamera', 'Chatda darhol rasm yoki video olib yuborish uchun.', 'bg-amber-soft text-amber'],
        'geolocation' => ['map-pin', 'Joylashuv', 'Chatda joylashuvingizni yuborish uchun. Faqat o‘zingiz yuborganda ishlatiladi.', 'bg-anor-soft text-anor'],
    ];
@endphp

@section('settings')
    <section x-data="permissionsPage">
        <h2 class="font-semibold">Qurilma ruxsatlari</h2>
        <p class="mt-1 text-sm text-ink-soft">Brauzer har bir imkoniyat uchun alohida ruxsat so‘raydi. Bu yerda holatini ko‘rasiz va kerakligini yoqasiz. Ruxsatlar faqat shu qurilma va brauzerga tegishli.</p>

        <div x-show="!secure" x-cloak class="mt-4 rounded-2xl bg-amber-soft/60 px-4 py-3 text-[13.5px] leading-relaxed text-ink-soft">
            Sayt HTTPS orqali ochilmagan — brauzer ruxsat so‘ray olmaydi. Sayt <strong>https://</strong> bilan ochilganda (yoki kompyuterda localhost’da) ishlaydi.
        </div>

        <ul class="mt-4 divide-y divide-line overflow-hidden rounded-2xl border border-line">
            @foreach ($items as $key => [$icon, $title, $text, $tone])
                <li class="px-4 py-4">
                    <div class="flex items-start gap-3.5">
                        <span class="grid size-10 shrink-0 place-items-center rounded-full {{ $tone }}"><x-ico :name="$icon" size="size-5" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[15px] font-medium text-ink">
                                {{ $title }}
                                <span class="rounded-full px-2 py-0.5 text-[11.5px] font-medium"
                                      :class="{
                                        'bg-firuza-soft text-firuza': stateOf('{{ $key }}') === 'granted',
                                        'bg-anor-soft text-anor': stateOf('{{ $key }}') === 'denied',
                                        'bg-sunken text-muted': !['granted', 'denied'].includes(stateOf('{{ $key }}')),
                                      }"
                                      x-text="{ granted: 'Ruxsat berilgan', denied: 'Rad etilgan', prompt: 'So‘ralmagan', unsupported: 'Qo‘llab-quvvatlanmaydi', insecure: 'HTTPS kerak', unknown: 'Ishlatilganda so‘raladi' }[stateOf('{{ $key }}')]"></span>
                            </p>
                            <p class="mt-0.5 text-[13.5px] leading-relaxed text-ink-soft">{{ $text }}</p>
                            <template x-if="['prompt', 'unknown'].includes(stateOf('{{ $key }}')) && secure">
                                <button type="button" class="btn btn-primary btn-sm mt-3" @click="request('{{ $key }}')">Ruxsat berish</button>
                            </template>
                            <template x-if="stateOf('{{ $key }}') === 'denied'">
                                <button type="button" class="btn btn-secondary btn-sm mt-3" @click="help = help === '{{ $key }}' ? null : '{{ $key }}'" :aria-expanded="help === '{{ $key }}'">Qanday yoqish?</button>
                            </template>
                        </div>
                        <template x-if="stateOf('{{ $key }}') === 'granted'">
                            <span class="grid size-8 shrink-0 place-items-center rounded-full bg-firuza-soft text-firuza"><x-ico name="check" size="size-4" stroke-width="2.4" /></span>
                        </template>
                    </div>
                    <div x-show="help === '{{ $key }}'" x-cloak x-transition class="ml-[3.375rem] mt-3 rounded-xl bg-sunken px-4 py-3 text-[13.5px] leading-relaxed text-ink-soft">
                        <p><strong>Kompyuterda:</strong> manzil satridagi qulf belgisini bosing → <em>Sayt sozlamalari</em> → <em>{{ $title }}</em>: <em>Ruxsat berish</em>. So‘ng sahifani yangilang.</p>
                        <p class="mt-2"><strong>Android (Chrome):</strong> ⋮ → <em>Sozlamalar</em> → <em>Sayt sozlamalari</em> → <em>{{ $title }}</em> → fikrlash.uz → <em>Ruxsat berish</em>.</p>
                        <p class="mt-2"><strong>iPhone (Safari):</strong> <em>Sozlamalar</em> → <em>Safari</em> → <em>{{ $title }}</em> → <em>So‘rash</em> yoki <em>Ruxsat berish</em>.</p>
                    </div>
                </li>
            @endforeach
            <li class="flex items-start gap-3.5 px-4 py-4">
                <span class="grid size-10 shrink-0 place-items-center rounded-full bg-sunken text-ink-soft"><x-ico name="photo" size="size-5" /></span>
                <div class="min-w-0 flex-1">
                    <p class="flex flex-wrap items-center gap-2 text-[15px] font-medium text-ink">Rasm va videolar (galereya)
                        <span class="rounded-full bg-firuza-soft px-2 py-0.5 text-[11.5px] font-medium text-firuza">Alohida ruxsat shart emas</span>
                    </p>
                    <p class="mt-0.5 text-[13.5px] leading-relaxed text-ink-soft">Sayt galereyangizni o‘zi ko‘ra olmaydi — faqat siz tanlagan rasm yoki video yuboriladi. Telefon birinchi marta tanlaganingizda o‘zi so‘rashi mumkin.</p>
                </div>
            </li>
        </ul>
    </section>
@endsection
