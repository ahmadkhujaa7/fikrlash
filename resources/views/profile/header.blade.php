@php use App\Support\Time; @endphp
<section class="pb-7">
    {{-- Muqova: foydalanuvchining doimiy rangi + girih naqshi --}}
    <div class="girih tone-{{ $user->tone() }} bg-tone m-2 h-32 rounded-[22px] text-white sm:h-40" style="--girih-opacity:.16">
        <div class="pointer-events-none absolute inset-0 -z-10 bg-[linear-gradient(180deg,transparent_30%,rgb(0_0_0/0.18))]"></div>
    </div>

    <div class="px-4 sm:px-6">
        <div class="-mt-14 flex items-end justify-between gap-4 sm:-mt-16">
            @if ($user->avatarUrl())
                {{-- Bir marta bosilganda profil rasmi to‘liq ekranda ochiladi --}}
                <button type="button" class="relative z-10 shrink-0 cursor-zoom-in rounded-full transition-transform active:scale-[0.97]" data-lightbox="" data-full="{{ $user->avatarUrl() }}" aria-label="Profil rasmini ko‘rish">
                    <x-avatar :user="$user" size="xl" class="!size-24 ring-[5px] ring-paper sm:!size-28" />
                </button>
            @else
                <x-avatar :user="$user" size="xl" class="relative z-10 !size-24 ring-[5px] ring-paper sm:!size-28 sm:!text-5xl" />
            @endif
            <div class="flex gap-2 pb-1">
                @if ($isOwner)
                    {{-- O‘z profilim: akkaunt menyusi (mobilda yuqori paneldagi avatar menyusi o‘rniga) --}}
                    <x-dropdown label="Akkaunt menyusi">
                        <x-slot:trigger class="border border-line-strong !p-2"><x-ico name="dots" /></x-slot:trigger>
                        <x-dropdown-item icon="bookmark" :href="route('saved.index')">Saqlanganlar</x-dropdown-item>
                        <x-dropdown-item icon="document" :href="route('posts.drafts')">Qoralamalar</x-dropdown-item>
                        <x-dropdown-item icon="settings" :href="route('settings.profile')">Sozlamalar</x-dropdown-item>
                        @if (auth()->user()->isAdmin())
                            <x-dropdown-item icon="shield" href="/admin">Admin panel</x-dropdown-item>
                        @endif
                        <form method="POST" action="{{ route('logout') }}" class="border-t border-line pt-1">
                            @csrf
                            <x-dropdown-item icon="logout" type="submit">Chiqish</x-dropdown-item>
                        </form>
                    </x-dropdown>
                    <a href="{{ route('settings.profile') }}" class="btn btn-secondary">Profilni tahrirlash</a>
                @else
                    @auth
                        <x-dropdown label="Ko‘proq">
                            <x-slot:trigger class="border border-line-strong !p-2"><x-ico name="dots" /></x-slot:trigger>
                            <x-dropdown-item icon="link" @click="sharePost({{ \Illuminate\Support\Js::from($user->profileUrl()) }}, {{ \Illuminate\Support\Js::from($user->name) }}); open = false">Profil havolasini ulashish</x-dropdown-item>
                            <x-dropdown-item icon="flag" danger @click="$dispatch('report', { type: 'user', id: {{ $user->id }} }); open = false">Shikoyat qilish</x-dropdown-item>
                        </x-dropdown>
                        @php
                            $chat = app(\App\Services\Chat\ChatService::class);
                            $canChat = \App\Models\Conversation::query()->where('pair_key', \App\Models\Conversation::pairKey(auth()->id(), $user->id))->exists()
                                || $chat->cannotMessage(auth()->user(), $user) === null;
                        @endphp
                        @if ($canChat)
                            <a href="{{ route('messages.with', $user->username) }}" class="btn btn-secondary !px-3 sm:!px-4" aria-label="Xabar yozish">
                                <x-ico name="chat" size="size-[18px]" /><span class="hidden sm:inline">Xabar</span>
                            </a>
                        @endif
                        @include('partials.follow-button', ['target' => $user, 'following' => $isFollowing])
                    @else
                        <a href="{{ route('login') }}" class="btn btn-primary">Obuna bo‘lish</a>
                    @endauth
                @endif
            </div>
        </div>

        <h1 class="display mt-5 !text-[2.25rem] !leading-[1.1]">{{ $user->name }}@if ($user->isVerified())<x-verified :user="$user" size="lg" class="ml-2 !align-[0.05em]" />@endif</h1>
        @if ($user->isVerified())
            <p class="mt-1.5 inline-flex items-center gap-1.5 text-[13px] text-lapis">Tasdiqlangan akkaunt</p>
        @endif
        <p class="mt-1 text-[15px] text-muted">{{ '@'.$user->username }}</p>

        @if ($user->bio)
            <p class="mt-5 max-w-[34rem] whitespace-pre-line font-serif text-[1.2rem] leading-relaxed text-ink-soft">{{ $user->bio }}</p>
        @endif

        <div class="mt-5 flex flex-wrap items-center gap-x-6 gap-y-2 text-[15px]">
            <span><span class="font-medium tabular-nums text-ink">{{ $postsCount }}</span> <span class="text-muted">fikr</span></span>
            <a href="{{ route('profile.followers', $user->username) }}" class="hover:underline"
               x-data="{ n: {{ $user->followers_count }} }" @follow-changed.window="n = $event.detail.followers_count ?? n">
                <span class="font-medium tabular-nums text-ink" x-text="n">{{ $user->followers_count }}</span> <span class="text-muted">obunachi</span>
            </a>
            <a href="{{ route('profile.following', $user->username) }}" class="hover:underline">
                <span class="font-medium tabular-nums text-ink">{{ $user->following_count }}</span> <span class="text-muted">obuna</span>
            </a>
            <span class="meta flex items-center gap-1.5"><x-ico name="calendar" size="size-4" /> {{ Time::date($user->created_at) }}dan beri</span>
        </div>
    </div>
</section>
