@php
    $me = auth()->user();
    $announcement = \App\Models\Setting::read('announcement');
    // Yozish sahifalarida mobil pastki menyu yashiriladi — klaviatura va asboblar paneli uchun joy.
    $writing = request()->routeIs('posts.create', 'posts.edit');
    // Suhbat oynasi: o‘z balandligi bor (mobilda butun ekran), pastki menyu yashiriladi.
    $chatScreen = request()->routeIs('messages.show');
    // "Ekran" sahifalari (post, yozish) o‘z ilova paneliga ega — mobilda umumiy sarlavha o‘rniga u chiqadi.
    $screen = View::hasSection('screen');
    $tabs = [
        ['Lenta', 'home', route('home'), request()->routeIs('home')],
        ['Qidiruv', 'search', route('search'), request()->routeIs('search')],
    ];
@endphp
<!DOCTYPE html>
<html lang="uz">
<head>
    @include('layouts.head')
</head>
<body @auth data-auth="1" data-unread-notifications="{{ $unreadNotifications }}" data-unread-messages="{{ $unreadMessages }}" @endauth @if (session('toast')) data-toast="{{ session('toast') }}" @endif
      x-data="reporter" @report.window="show($event.detail)">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[70] focus:rounded-full focus:bg-surface focus:px-4 focus:py-2">Asosiy qismga o‘tish</a>

@if ($announcement)
    <div class="border-b border-line bg-lapis-soft/70 px-4 py-2.5 text-center text-[13px] text-ink-soft">{{ $announcement }}</div>
@endif

<header class="app-header {{ $screen ? 'hidden md:block' : '' }}" data-app-header>
    <div class="mx-auto flex h-14 max-w-[1080px] items-center gap-6 px-4 sm:px-6 md:h-16 xl:max-w-[1280px]">
        <a href="{{ route('home') }}" data-home-link data-tab aria-label="{{ \App\Support\Branding::name() }} — bosh sahifa"><x-logo /></a>

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
                     class="absolute inset-x-0 top-11 z-40 overflow-hidden rounded-2xl border border-line bg-surface shadow-[var(--shadow-pop)]"
                     x-html="html"></div>
            </form>
        @endunless

        <div class="{{ request()->routeIs('search') ? 'ml-auto' : 'ml-auto md:ml-0' }} flex items-center gap-1.5">
            @auth
                <a href="{{ route('posts.create') }}" class="btn btn-primary btn-sm mr-1 hidden md:inline-flex xl:hidden"><x-ico name="pencil" size="size-4" /> Yozish</a>
                {{-- Bildirishnomalar: mobil va planshetda sarlavhada (kompyuterda — chap menyuda) --}}
                <a href="{{ route('notifications.index') }}" class="icon-btn relative xl:hidden {{ request()->routeIs('notifications.*') ? '!text-lapis' : '' }}" aria-label="Bildirishnomalar"
                   x-data="unreadBadge('notifications')">
                    <x-ico name="bell" :solid="request()->routeIs('notifications.*')" />
                    <span x-show="count > 0" x-cloak class="absolute right-0.5 top-0.5 grid h-[18px] min-w-[18px] place-items-center rounded-full bg-anor px-1 text-[10px] font-semibold leading-none text-white ring-2 ring-paper" x-text="count > 99 ? '99+' : count"></span>
                </a>
                {{-- Xabarlar: planshetda sarlavhada (mobilda — pastki menyuda, kompyuterda — chap menyuda) --}}
                <a href="{{ route('messages.index') }}" class="icon-btn relative hidden md:grid xl:hidden" aria-label="Xabarlar" x-data="unreadBadge('messages')">
                    <x-ico name="chat" />
                    <span x-show="count > 0" x-cloak class="absolute right-0.5 top-0.5 grid h-[18px] min-w-[18px] place-items-center rounded-full bg-lapis px-1 text-[10px] font-semibold leading-none text-white ring-2 ring-paper" x-text="count > 99 ? '99+' : count"></span>
                </a>
                {{-- Akkaunt menyusi: mobilda yo‘q (profil — pastki menyuda, qolgan bo‘limlar — profil sahifasidagi menyuda) --}}
                <div class="hidden md:block">
                <x-dropdown label="Akkaunt menyusi">
                    <x-slot:trigger class="!p-1"><x-avatar :user="$me" size="xs" /></x-slot:trigger>
                    <div class="border-b border-line px-4 pb-3 pt-2">
                        <p class="flex items-center gap-1 text-sm font-medium text-ink"><span class="truncate">{{ $me->name }}</span><x-verified :user="$me" size="xs" /></p>
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
                </div>
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

    <main id="main" class="mx-auto min-w-0 max-w-[680px] {{ $chatScreen ? 'pb-0 md:pb-4' : ($writing ? 'pb-0 md:pb-16' : 'pb-[calc(5.5rem+env(safe-area-inset-bottom))] md:pb-16') }} sm:pt-3 lg:mx-0 lg:max-w-none">
        <div class="sheet {{ $chatScreen ? '' : (($screen ? 'min-h-[100dvh]' : 'min-h-[calc(100dvh-3.5rem)]').' sm:min-h-[calc(100vh-7rem)]') }} {{ $writing || $chatScreen ? '' : 'pb-4' }}">
            @yield('content')
        </div>
    </main>

    <aside class="hidden lg:block" aria-label="Qo‘shimcha">
        <div class="sticky top-16 max-h-[calc(100vh-4rem)] overflow-y-auto pb-10 pt-3 [scrollbar-width:none]">
            @hasSection('sidebar')
                @yield('sidebar')
            @else
                @include('partials.sidebar')
            @endif
        </div>
    </aside>
