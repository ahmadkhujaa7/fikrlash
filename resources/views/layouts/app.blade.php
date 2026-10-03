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
    <div class="bg-lapis px-4 py-2 text-center text-sm text-white">{{ $announcement }}</div>
@endif

{{-- Mobil: yuqori panel --}}
<header class="sticky top-0 z-30 flex items-center justify-between border-b border-line bg-paper/90 px-4 py-2.5 backdrop-blur lg:hidden">
    <a href="{{ route('home') }}" aria-label="Bosh sahifa"><x-logo /></a>
    <div class="flex items-center gap-1">
        @guest
            <a href="{{ route('login') }}" class="btn btn-ghost btn-sm">Kirish</a>
            <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Ro‘yxatdan o‘tish</a>
        @else
            <a href="{{ route('settings.profile') }}" class="btn-ghost rounded-full p-2" aria-label="Sozlamalar"><x-ico name="settings" /></a>
        @endguest
    </div>
</header>

<div class="mx-auto flex max-w-[1260px] justify-center gap-6 lg:px-4">
    {{-- Chap navigatsiya (desktop) --}}
    <aside class="sticky top-0 hidden h-screen w-60 shrink-0 flex-col py-5 lg:flex" aria-label="Asosiy menyu">
        <a href="{{ route('home') }}" class="mb-6 px-4" aria-label="Fikrlash.uz bosh sahifa"><x-logo /></a>
        <nav class="flex flex-col gap-0.5">
            <a href="{{ route('home') }}" class="nav-item" aria-current="{{ $is('home') }}"><x-ico name="home" size="size-6" /> Bosh sahifa</a>
            <a href="{{ route('search') }}" class="nav-item" aria-current="{{ $is('search') }}"><x-ico name="search" size="size-6" /> Qidiruv</a>
            <a href="{{ route('categories.index') }}" class="nav-item" aria-current="{{ $is('categories.*') }}"><x-ico name="grid" size="size-6" /> Mavzular</a>
            @auth
                <a href="{{ route('notifications.index') }}" class="nav-item" aria-current="{{ $is('notifications.*') }}" x-data="unreadBadge({{ $unreadNotifications }})">
                    <span class="relative">
                        <x-ico name="bell" size="size-6" />
                        <span x-show="count > 0" x-cloak x-text="count > 99 ? '99+' : count" class="absolute -right-2 -top-1.5 min-w-5 rounded-full bg-anor px-1 text-center text-[11px] font-bold leading-5 text-white"></span>
                    </span>
                    Bildirishnomalar
                </a>
                <a href="{{ route('saved.index') }}" class="nav-item" aria-current="{{ $is('saved.*') }}"><x-ico name="bookmark" size="size-6" /> Saqlanganlar</a>
                <a href="{{ route('profile.show', $me->username) }}" class="nav-item" aria-current="{{ request()->is('@'.$me->username.'*') ? 'page' : 'false' }}"><x-ico name="user" size="size-6" /> Profil</a>
                <a href="{{ route('settings.profile') }}" class="nav-item" aria-current="{{ $is('settings.*') }}"><x-ico name="settings" size="size-6" /> Sozlamalar</a>
                @if ($me->isAdmin())
                    <a href="/admin" class="nav-item"><x-ico name="shield" size="size-6" /> Admin panel</a>
                @endif
            @endauth
        </nav>

        @auth
            <a href="{{ route('posts.create') }}" class="btn btn-primary btn-lg mt-6 w-full"><x-ico name="pencil" /> Fikr yozish</a>

            <div class="mt-auto">
                <x-dropdown align="left" label="Akkaunt menyusi">
                    <x-slot:trigger class="flex w-full items-center gap-3 !rounded-2xl !p-2 text-left">
                        <x-avatar :user="$me" size="sm" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-ink">{{ $me->name }}</span>
                            <span class="block truncate text-sm text-muted">{{ '@'.$me->username }}</span>
                        </span>
                        <x-ico name="dots" />
                    </x-slot:trigger>
                    <x-dropdown-item icon="document" :href="route('posts.drafts')">Qoralamalar</x-dropdown-item>
                    <x-dropdown-item icon="settings" :href="route('settings.profile')">Sozlamalar</x-dropdown-item>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-dropdown-item icon="logout" type="submit">Chiqish</x-dropdown-item>
                    </form>
                </x-dropdown>
            </div>
        @else
            <div class="mt-6 flex flex-col gap-2 px-1">
                <a href="{{ route('register') }}" class="btn btn-primary btn-lg">Ro‘yxatdan o‘tish</a>
                <a href="{{ route('login') }}" class="btn btn-secondary btn-lg">Kirish</a>
            </div>
        @endauth
    </aside>

    {{-- Asosiy ustun --}}
    <main id="main" class="min-h-screen w-full max-w-[620px] border-line bg-surface pb-24 lg:border-x lg:pb-10">
        @yield('content')
    </main>

    {{-- O‘ng panel (keng ekran) --}}
    <aside class="sticky top-0 hidden h-screen w-80 shrink-0 overflow-y-auto py-5 xl:block" aria-label="Qo‘shimcha">
        @hasSection('sidebar')
            @yield('sidebar')
        @else
            @include('partials.sidebar')
        @endif
    </aside>
</div>

{{-- Mobil: pastki navigatsiya --}}
<nav class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden" aria-label="Pastki menyu">
    <div class="mx-auto grid max-w-md grid-cols-5 items-center">
        <a href="{{ route('home') }}" class="flex justify-center py-3 {{ request()->routeIs('home') ? 'text-ink' : 'text-muted' }}" aria-label="Bosh sahifa"><x-ico name="home" size="size-6" :solid="request()->routeIs('home')" /></a>
        <a href="{{ route('search') }}" class="flex justify-center py-3 {{ request()->routeIs('search') ? 'text-ink' : 'text-muted' }}" aria-label="Qidiruv"><x-ico name="search" size="size-6" /></a>
        <a href="{{ auth()->check() ? route('posts.create') : route('login') }}" class="mx-auto flex size-12 items-center justify-center rounded-full bg-lapis text-white shadow-md" aria-label="Fikr yozish"><x-ico name="plus" size="size-6" /></a>
        @auth
            <a href="{{ route('notifications.index') }}" class="relative flex justify-center py-3 {{ request()->routeIs('notifications.*') ? 'text-ink' : 'text-muted' }}" aria-label="Bildirishnomalar" x-data="unreadBadge({{ $unreadNotifications }})">
                <x-ico name="bell" size="size-6" />
                <span x-show="count > 0" x-cloak class="absolute right-[30%] top-2.5 size-2.5 rounded-full bg-anor ring-2 ring-surface"></span>
            </a>
            <a href="{{ route('profile.show', $me->username) }}" class="flex justify-center py-3" aria-label="Profil"><x-avatar :user="$me" size="xs" /></a>
        @else
            <a href="{{ route('categories.index') }}" class="flex justify-center py-3 text-muted" aria-label="Mavzular"><x-ico name="grid" size="size-6" /></a>
            <a href="{{ route('login') }}" class="flex justify-center py-3 text-muted" aria-label="Kirish"><x-ico name="user" size="size-6" /></a>
        @endauth
    </div>
</nav>

@include('partials.report-modal')
<x-toasts />
</body>
</html>
