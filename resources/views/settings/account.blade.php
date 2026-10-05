@extends('settings.layout')
@section('title', 'Akkaunt')

@section('settings')
    {{-- Shaxsiy xabarlar: kim yoza oladi --}}
    <section>
        <h2 class="font-semibold">Shaxsiy xabarlar</h2>
        <p class="mt-1 text-sm text-ink-soft">Kim sizga birinchi bo‘lib yoza oladi. Siz yozgan odam har doim javob bera oladi.</p>
        <form method="POST" action="{{ route('settings.messaging') }}" class="mt-4 space-y-2" x-data @change="$el.requestSubmit()">
            @csrf @method('PUT')
            @foreach (\App\Enums\MessagePrivacy::cases() as $option)
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-line px-4 py-3 text-sm transition-colors has-[:checked]:border-lapis has-[:checked]:bg-lapis-soft/50">
                    <input type="radio" name="messages_from" value="{{ $option->value }}" @checked(auth()->user()->messages_from === $option) class="accent-[var(--lapis)]">
                    {{ $option->getLabel() }}
                </label>
            @endforeach
            <noscript><button type="submit" class="btn btn-secondary btn-sm mt-2">Saqlash</button></noscript>
        </form>
    </section>

    <section>
        <h2 class="font-semibold">Ma’lumotlarimni yuklab olish</h2>
        <p class="mt-1 text-sm text-ink-soft">Profil, postlar, izohlar va obunalaringiz JSON faylda.</p>
        <a href="{{ route('settings.export') }}" class="btn btn-secondary btn-sm mt-3"><x-ico name="download" size="size-4" /> Yuklab olish</a>
    </section>

    <section x-data="{ open: false }">
        <h2 class="font-semibold">Vaqtincha o‘chirish</h2>
        <p class="mt-1 text-sm text-ink-soft">Profilingiz va postlaringiz yashiriladi. Qayta kirsangiz — hammasi tiklanadi.</p>
        <button type="button" class="btn btn-secondary btn-sm mt-3" @click="open = true">Akkauntni o‘chirib qo‘yish</button>
        <x-modal title="Akkauntni vaqtincha o‘chirish">
            <form method="POST" action="{{ route('settings.deactivate') }}" class="space-y-4">
                @csrf
                <x-input name="password" label="Tasdiqlash uchun parol" type="password" autocomplete="current-password" required />
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-secondary" @click="open = false">Bekor qilish</button>
                    <button type="submit" class="btn btn-primary">O‘chirib qo‘yish</button>
                </div>
            </form>
        </x-modal>
    </section>

    <section x-data="{ open: {{ $errors->has('confirm') || $errors->has('password') ? 'true' : 'false' }} }" class="rounded-2xl border border-anor/40 p-5">
        <h2 class="font-semibold text-anor">Akkauntni butunlay o‘chirish</h2>
        <p class="mt-1 text-sm text-ink-soft">Postlar, izohlar va obunalar darhol yashiriladi va {{ $graceDays }} kundan keyin qaytarib bo‘lmaydigan tarzda o‘chiriladi.</p>
        <button type="button" class="btn btn-danger btn-sm mt-3" @click="open = true">Akkauntni o‘chirish</button>
        <x-modal title="Akkauntni o‘chirish">
            <form method="POST" action="{{ route('settings.delete') }}" class="space-y-4">
                @csrf @method('DELETE')
                <x-input name="confirm" label="Tasdiqlash uchun username'ingizni yozing: {{ auth()->user()->username }}" autocomplete="off" required />
                <x-input name="password" label="Parol" type="password" autocomplete="current-password" required />
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-secondary" @click="open = false">Bekor qilish</button>
                    <button type="submit" class="btn btn-danger">Butunlay o‘chirish</button>
                </div>
            </form>
        </x-modal>
    </section>
@endsection
