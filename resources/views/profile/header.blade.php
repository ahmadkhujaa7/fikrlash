@php use App\Support\Time; @endphp
<section class="px-4 pb-8 pt-12 sm:px-5">
    <div class="flex items-start justify-between gap-6">
        <div class="min-w-0">
            <h1 class="display !text-[2.25rem]">{{ $user->name }}</h1>
            <p class="mt-2 text-[15px] text-muted">{{ '@'.$user->username }}</p>
        </div>
        <x-avatar :user="$user" size="xl" class="!size-20 sm:!size-24" />
    </div>

    @if ($user->bio)
        <p class="mt-6 max-w-[34rem] whitespace-pre-line font-serif text-[1.2rem] leading-relaxed text-ink-soft">{{ $user->bio }}</p>
    @endif

    <div class="mt-6 flex flex-wrap items-center gap-x-6 gap-y-2 text-[15px]">
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

    <div class="mt-7 flex gap-2">
        @if ($isOwner)
            <a href="{{ route('settings.profile') }}" class="btn btn-secondary">Profilni tahrirlash</a>
        @else
            @auth
                @include('partials.follow-button', ['target' => $user, 'following' => $isFollowing])
                <x-dropdown label="Ko‘proq" align="left">
                    <x-slot:trigger class="border border-line-strong !p-2"><x-ico name="dots" /></x-slot:trigger>
                    <x-dropdown-item icon="link" @click="sharePost('{{ $user->profileUrl() }}', ''); open = false">Profil havolasi</x-dropdown-item>
                    <x-dropdown-item icon="flag" danger @click="$dispatch('report', { type: 'user', id: {{ $user->id }} }); open = false">Shikoyat qilish</x-dropdown-item>
                </x-dropdown>
            @else
                <a href="{{ route('login') }}" class="btn btn-primary">Obuna bo‘lish</a>
            @endauth
        @endif
    </div>
</section>
