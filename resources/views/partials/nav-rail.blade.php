@php
    $me = auth()->user();
    $items = $me ? [
        ['Lenta', 'home', route('home'), request()->routeIs('home')],
        ['Qidiruv', 'search', route('search'), request()->routeIs('search')],
        ['Xabarlar', 'chat', route('messages.index'), request()->routeIs('messages.*')],
        ['Bildirishnomalar', 'bell', route('notifications.index'), request()->routeIs('notifications.*')],
        ['Saqlanganlar', 'bookmark', route('saved.index'), request()->routeIs('saved.*')],
        ['Profil', 'user', route('profile.show', $me->username), request()->is('@'.$me->username.'*')],
        ['Sozlamalar', 'settings', route('settings.profile'), request()->routeIs('settings.*')],
    ] : [
        ['Lenta', 'home', route('home'), request()->routeIs('home')],
        ['Qidiruv', 'search', route('search'), request()->routeIs('search')],
        ['Loyiha haqida', 'info', route('about'), request()->routeIs('about')],
    ];
@endphp
<nav class="flex flex-col gap-0.5" aria-label="Asosiy menyu">
    @foreach ($items as [$label, $icon, $href, $active])
        <a href="{{ $href }}" @if ($active) aria-current="page" @endif @if ($icon === 'home') data-home-link @endif
           class="group relative flex items-center gap-3.5 rounded-full px-3.5 py-2.5 text-[15px] transition-colors {{ $active ? 'bg-paper font-medium text-ink shadow-[0_1px_2px_rgb(19_21_27/0.06)]' : 'text-ink-soft hover:bg-ink/[0.04] hover:text-ink' }}">
            <span class="relative">
                <x-ico :name="$icon" size="size-[22px]" />
                @if ($icon === 'bell')
                    <span x-data="unreadBadge('notifications')" x-show="count > 0" x-cloak
                          class="absolute -right-0.5 -top-0.5 size-2 rounded-full bg-anor ring-2 ring-canvas"></span>
                @elseif ($icon === 'chat')
                    <span x-data="unreadBadge('messages')" x-show="count > 0" x-cloak x-text="count > 99 ? '99+' : count"
                          class="absolute -right-2 -top-1.5 grid h-[17px] min-w-[17px] place-items-center rounded-full bg-lapis px-1 text-[10px] font-semibold leading-none text-white ring-2 ring-canvas"></span>
                @endif
            </span>
            {{ $label }}
        </a>
    @endforeach
</nav>

@auth
    <a href="{{ route('posts.create') }}" class="btn btn-primary btn-lg mt-6 w-full"><x-ico name="pencil" size="size-[18px]" /> Yozish</a>
@else
    <div class="mt-6 rounded-2xl border border-line bg-paper p-4">
        <p class="text-sm leading-relaxed text-ink-soft">Fikrlaringizni yozing va sizga qiziq postlarni o‘qing.</p>
        <a href="{{ route('register') }}" class="btn btn-primary mt-3 w-full">Ro‘yxatdan o‘tish</a>
    </div>
@endauth
