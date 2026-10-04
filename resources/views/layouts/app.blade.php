@php
    $me = auth()->user();
    $announcement = \App\Models\Setting::read('announcement');
    // Yozish sahifalarida mobil pastki menyu yashiriladi — klaviatura va asboblar paneli uchun joy.
    $writing = request()->routeIs('posts.create', 'posts.edit');
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
    <div class="mx-auto flex h-16 max-w-[1080px] items-center gap-6 px-4 sm:px-6 xl:max-w-[1280px]">
        <a href="{{ route('home') }}" data-home-link aria-label="{{ \App\Support\Branding::name() }} — bosh sahifa"><x-logo /></a>

        @unless (request()->routeIs('search'))
            {{-- Tezkor qidiruv: yozish bilan takliflar chiqadi; Enter — to‘liq natijalar sahifasi --}}
            <form action="{{ route('search') }}" method="GET" role="search" class="relative ml-auto hidden w-full max-w-[300px] md:block"
                  x-data="quickSearch('{{ route('search.suggest') }}')" @click.outside="open = false" @keydown.escape.window="close()"
                  @keydown.window.slash="if (! ['INPUT','TEXTAREA','SELECT'].includes($event.target.tagName) && ! $event.target.isContentEditable) { $event.preventDefault(); $refs.q.focus() }">
                <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-muted">
                    <x-ico name="search" size="size-4" x-show="!loading" />
                    <x-spinner class="!size-4" x-show="loading" x-cloak />
                </span>
                <input type="search" name="q" x-ref="q" x-model="q" placeholder="Qidirish" aria-label="Qidirish" autocomplete="off"
                       role="combobox" aria-autocomplete="list" aria-controls="quick-search-panel" :aria-expanded="open"
                       @input.debounce.200ms="suggest()" @focus="q.trim().length >= 2 && (open = true)" @keydown.down.prevent="move(1)"
                       class="h-9 w-full rounded-full border border-transparent bg-ink/[0.05] pl-10 pr-9 text-sm text-ink placeholder:text-muted transition-colors focus:border-line-strong focus:bg-paper focus:outline-none">
                <kbd x-show="!q" class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 rounded border border-line-strong px-1.5 font-sans text-[11px] leading-4 text-muted">/</kbd>
                <div id="quick-search-panel" x-show="open && html" x-cloak x-transition.opacity.duration.100ms
                     @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)"
                     class="absolute inset-x-0 top-11 z-40 overflow-hidden rounded-2xl border border-line bg-surface shadow-[0_16px_48px_-16px_rgb(0_0_0/0.3)]"
                     x-html="html"></div>
            </form>
        @endunless

        <div class="{{ request()->routeIs('search') ? 'ml-auto' : 'ml-auto md:ml-0' }} flex items-center gap-1.5">
            @auth
                <a href="{{ route('posts.create') }}" class="btn btn-primary btn-sm mr-1 hidden md:inline-flex xl:hidden"><x-ico name="pencil" size="size-4" /> Yozish</a>
                <a href="{{ route('notifications.index') }}" class="relative hidden rounded-full p-2 text-muted hover:text-ink md:block xl:hidden" aria-label="Bildirishnomalar"
                   x-data="unreadBadge({{ $unreadNotifications }})">
                    <x-ico name="bell" />
                    <span x-show="count > 0" x-cloak class="absolute right-1.5 top-1.5 size-2 rounded-full bg-lapis ring-2 ring-paper"></span>
                </a>
                <x-dropdown label="Akkaunt menyusi">
                    <x-slot:trigger class="!p-1"><x-avatar :user="$me" size="xs" /></x-slot:trigger>
                    <div class="border-b border-line px-4 pb-3 pt-2">
                        <p class="flex items-center gap-1 text-sm font-medium text-ink"><span class="truncate">{{ $me->name }}</span><x-verified :user="$me" size="xs" /></p>
                        <p class="truncate text-[13px] text-muted">{{ '@'.$me->username }}</p>
                    </div>
                    <div class="py-1">
                        <x-dropdown-item icon="user" :href="route('profile.show', $me->username)">Profil</x-dropdown-item>
                        <x-dropdown-item icon="bookmark" :href="route('saved.index')">Saqlanganlar</x-dropdown-item>
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

