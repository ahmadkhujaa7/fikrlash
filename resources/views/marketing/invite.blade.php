@extends('layouts.app')
@section('title', 'Do‘stlarni taklif qilish')

@php
    $text = 'Fikrlash.uz — o‘zbek tilidagi fikr va maqolalar platformasi. Menga qo‘shiling:';
    $share = [
        ['Telegram', 'telegram', 'https://t.me/share/url?url='.rawurlencode($url).'&text='.rawurlencode($text)],
        ['WhatsApp', 'chat', 'https://wa.me/?text='.rawurlencode($text.' '.$url)],
    ];
@endphp

@section('content')
    <x-page-header title="Do‘stlarni taklif qilish" text="Shaxsiy havolangizni do‘stlaringizga yuboring. Ular shu havola orqali ro‘yxatdan o‘tsa, sizga xabar keladi va ular quyidagi ro‘yxatda paydo bo‘ladi." class="border-b border-line" />

    <div class="space-y-8 px-4 py-6 sm:px-6" x-data="{ url: @js($url), text: @js($text),
            copy() { window.copyText(this.url).then((ok) => window.toast?.(ok ? 'Havola nusxalandi.' : 'Nusxa olib bo‘lmadi.', ok ? 'success' : 'error')) },
            native() { (window.fkNative ? window.fkNative.share({ url: this.url, text: this.text }) : navigator.share({ url: this.url, text: this.text })).catch(() => {}) },
            canNative: Boolean(window.fkNative) || typeof navigator.share === 'function' }">
        <section class="rounded-[24px] border border-line bg-paper p-5 sm:p-6">
            <label for="invite-url" class="field-label">Sizning havolangiz</label>
            <div class="flex flex-col gap-3 sm:flex-row">
                <input id="invite-url" type="text" readonly value="{{ $url }}" class="field min-w-0 flex-1 font-medium tabular-nums" @focus="$el.select()">
                <button type="button" class="btn btn-primary btn-lg shrink-0" @click="copy()"><x-ico name="copy" size="size-[18px]" /> Nusxa olish</button>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach ($share as [$label, $icon, $href])
                    <a href="{{ $href }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><x-ico :name="$icon" size="size-4" /> {{ $label }}</a>
                @endforeach
                <button type="button" class="btn btn-secondary btn-sm" x-show="canNative" x-cloak @click="native()"><x-ico name="share" size="size-4" /> Boshqa ilovaga</button>
            </div>
        </section>

        <section>
            <div class="flex items-baseline justify-between gap-3">
                <h2 class="font-semibold">Siz taklif qilganlar</h2>
                <span class="text-[14px] tabular-nums text-muted">{{ number_format($total, 0, ',', ' ') }} kishi</span>
            </div>

            @if ($invitees->isEmpty())
                <x-empty-state icon="users" title="Hali hech kim yo‘q" text="Havolani bitta yaqin do‘stingizga yuborib ko‘ring — u ro‘yxatdan o‘tishi bilan shu yerda chiqadi." class="!px-0 !py-10" />
            @else
                <ul class="mt-3 divide-y divide-line overflow-hidden rounded-2xl border border-line bg-paper">
                    @foreach ($invitees as $friend)
                        <li>
                            <a href="{{ $friend->profileUrl() }}" class="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-sunken">
                                <x-avatar :user="$friend" size="sm" />
                                <span class="min-w-0 flex-1">
                                    <span class="flex items-center gap-1 truncate text-[15px] font-medium text-ink">{{ $friend->name }} <x-verified :user="$friend" /></span>
                                    <span class="meta">{{ '@'.$friend->username }}</span>
                                </span>
                                <span class="meta shrink-0">{{ $friend->created_at->diffForHumans() }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection
