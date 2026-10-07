@extends('settings.layout')
@section('title', 'Profil sozlamalari')

@section('settings')
    <section>
        <h2 class="mb-4 font-semibold">Profil rasmi</h2>
        <div class="flex items-center gap-4">
            <x-avatar :user="$user" size="lg" />
            <form method="POST" action="{{ route('settings.avatar') }}" enctype="multipart/form-data" x-data>
                @csrf
                <label class="btn btn-secondary btn-sm cursor-pointer">
                    Rasm yuklash
                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="$el.form.submit()">
                </label>
            </form>
            @if ($user->avatar_path)
                <form method="POST" action="{{ route('settings.avatar.destroy') }}" data-confirm="Profil rasmi olib tashlansinmi?" data-confirm-ok="Olib tashlash">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-ghost btn-sm">Olib tashlash</button>
                </form>
            @endif
        </div>
        @error('avatar')<p class="field-error">{{ $message }}</p>@enderror
        <p class="field-hint">JPG, PNG yoki WEBP, 5 MB gacha. Rasm metadata (joylashuv va h.k.) avtomatik o‘chiriladi.</p>
    </section>

    <form method="POST" action="{{ route('settings.profile.update') }}" class="space-y-4">
        @csrf @method('PUT')
        <h2 class="font-semibold">Ma’lumotlar</h2>
        <x-input name="name" label="Ism" :value="$user->name" required maxlength="100" />
        <x-input name="username" label="Username" :value="$user->username" required maxlength="30" hint="Profil manzili o‘zgaradi: fikrlash.uz/@username" />
        <x-textarea name="bio" label="O‘zingiz haqingizda" :value="$user->bio" rows="3" maxlength="300" />
        <x-input name="email" label="Email (ixtiyoriy)" type="email" :value="$user->email" autocomplete="email" />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-select name="gender" label="Jins (ixtiyoriy)" :options="$genders" :value="$user->gender?->value" placeholder="Ko‘rsatilmagan" />
            <x-input name="birth_date" label="Tug‘ilgan sana (ixtiyoriy)" type="date" :value="$user->birth_date?->toDateString()" />
        </div>
        <p class="text-sm text-muted">Jins va tug‘ilgan sana boshqalarga ko‘rsatilmaydi.</p>
        <button type="submit" class="btn btn-primary">Saqlash</button>
    </form>
@endsection