<div class="mx-auto max-w-[1080px] sm:px-4 lg:grid lg:grid-cols-[minmax(0,660px)_1fr] lg:gap-10 lg:px-6 xl:max-w-[1280px] xl:grid-cols-[200px_minmax(0,640px)_minmax(0,1fr)] xl:gap-8">
    {{-- Keng ekranda: chap navigatsiya ustuni --}}
    <aside class="hidden xl:block" aria-label="Navigatsiya">
        <div class="sticky top-16 pt-2">
            @include('partials.nav-rail')
        </div>
    </aside>

    <main id="main" class="mx-auto min-w-0 max-w-[680px] {{ $writing ? 'pb-0 sm:pb-16' : 'pb-28' }} sm:pt-2 lg:mx-0 lg:max-w-none lg:pb-16">
        <div class="sheet min-h-[calc(100vh-7rem)] pb-4">
            @yield('content')
        </div>
    </main>

    <aside class="hidden lg:block" aria-label="Qo‘shimcha">
        <div class="sticky top-16 max-h-[calc(100vh-4rem)] overflow-y-auto pb-10 pt-2 [scrollbar-width:none]">
            @hasSection('sidebar')
                @yield('sidebar')
            @else
                @include('partials.sidebar')
            @endif
        </div>
    </aside>
</div>

{{-- Mobil: pastki navigatsiya --}}
@unless ($writing)
<nav class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-paper/90 pb-[env(safe-area-inset-bottom)] backdrop-blur-md md:hidden" aria-label="Pastki menyu">
    <div class="mx-auto grid max-w-md grid-cols-5 items-center">
        <a href="{{ route('home') }}" data-home-link class="flex justify-center py-3.5 {{ request()->routeIs('home') ? 'text-ink' : 'text-muted' }}" aria-label="Lenta"><x-ico name="home" size="size-6" /></a>
        <a href="{{ route('search') }}" class="flex justify-center py-3.5 {{ request()->routeIs('search') ? 'text-ink' : 'text-muted' }}" aria-label="Qidiruv"><x-ico name="search" size="size-6" /></a>
        <a href="{{ auth()->check() ? route('posts.create') : route('login') }}" class="mx-auto flex size-11 items-center justify-center rounded-full bg-ink text-on-ink" aria-label="Yozish"><x-ico name="plus" size="size-5" /></a>
        @auth
            <a href="{{ route('notifications.index') }}" class="relative flex justify-center py-3.5 {{ request()->routeIs('notifications.*') ? 'text-ink' : 'text-muted' }}" aria-label="Bildirishnomalar" x-data="unreadBadge({{ $unreadNotifications }})">
                <x-ico name="bell" size="size-6" />
                <span x-show="count > 0" x-cloak class="absolute right-[32%] top-3 size-2 rounded-full bg-lapis ring-2 ring-paper"></span>
            </a>
            <a href="{{ route('profile.show', $me->username) }}" class="flex justify-center py-3.5" aria-label="Profil"><x-avatar :user="$me" size="xs" /></a>
        @else
            <a href="{{ route('login') }}" class="flex justify-center py-3.5 text-muted" aria-label="Bildirishnomalar"><x-ico name="bell" size="size-6" /></a>
            <a href="{{ route('login') }}" class="flex justify-center py-3.5 text-muted" aria-label="Kirish"><x-ico name="user" size="size-6" /></a>
        @endauth
    </div>
</nav>
@endunless

{{-- Post oynasi: lentadan bosilgan post shu yerda ochiladi, lenta orqada o‘z joyida qoladi --}}
<div x-data="postViewer" x-show="open" x-cloak @keydown.escape.window="open && close()"
     class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label="Fikr">
    <div class="absolute inset-0 hidden bg-ink/35 backdrop-blur-[2px] sm:block" x-show="open"
         x-transition:enter="transition duration-200" x-transition:enter-start="opacity-0" x-transition:leave="transition duration-150" x-transition:leave-end="opacity-0"></div>
    <div x-ref="scroller" class="absolute inset-0 overflow-y-auto overscroll-contain" @click.self="close()">
        <div x-show="open" x-trap.noscroll="open"
             x-transition:enter="transition duration-200 ease-out motion-reduce:transition-none"
             x-transition:enter-start="translate-x-full sm:translate-x-0 sm:translate-y-4 sm:opacity-0"
             x-transition:enter-end="translate-x-0 sm:translate-y-0 sm:opacity-100"
             x-transition:leave="transition duration-150 ease-in motion-reduce:transition-none"
             x-transition:leave-start="translate-x-0 sm:translate-y-0 sm:opacity-100"
             x-transition:leave-end="translate-x-full sm:translate-x-0 sm:translate-y-4 sm:opacity-0"
             class="relative min-h-full bg-paper pb-[env(safe-area-inset-bottom)] sm:mx-auto sm:my-8 sm:min-h-0 sm:max-w-[680px] sm:rounded-[28px] sm:border sm:border-line sm:shadow-[0_24px_80px_-24px_rgb(0_0_0/0.35)]">
            <header class="sticky top-0 z-20 flex h-14 items-center gap-1 border-b border-line bg-paper/90 px-2 backdrop-blur-md sm:rounded-t-[28px] sm:px-3">
                <button type="button" x-ref="close" @click="close()" class="grid size-10 place-items-center rounded-full text-ink-soft hover:bg-sunken hover:text-ink" aria-label="Orqaga — lentaga qaytish">
                    <x-ico name="arrow-left" />
                </button>
                <p class="text-[15px] font-medium">Fikr</p>
                <span class="flex-1"></span>
                <a :href="url" class="grid size-10 place-items-center rounded-full text-muted hover:bg-sunken hover:text-ink" title="Alohida sahifada ochish" aria-label="Alohida sahifada ochish">
                    <x-ico name="expand" size="size-[18px]" />
                </a>
                <button type="button" @click="close()" class="hidden size-10 place-items-center rounded-full text-muted hover:bg-sunken hover:text-ink sm:grid" aria-label="Yopish" title="Yopish (Esc)">
                    <x-ico name="x" />
                </button>
            </header>

            {{-- Yuklanayotganda: post shaklidagi skelet --}}
            <div x-show="loading" class="animate-pulse px-4 pb-10 pt-8 sm:px-6" aria-hidden="true">
                <div class="flex items-center gap-3"><div class="size-10 rounded-full bg-sunken"></div><div class="space-y-2"><div class="h-3 w-32 rounded-full bg-sunken"></div><div class="h-3 w-20 rounded-full bg-sunken"></div></div></div>
                <div class="mt-7 space-y-3"><div class="h-4 w-full rounded-full bg-sunken"></div><div class="h-4 w-11/12 rounded-full bg-sunken"></div><div class="h-4 w-3/5 rounded-full bg-sunken"></div></div>
                <div class="mt-8 h-10 w-full rounded-xl bg-sunken"></div>
            </div>
            <div x-show="failed" x-cloak class="px-6 py-16 text-center">
                <p class="font-serif text-xl text-ink">Postni ochib bo‘lmadi</p>
                <p class="mt-2 text-sm text-muted">Internet aloqasini tekshiring yoki post o‘chirilgan bo‘lishi mumkin.</p>
                <a :href="url" class="btn btn-secondary btn-sm mt-5">Sahifada ochish</a>
            </div>
            <div x-ref="body" class="pb-6"></div>
        </div>
    </div>
</div>

@include('partials.report-modal')
@if (\App\Services\Security\ImpersonationService::active() && $me)
    {{-- Admin foydalanuvchi nomidan ko‘ryapti — doim ko‘rinib turadi --}}
    <form method="POST" action="{{ route('impersonate.stop') }}"
          class="fixed inset-x-0 bottom-20 z-[55] mx-auto flex w-fit max-w-[calc(100%-2rem)] items-center gap-3 rounded-full bg-amber py-1.5 pl-4 pr-1.5 text-[13px] text-white shadow-[0_12px_40px_-12px_rgb(0_0_0/0.45)] md:bottom-6">
        @csrf
        <x-ico name="eye" size="size-4" />
        <span class="truncate">Admin rejimi: <strong class="font-semibold">{{ '@'.$me->username }}</strong> nomidan ko‘ryapsiz</span>
        <button type="submit" class="shrink-0 rounded-full bg-white/20 px-3 py-1 font-medium hover:bg-white/30">Admin’ga qaytish</button>
    </form>
@endif
<x-toasts />
</body>
</html>
