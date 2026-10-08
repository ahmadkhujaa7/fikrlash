@extends('layouts.app')
@section('title', 'Monetizatsiya')

@php
    use App\Models\AuthorApplication;
    use App\Services\Monetization\MonetizationService as M;
    $checks = $eligibility['checks'];
    $rows = [
        'followers' => ['users', 'Obunachilar', 'obunachi'],
        'views' => ['eye', 'Maqolalaringiz ko‘rishlari', 'ko‘rish'],
        'articles' => ['newspaper', 'Chop etilgan maqolalar', 'maqola'],
    ];
    $pending = $application?->status === AuthorApplication::PENDING;
@endphp

@section('content')
    <x-page-header title="Monetizatsiya" text="Maqolalaringiz o‘qilgani uchun daromad oling. Talablarga yetgach so‘rov yuboring — admin tasdiqlagandan keyin yozgan maqolalaringiz pul keltiradi." />

    <div class="space-y-8 px-4 pb-12 sm:px-6">
        @unless ($settings['enabled'])
            <div class="rounded-2xl bg-amber-soft/70 px-4 py-3 text-[14px] text-ink-soft">Monetizatsiya dasturi hozircha yopiq. Ochilganda shu sahifada so‘rov yuborishingiz mumkin bo‘ladi.</div>
        @endunless

        {{-- Qanday ishlaydi --}}
        <section class="grid gap-3 sm:grid-cols-3">
            @foreach ([
                ['1', 'Talablarga yeting', 'Maqola yozing, obunachilar to‘plang.'],
                ['2', 'So‘rov yuboring', 'Admin ko‘rib chiqadi va “Muallif” deb tasdiqlaydi.'],
                ['3', 'Daromad oling', 'Har '.number_format($settings['rate_views'], 0, ',', ' ').' ko‘rish uchun '.M::money($settings['rate_amount']).'.'],
            ] as [$n, $title, $text])
                <div class="rounded-2xl border border-line bg-paper p-4">
                    <span class="grid size-8 place-items-center rounded-full bg-lapis-soft text-[14px] font-semibold text-lapis">{{ $n }}</span>
                    <p class="mt-3 font-medium text-ink">{{ $title }}</p>
                    <p class="mt-1 text-[13.5px] leading-relaxed text-ink-soft">{{ $text }}</p>
                </div>
            @endforeach
        </section>

        {{-- Talablar --}}
        <section>
            <h2 class="font-semibold">Talablar</h2>
            <p class="mt-1 text-sm text-ink-soft">Faqat <strong>maqolalar</strong> hisobga olinadi — qisqa fikrlar emas.</p>
            <ul class="mt-4 divide-y divide-line overflow-hidden rounded-2xl border border-line bg-paper">
                @foreach ($rows as $key => [$icon, $label, $unit])
                    @php $c = $checks[$key]; @endphp
                    <li class="px-4 py-4">
                        <div class="flex items-center gap-3">
                            <span class="grid size-9 shrink-0 place-items-center rounded-full {{ $c['ok'] ? 'bg-firuza-soft text-firuza' : 'bg-sunken text-ink-soft' }}"><x-ico :name="$c['ok'] ? 'check' : $icon" size="size-[18px]" /></span>
                            <span class="min-w-0 flex-1 text-[15px] text-ink">{{ $label }}</span>
                            <span class="text-[14px] tabular-nums {{ $c['ok'] ? 'font-medium text-firuza' : 'text-ink-soft' }}">{{ number_format($c['current'], 0, ',', ' ') }} / {{ number_format($c['required'], 0, ',', ' ') }}</span>
                        </div>
                        <div class="ml-12 mt-2.5 h-2 overflow-hidden rounded-full bg-lapis-soft" role="meter" aria-valuemin="0" aria-valuemax="{{ $c['required'] }}" aria-valuenow="{{ $c['current'] }}" aria-label="{{ $label }}">
                            <div class="h-full rounded-full {{ $c['ok'] ? 'bg-firuza' : 'bg-lapis' }}" style="width: {{ $c['percent'] }}%"></div>
                        </div>
                        @unless ($c['ok'])
                            <p class="ml-12 mt-1.5 text-[12.5px] text-muted">Yana {{ number_format($c['required'] - $c['current'], 0, ',', ' ') }} {{ $unit }} kerak</p>
                        @endunless
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- So‘rov holati / yuborish --}}
        <section>
            @if ($pending)
                <div class="flex items-start gap-3 rounded-2xl border border-line bg-paper p-4">
                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-amber-soft text-amber"><x-ico name="clock" size="size-[18px]" /></span>
                    <div>
                        <p class="font-medium text-ink">So‘rovingiz ko‘rib chiqilmoqda</p>
                        <p class="mt-0.5 text-[13.5px] text-ink-soft">{{ $application->created_at->format('d.m.Y') }} da yuborilgan. Natija haqida bildirishnoma keladi.</p>
                    </div>
                </div>
            @else
                @if ($application && in_array($application->status, [AuthorApplication::REJECTED, AuthorApplication::REVOKED], true))
                    <div class="mb-4 rounded-2xl bg-anor-soft/60 px-4 py-3 text-[13.5px] leading-relaxed text-ink-soft">
                        <strong class="text-anor">{{ $application->status === AuthorApplication::REVOKED ? 'Monetizatsiya to‘xtatilgan' : 'Oldingi so‘rov rad etilgan' }}</strong>
                        @if ($application->admin_note) — {{ $application->admin_note }}@endif.
                        Talablarga javob bersangiz, qayta so‘rov yuborishingiz mumkin.
                    </div>
                @endif
                <form method="POST" action="{{ route('monetization.apply') }}" class="rounded-2xl border border-line bg-paper p-4 sm:p-5">
                    @csrf
                    <h2 class="font-semibold">Muallif bo‘lish uchun so‘rov</h2>
                    <x-textarea name="message" label="Admin uchun izoh (ixtiyoriy)" rows="3" maxlength="1000" placeholder="Qaysi mavzularda yozasiz, qancha tez-tez?" class="mt-3" />
                    @if ($settings['terms'])
                        <p class="mt-3 whitespace-pre-line text-[13px] leading-relaxed text-muted">{{ $settings['terms'] }}</p>
                    @endif
                    <button type="submit" class="btn btn-primary mt-4 w-full sm:w-auto" @disabled(! $eligibility['eligible'] || ! $settings['enabled'])>So‘rov yuborish</button>
                    @unless ($eligibility['eligible'])
                        <p class="mt-2 text-[13px] text-muted">Barcha talablar bajarilgach tugma faollashadi.</p>
                    @endunless
                </form>
            @endif
        </section>

        <section class="text-[13px] leading-relaxed text-muted">
            <h2 class="mb-1.5 font-medium text-ink-soft">Daromad qanday hisoblanadi</h2>
            <p>Tasdiqlangandan keyin chop etilgan maqolalarning ko‘rishlari hisoblanadi. Ko‘rish — ro‘yxatdan o‘tgan foydalanuvchi maqolani birinchi marta ochib, kamida {{ $settings['min_read_seconds'] }} soniya o‘qishi; har bir o‘quvchi bir marta hisoblanadi, o‘zingizning ko‘rishlaringiz hisoblanmaydi. Eng kam yechish summasi — {{ M::money($settings['min_payout']) }}.</p>
        </section>
    </div>
@endsection
