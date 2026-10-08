@extends('layouts.app')
@section('title', 'Bildirishnomalar')

@php
    use App\Enums\NotificationType as T;
    use App\Support\NotificationPresenter;
    $icons = [
        T::Followed->value => ['user', 'text-ink-soft bg-sunken'],
        T::PostLiked->value => ['heart', 'text-anor bg-anor-soft'],
        T::PostCommented->value => ['chat', 'text-ink-soft bg-sunken'],
        T::CommentReplied->value => ['reply', 'text-ink-soft bg-sunken'],
        T::Mentioned->value => ['hashtag', 'text-ink-soft bg-sunken'],
        T::PostModerated->value => ['shield', 'text-ink-soft bg-sunken'],
        T::System->value => ['bulb', 'text-ink-soft bg-sunken'],
        T::Announcement->value => ['megaphone', 'text-lapis bg-lapis-soft'],
        T::Monetization->value => ['wallet', 'text-amber bg-amber-soft'],
    ];
@endphp

@section('content')
    <x-page-header title="Bildirishnomalar" class="border-b border-line">
        <a href="{{ route('settings.notifications') }}" class="icon-btn" aria-label="Bildirishnoma sozlamalari" title="Sozlamalar"><x-ico name="settings" /></a>
    </x-page-header>

    {{-- Brauzer bildirishnomalarini yoqish taklifi (faqat hali so‘ralmagan bo‘lsa) --}}
    <div x-data="pushOffer" x-show="visible" x-cloak class="mx-4 mt-4 flex items-start gap-3 rounded-2xl bg-lapis-soft/70 p-4 sm:mx-6">
        <span class="grid size-9 shrink-0 place-items-center rounded-full bg-lapis text-white"><x-ico name="bell" size="size-[18px]" /></span>
        <div class="min-w-0 flex-1">
            <p class="text-[14.5px] font-medium text-ink">Yangiliklarni o‘tkazib yubormang</p>
            <p class="mt-0.5 text-[13px] leading-relaxed text-ink-soft">Yangi xabar va bildirishnomalar telefon yoki kompyuteringizda ko‘rinsin.</p>
            <div class="mt-3 flex gap-2">
                <button type="button" class="btn btn-primary btn-sm" @click="enable()">Yoqish</button>
                <button type="button" class="btn btn-ghost btn-sm" @click="dismiss()">Keyinroq</button>
            </div>
        </div>
    </div>

    <ul class="stream">
        @forelse ($notifications as $n)
            @php
                $p = NotificationPresenter::present($n);
                [$icon, $tone] = $icons[$n->type->value];
            @endphp
            @if ($n->type === T::Announcement && $n->subject)
                @include('notifications._announcement', ['n' => $n, 'a' => $n->subject])
                @continue
            @endif
            <li class="relative flex gap-4 px-4 py-5 sm:px-6 {{ $n->isRead() ? '' : 'bg-sunken/70' }}">
                <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-full {{ $tone }}">
                    <x-ico :name="$icon" size="size-5" :solid="$n->type === T::PostLiked" />
                </span>
                <div class="min-w-0 flex-1">
                    @if ($n->actor)
                        <a href="{{ $n->actor->profileUrl() }}" class="relative z-10 mb-1 inline-block"><x-avatar :user="$n->actor" size="xs" /></a>
                    @endif
                    <p class="text-[15px] leading-snug">
                        @if ($n->actor)<span class="font-semibold">{{ $n->actor->name }}</span><x-verified :user="$n->actor" size="xs" class="ml-0.5" />@endif
                        {{ $p['text'] }}
                    </p>
                    @if ($p['excerpt'])
                        <p class="mt-1 line-clamp-2 text-sm text-muted">{{ $p['excerpt'] }}</p>
                    @endif
                    <p class="mt-1 text-xs text-muted">{{ \App\Support\Time::short($n->created_at) }}</p>
                    @if ($p['url'])
                        <a href="{{ $p['url'] }}" class="absolute inset-0" aria-label="Ochish"></a>
                    @endif
                </div>
            </li>
        @empty
            <x-empty-state icon="bell" title="Hozircha jimjitlik" text="Kimdir fikringizni yoqtirsa, izoh qoldirsa yoki sizga obuna bo‘lsa — shu yerda ko‘rasiz." />
        @endforelse
    </ul>
    {{ $notifications->links() }}
@endsection