</div>

{{-- Mobil: pastki menyu (ilovadagidek — belgi va yozuv; o‘rtada yozish tugmasi) --}}
@unless ($writing || $chatScreen)
<nav class="tab-bar" aria-label="Pastki menyu">
    <div class="mx-auto grid max-w-md grid-cols-5 items-center px-1">
        @foreach ($tabs as [$label, $icon, $href, $active])
            <a href="{{ $href }}" data-tab @if ($icon === 'home') data-home-link @endif class="tab-item" @if ($active) aria-current="page" @endif>
                <x-ico :name="$active && $icon === 'home' ? 'home-solid' : $icon" size="size-6" :solid="$active && $icon === 'home'" :stroke-width="$active ? 2 : 1.6" />
                {{ $label }}
            </a>
        @endforeach
        <a href="{{ $me ? route('posts.create') : route('login') }}" data-tab class="flex justify-center py-1.5" aria-label="Yozish">
            <span class="tab-compose"><x-ico name="plus" size="size-6" stroke-width="2.2" /></span>
        </a>
        @auth
            <a href="{{ route('messages.index') }}" data-tab class="tab-item" @if (request()->routeIs('messages.*')) aria-current="page" @endif x-data="unreadBadge('messages')">
                <span class="relative">
                    <x-ico name="chat" size="size-6" :solid="request()->routeIs('messages.*')" />
                    <span x-show="count > 0" x-cloak class="absolute -right-1.5 -top-1 grid min-w-[17px] place-items-center rounded-full bg-lapis px-1 text-[10px] font-semibold leading-[17px] text-white ring-2 ring-paper" x-text="count > 99 ? '99+' : count"></span>
                </span>
                Xabarlar
            </a>
            <a href="{{ route('profile.show', $me->username) }}" data-tab class="tab-item" @if (request()->is('@'.$me->username, '@'.$me->username.'/*')) aria-current="page" @endif>
                <span class="rounded-full ring-2 {{ request()->is('@'.$me->username, '@'.$me->username.'/*') ? 'ring-lapis' : 'ring-transparent' }}"><x-avatar :user="$me" size="xs" /></span>
                Profil
            </a>
        @else
            <a href="{{ route('login') }}" data-tab class="tab-item"><x-ico name="chat" size="size-6" />Xabarlar</a>
            <a href="{{ route('login') }}" data-tab class="tab-item"><x-ico name="user" size="size-6" />Kirish</a>
        @endauth
    </div>
</nav>
@endunless

@include('partials.report-modal')
@if (\App\Services\Security\ImpersonationService::active() && $me)
    {{-- Admin foydalanuvchi nomidan ko‘ryapti — doim ko‘rinib turadi --}}
    <form method="POST" action="{{ route('impersonate.stop') }}"
          class="fixed inset-x-0 bottom-[calc(5.25rem+env(safe-area-inset-bottom))] z-[55] mx-auto flex w-fit max-w-[calc(100%-2rem)] items-center gap-3 rounded-full bg-amber py-1.5 pl-4 pr-1.5 text-[13px] text-white shadow-[0_12px_40px_-12px_rgb(0_0_0/0.45)] md:bottom-6">
        @csrf
        <x-ico name="eye" size="size-4" />
        <span class="truncate">Admin rejimi: <strong class="font-semibold">{{ '@'.$me->username }}</strong> nomidan ko‘ryapsiz</span>
        <button type="submit" class="shrink-0 rounded-full bg-white/20 px-3 py-1 font-medium hover:bg-white/30">Admin’ga qaytish</button>
    </form>
@endif
<x-toasts />
</body>
</html>
