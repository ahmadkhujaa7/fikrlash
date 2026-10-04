@php
    $me = auth()->user();
    $announcement = \App\Models\Setting::read('announcement');
    $is = fn (string ...$patterns) => request()->routeIs(...$patterns) ? 'page' : 'false';
@endphp
<!DOCTYPE html>
<html lang="uz">
<head>
    @include('layouts.head')
</head>
<body @auth data-auth="1" @endauth @if (session('toast')) data-toast="{{ session('toast') }}" @endif
      x-data="reporter" @report.window="show($event.detail)">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[70] focus:rounded-full focus:bg-surface focus:px-4 focus:py-2">Asosiy qismga o‘tish</a>

@if ($announcement)
    <div class="border-b border-line bg-sunken px-4 py-2.5 text-center text-[13px] text-ink-soft">{{ $announcement }}</div>
@endif

<header class="sticky top-0 z-30 bg-canvas/80 backdrop-blur-lg backdrop-saturate-150">
    <div class="mx-auto flex h-16 max-w-[1080px] items-center gap-6 px-4 sm:px-6">
        <a href="{{ route('home') }}" aria-label="Fikrlash.uz bosh sahifa"><x-logo /></a>

        <nav class="hidden items-center gap-1 md:flex" aria-label="Asosiy menyu">
            <a href="{{ route('home') }}" class="nav-link" aria-current="{{ $is('home') }}">Lenta</a>
            <a href="{{ route('categories.index') }}" class="nav-link" aria-current="{{ $is('categories.*') }}">Mavzular</a>
        </nav>

        @unless (request()->routeIs('search'))
            <form action="{{ route('search') }}" method="GET" role="search" class="relative ml-auto hidden w-full max-w-[260px] md:block" x-data
                  @keydown.window.slash="if (! ['INPUT','TEXTAREA','SELECT'].includes($event.target.tagName)) { $event.preventDefault(); $refs.q.focus() }">
                <x-ico name="search" size="size-4" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-muted" />
                <input type="search" name="q" x-ref="q" placeholder="Qidirish" aria-label="Qidirish"
                       class="h-9 w-full rounded-full border border-transparent bg-ink/[0.05] pl-10 pr-9 text-sm text-ink placeholder:text-muted transition-colors focus:border-line-strong focus:bg-paper focus:outline-none">
                <kbd class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 rounded border border-line-strong px-1.5 font-sans text-[11px] leading-4 text-muted">/</kbd>
            </form>
        @endunless

        <div class="{{ request()->routeIs('search') ? 'ml-auto' : 'ml-auto md:ml-0' }} flex items-center gap-1.5">
            <a href="{{ route('search') }}" class="rounded-full p-2 text-muted hover:text-ink md:hidden" aria-label="Qidiruv"><x-ico name="search" /></a>
            @auth
                <a href="{{ route('posts.create') }}" class="btn btn-primary btn-sm mr-1 hidden md:inline-flex"><x-ico name="pencil" size="size-4" /> Yozish</a>
                <a href="{{ route('notifications.index') }}" class="relative hidden rounded-full p-2 text-muted hover:text-ink md:block" aria-label="Bildirishnomalar"
                   x-data="unreadBadge({{ $unreadNotifications }})">
                    <x-ico name="bell" />
                    <span x-show="count > 0" x-cloak class="absolute right-1.5 top-1.5 size-2 rounded-full bg-lapis ring-2 ring-paper"></span>
                </a>
                <x-dropdown label="Akkaunt menyusi">
                    <x-slot:trigger class="!p-1"><x-avatar :user="$me" size="xs" /></x-slot:trigger>
                    <div class="border-b border-line px-4 pb-3 pt-2">
                        <p class="truncate text-sm font-medium text-ink">{{ $me->name }}</p>
                        <p class="truncate text-[13px] text-muted">{{ '@'.$me->username }}</p>
                    </div>
                    <div class="py-1">
                        <x-dropdown-item icon="user" :href="route('profile.show', $me->username)">Profil</x-dropdown-item>
                        <x-dropdown-item icon="bookmark" :href="route('saved.index')">Saqlanganlar</x-dropdown-item>
                        <x-dropdown-item icon="document" :href="route('posts.drafts')">Qoralamalar</x-dropdown-item>
                        <x-dropdown-item icon="settings" :href="route('settings.profile')">Sozlamalar</x-dropdown-item>
                        @if ($me->isAdmin())
                            <x-dropdown-item icon="shield" href="/admin">Admin panel</x-dropdown-item>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="border-t border-line pt-1">
                        @csrf
                        <x-dropdown-item icon="logout" type="submit">Chiqish</x-dropdown-item>
                    </form>
                </x-dropdown>
            @else
                <a href="{{ route('login') }}" class="btn btn-ghost btn-sm">Kirish</a>
                <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Ro‘yxatdan o‘tish</a>
            @endauth
        </div>
    </div>
</header>

<div class="mx-auto max-w-[1080px] sm:px-4 lg:grid lg:grid-cols-[minmax(0,660px)_1fr] lg:gap-10 lg:px-6">
    <main id="main" class="min-w-0 pb-28 sm:pt-2 lg:pb-16">
        <div class="sheet min-h-[calc(100vh-7rem)] pb-4">
            @yield('content')
        </div>
    </main>

    <aside class="hidden lg:block" aria-label="Qo‘shimcha">
        <div class="sticky top-16 max-h-[calc(100vh-4rem)] overflow-y-auto pb-10 pt-4 [scrollbar-width:none]">
            @hasSection('sidebar')
                @yield('sidebar')
            @else
                @include('partials.sidebar')
            @endif
        </div>
    </aside>
</div>

{{-- Mobil: pastki navigatsiya --}}
<nav class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-paper/90 pb-[env(safe-area-inset-bottom)] backdrop-blur-md md:hidden" aria-label="Pastki menyu">
    <div class="mx-auto grid max-w-md grid-cols-5 items-center">
        <a href="{{ route('home') }}" class="flex justify-center py-3.5 {{ request()->routeIs('home') ? 'text-ink' : 'text-muted' }}" aria-label="Lenta"><x-ico name="home" size="size-6" /></a>
        <a href="{{ route('categories.index') }}" class="flex justify-center py-3.5 {{ request()->routeIs('categories.*') ? 'text-ink' : 'text-muted' }}" aria-label="Mavzular"><x-ico name="grid" size="size-6" /></a>
        <a href="{{ auth()->check() ? route('posts.create') : route('login') }}" class="mx-auto flex size-11 items-center justify-center rounded-full bg-ink text-on-ink" aria-label="Yozish"><x-ico name="plus" size="size-5" /></a>
        @auth
            <a href="{{ route('notifications.index') }}" class="relative flex justify-center py-3.5 {{ request()->routeIs('notifications.*') ? 'text-ink' : 'text-muted' }}" aria-label="Bildirishnomalar" x-data="unreadBadge({{ $unreadNotifications }})">
                <x-ico name="bell" size="size-6" />
                <span x-show="count > 0" x-cloak class="absolute right-[32%] top-3 size-2 rounded-full bg-lapis ring-2 ring-paper"></span>
            </a>
            <a href="{{ route('profile.show', $me->username) }}" class="flex justify-center py-3.5" aria-label="Profil"><x-avatar :user="$me" size="xs" /></a>
        @else
            <a href="{{ route('search') }}" class="flex justify-center py-3.5 text-muted" aria-label="Qidiruv"><x-ico name="search" size="size-6" /></a>
            <a href="{{ route('login') }}" class="flex justify-center py-3.5 text-muted" aria-label="Kirish"><x-ico name="user" size="size-6" /></a>
        @endauth
    </div>
</nav>

@include('partials.report-modal')
<x-toasts />
</body>
</html>
