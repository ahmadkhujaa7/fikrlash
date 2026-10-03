@php use App\Support\Time; @endphp
<div class="flex items-center gap-3 border-b border-line px-2 py-2 lg:hidden">
    <a href="{{ route('home') }}" class="btn-ghost rounded-full p-2" aria-label="Orqaga"><x-ico name="arrow-left" /></a>
    <span class="font-semibold">{{ $user->name }}</span>
</div>
<section class="px-4 pb-4 pt-6 sm:px-6">
    <div class="flex items-start justify-between gap-4">
        <x-avatar :user="$user" size="xl" />
        <div class="flex gap-2 pt-2">
            @if ($isOwner)
                <a href="{{ route('settings.profile') }}" class="btn btn-secondary">Profilni tahrirlash</a>
            @else
                @auth
                    @include('partials.follow-button', ['target' => $user, 'following' => $isFollowing])
                    <x-dropdown label="Ko‘proq">
                        <x-slot:trigger class="border border-line"><x-ico name="dots" /></x-slot:trigger>
                        <x-dropdown-item icon="link" @click="sharePost('{{ $user->profileUrl() }}', ''); open = false">Profil havolasi</x-dropdown-item>
                        <x-dropdown-item icon="flag" danger @click="$dispatch('report', { type: 'user', id: {{ $user->id }} }); open = false">Shikoyat qilish</x-dropdown-item>
                    </x-dropdown>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary">Obuna bo‘lish</a>
                @endauth
            @endif
        </div>
    </div>

    <h1 class="mt-4 font-serif text-2xl font-semibold leading-tight">{{ $user->name }}</h1>
    <p class="text-muted">{{ '@'.$user->username }}</p>
    @if ($user->bio)
        <p class="mt-3 max-w-prose whitespace-pre-line text-[15px] leading-relaxed">{{ $user->bio }}</p>
    @endif
    <p class="mt-3 flex items-center gap-1.5 text-sm text-muted">
        <x-ico name="calendar" size="size-4" /> {{ Time::date($user->created_at) }}dan beri Fikrlash’da
    </p>

    <dl class="mt-4 flex gap-5 text-sm">
        <div class="flex gap-1"><dt class="sr-only">Postlar</dt><dd><span class="font-semibold text-ink">{{ $postsCount }}</span> <span class="text-muted">fikr</span></dd></div>
        <a href="{{ route('profile.followers', $user->username) }}" class="flex gap-1 hover:underline"
           x-data="{ n: {{ $user->followers_count }} }" @follow-changed.window="n = $event.detail.followers_count ?? n">
            <span class="font-semibold text-ink" x-text="n">{{ $user->followers_count }}</span> <span class="text-muted">obunachi</span>
        </a>
        <a href="{{ route('profile.following', $user->username) }}" class="flex gap-1 hover:underline">
            <span class="font-semibold text-ink">{{ $user->following_count }}</span> <span class="text-muted">obuna</span>
        </a>
    </dl>
</section>
