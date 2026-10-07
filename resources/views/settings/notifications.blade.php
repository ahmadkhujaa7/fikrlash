@extends('settings.layout')
@section('title', 'Bildirishnomalar')

@php
    use App\Enums\NotificationType as T;
    $icons = [
        T::Followed->value => 'user',
        T::PostLiked->value => 'heart',
        T::PostCommented->value => 'chat',
        T::CommentReplied->value => 'reply',
        T::Mentioned->value => 'hashtag',
    ];
@endphp

@section('settings')
    {{-- Qaysi hodisalar haqida xabar berilsin --}}
    <section>
        <h2 class="font-semibold">Nimalar haqida xabar berilsin</h2>
        <p class="mt-1 text-sm text-ink-soft">O‘chirilgan turlar bildirishnomalar ro‘yxatida ham, brauzerda ham ko‘rinmaydi. E’lonlar va moderatsiya xabarlari doim keladi.</p>
        <form method="POST" action="{{ route('settings.notifications.update') }}" class="mt-4 divide-y divide-line overflow-hidden rounded-2xl border border-line" x-data @change="$el.requestSubmit()">
            @csrf @method('PUT')
            @foreach ($types as $type)
                <label class="flex cursor-pointer items-center gap-3.5 px-4 py-3.5 transition-colors hover:bg-sunken/60">
                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-sunken text-ink-soft"><x-ico :name="$icons[$type->value] ?? 'bell'" size="size-[18px]" /></span>
                    <span class="min-w-0 flex-1 text-[15px] text-ink">{{ $type->settingLabel() }}</span>
                    <input type="checkbox" name="enabled[]" value="{{ $type->value }}" class="switch" @checked($user->wantsNotification($type))>
                </label>
            @endforeach
            <noscript><div class="p-4"><button type="submit" class="btn btn-secondary btn-sm">Saqlash</button></div></noscript>
        </form>
    </section>

    {{-- Shu qurilmada (brauzerda) ko‘rsatish --}}
    <section x-data="devicePush">
        <h2 class="font-semibold">Telefon va kompyuterda ko‘rsatish</h2>
        <p class="mt-1 text-sm text-ink-soft">Sayt ochiq turganda — boshqa ilovada yoki oynada bo‘lsangiz ham — yangi xabar va bildirishnomalar qurilmangizning bildirishnomasi bo‘lib chiqadi. Sozlama faqat shu qurilma va brauzer uchun.</p>

        <div class="mt-4 overflow-hidden rounded-2xl border border-line">
            <label class="flex cursor-pointer items-center gap-3.5 px-4 py-3.5" :class="(state === 'denied' || state === 'unsupported' || !secure) && 'cursor-not-allowed opacity-60'">
                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-lapis-soft text-lapis"><x-ico name="bell" size="size-[18px]" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-[15px] text-ink">Bu qurilmada ko‘rsatish</span>
                    <span class="block text-[13px] text-muted"
                          x-text="!secure ? 'Faqat HTTPS orqali ochilgan saytda ishlaydi' : state === 'unsupported' ? 'Brauzeringiz qo‘llab-quvvatlamaydi' : state === 'denied' ? 'Brauzerda rad etilgan — pastdagi yo‘riqnomaga qarang' : (on ? 'Yoqilgan' : 'O‘chirilgan')"></span>
                </span>
                <input type="checkbox" class="switch" :checked="on" @change.prevent="toggle(); $el.checked = on" :disabled="busy || state === 'denied' || state === 'unsupported' || !secure">
            </label>
            <div x-show="on" x-cloak class="border-t border-line px-4 py-3">
                <button type="button" class="btn btn-secondary btn-sm" @click="test()"><x-ico name="send" size="size-4" /> Sinov bildirishnomasi</button>
            </div>
        </div>

        <div x-show="state === 'denied'" x-cloak class="mt-3 rounded-2xl bg-amber-soft/60 px-4 py-3 text-[13.5px] leading-relaxed text-ink-soft">
            Brauzer bu saytga bildirishnoma ko‘rsatishni taqiqlagan. Yoqish uchun: manzil satridagi <strong>qulf</strong> belgisini bosing → <strong>Ruxsatlar</strong> (Sayt sozlamalari) → <strong>Bildirishnomalar: Ruxsat berish</strong>, so‘ng sahifani yangilang.
        </div>
        <p class="mt-3 text-[13px] text-muted">Mikrofon, kamera va joylashuv ruxsatlari — <a href="{{ route('settings.permissions') }}" class="text-ink underline underline-offset-4">Ruxsatlar</a> bo‘limida.</p>
    </section>
@endsection
