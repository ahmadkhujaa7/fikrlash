@extends('layouts.auth')
@section('focus', '1')
@section('title', 'Ro‘yxatdan o‘tish')

@section('content')
    <x-auth-steps :current="1" />

    {{-- Do‘st taklifi yoki kampaniya havolasi bilan kelganlarga salomlashuv --}}
    @if ($landing['inviter'] ?? null)
        <div class="mb-6 flex items-center gap-3 rounded-2xl bg-lapis-soft px-4 py-3">
            <x-avatar :user="$landing['inviter']" size="sm" />
            <p class="min-w-0 text-[14px] leading-snug text-ink"><strong class="font-semibold">{{ $landing['inviter']->name }}</strong> sizni Fikrlash’ga taklif qildi. Ro‘yxatdan o‘ting — u bilan birga o‘qing va yozing.</p>
        </div>
    @elseif ($landing['welcome'] ?? null)
        <div class="mb-6 flex items-start gap-3 rounded-2xl bg-lapis-soft px-4 py-3">
            <x-ico name="sparkles" size="size-5" class="mt-0.5 shrink-0 text-lapis" />
            <p class="min-w-0 text-[14px] leading-snug text-ink">{{ $landing['welcome'] }}</p>
        </div>
    @endif

    <h1 class="display !text-[2rem] sm:!text-[2.25rem]">Fikrlaringiz uchun joy</h1>
    <p class="mt-2 text-ink-soft">Avval ma’lumotlaringizni kiriting. Keyingi bosqichda telefon raqamingizni SMS kod bilan tasdiqlaysiz.</p>

    @unless ($registrationOpen)
        <div class="mt-6 rounded-2xl bg-amber-soft px-4 py-3 text-sm text-ink">Ro‘yxatdan o‘tish vaqtincha yopiq. Keyinroq urinib ko‘ring.</div>
    @else
        <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-5" novalidate
              x-data="registerForm({ checkUrl: '{{ route('register.check') }}', usernameTouched: {{ old('username', $prefill['username'] ?? null) ? 'true' : 'false' }} })"
              @submit="submitting = true">
            @csrf

            {{-- Ism --}}
            <div>
                <label for="name" class="field-label">Ism va familiya</label>
                <input id="name" name="name" type="text" class="field" autocomplete="name" required maxlength="100" placeholder="Aziz Karimov"
                       value="{{ old('name', $prefill['name'] ?? '') }}" x-ref="name" @input="suggestUsername($el.value)" autofocus
                       @error('name') aria-invalid="true" @enderror>
                @error('name')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            {{-- Username: yozish bilan bandligi tekshiriladi --}}
            <div>
                <label for="username" class="field-label">Username</label>
                <div class="relative">
                    <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-[15px] text-muted">@</span>
                    <input id="username" name="username" type="text" class="field !pl-9 !pr-10" autocomplete="username" required maxlength="30"
                           autocapitalize="none" spellcheck="false" placeholder="aziz_karimov"
                           value="{{ old('username', $prefill['username'] ?? '') }}" x-ref="username"
                           @input="usernameTouched = true; $el.value = $el.value.toLowerCase().replace(/[^a-z0-9_]/g, ''); check('username', $el.value)"
                           @error('username') aria-invalid="true" @enderror>
                    <span class="absolute right-3.5 top-1/2 -translate-y-1/2" x-show="status.username" x-cloak>
                        <x-ico name="check" size="size-5" class="text-firuza" x-show="status.username === 'ok'" />
                        <x-ico name="x" size="size-5" class="text-anor" x-show="status.username === 'bad'" />
                        <x-spinner class="!size-4" x-show="status.username === 'checking'" />
                    </span>
                </div>
                @error('username')
                    <p class="field-error">{{ $message }}</p>
                @else
                    <p class="field-hint" :class="{ '!text-anor': status.username === 'bad', '!text-firuza': status.username === 'ok' }"
                       x-text="messages.username || 'Lotin harflari, raqamlar va _ belgisi.'">Lotin harflari, raqamlar va _ belgisi.</p>
                @enderror
            </div>

            {{-- Telefon: +998 prefiksi qotirilgan, raqam avtomatik formatlanadi --}}
            <div>
                <label for="phone" class="field-label">Telefon raqam</label>
                <div class="relative">
                    <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-[15px] text-ink-soft">+998</span>
                    <input id="phone" name="phone" type="tel" inputmode="numeric" class="field !pl-[4.25rem] !pr-10 tabular-nums" autocomplete="tel-national" required
                           placeholder="90 123 45 67" maxlength="12"
                           value="{{ preg_replace('/^\+?998\s?/', '', old('phone', $prefill['phone'] ?? '')) }}"
                           @input="$el.value = formatPhone($el.value); check('phone', $el.value)"
                           @error('phone') aria-invalid="true" @enderror>
                    <span class="absolute right-3.5 top-1/2 -translate-y-1/2" x-show="status.phone" x-cloak>
                        <x-ico name="check" size="size-5" class="text-firuza" x-show="status.phone === 'ok'" />
                        <x-ico name="x" size="size-5" class="text-anor" x-show="status.phone === 'bad'" />
                        <x-spinner class="!size-4" x-show="status.phone === 'checking'" />
                    </span>
                </div>
                @error('phone')
                    <p class="field-error">{{ $message }}</p>
                @else
                    <p class="field-hint" :class="{ '!text-anor': status.phone === 'bad' }" x-show="messages.phone" x-cloak>
                        <span x-text="messages.phone"></span>
                        <a href="{{ route('login') }}" class="font-medium text-lapis underline" x-show="phoneTaken">Kirish</a>
                    </p>
                @enderror
            </div>

            {{-- Parol: bitta maydon, ko‘rsatish tugmasi va kuch o‘lchagichi --}}
            <div>
                <label for="password" class="field-label">Parol</label>
                <div class="relative">
                    <input id="password" name="password" :type="showPassword ? 'text' : 'password'" type="password" class="field !pr-12"
                           autocomplete="new-password" required minlength="8" x-model="password"
                           @error('password') aria-invalid="true" @enderror>
                    <button type="button" class="absolute right-2 top-1/2 grid size-9 -translate-y-1/2 place-items-center rounded-full text-muted hover:text-ink"
                            @click="showPassword = !showPassword" :aria-label="showPassword ? 'Parolni yashirish' : 'Parolni ko‘rsatish'">
                        <x-ico name="eye" size="size-5" x-show="!showPassword" />
                        <x-ico name="eye-slash" size="size-5" x-show="showPassword" x-cloak />
                    </button>
                </div>
                <div class="mt-2.5 flex gap-1.5" aria-hidden="true">
                    <template x-for="i in 4" :key="i">
                        <span class="h-1 flex-1 rounded-full transition-colors"
                              :class="i <= strength ? ['', 'bg-anor', 'bg-amber', 'bg-firuza', 'bg-firuza'][strength] : 'bg-line'"></span>
                    </template>
                </div>
                @error('password')
                    <p class="field-error">{{ $message }}</p>
                @else
                    <p class="field-hint" x-text="strengthLabel">Kamida 8 belgi: harf va raqam bo‘lsin.</p>
                @enderror
            </div>

            <label class="flex items-start gap-3 text-sm text-ink-soft">
                <input type="checkbox" name="terms" value="1" class="mt-0.5 size-[18px] accent-[var(--lapis)]" x-model="terms" @checked(old('terms'))>
                <span><a href="{{ route('terms') }}" target="_blank" class="text-lapis hover:underline">Foydalanish shartlari</a> va
                      <a href="{{ route('privacy') }}" target="_blank" class="text-lapis hover:underline">maxfiylik siyosati</a>ga roziman.</span>
            </label>
            @error('terms')<p class="field-error !mt-0">{{ $message }}</p>@enderror

            <button type="submit" class="btn btn-primary btn-lg w-full" :disabled="submitting">
                <x-spinner class="!size-4 !text-current" x-show="submitting" x-cloak />
                Davom etish — kod olish
            </button>
        </form>
    @endunless

    <p class="mt-6 text-sm text-muted">Akkauntingiz bormi? <a href="{{ route('login') }}" class="font-medium text-lapis hover:underline">Kirish</a></p>
@endsection
