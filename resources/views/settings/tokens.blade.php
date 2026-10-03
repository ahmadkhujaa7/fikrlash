@extends('settings.layout')
@section('title', 'API tokenlar')

@section('settings')
    <section>
        <h2 class="font-semibold">API tokenlar</h2>
        <p class="mt-1 text-sm text-ink-soft">Mobil ilova yoki skriptlar uchun. So‘rovlarda <code class="rounded bg-sunken px-1.5 py-0.5 text-xs">Authorization: Bearer TOKEN</code> sarlavhasidan foydalaning.
            Hujjatlar: <a href="{{ route('docs.api') }}" class="text-lapis hover:underline">API qo‘llanma</a>.</p>

        @if ($newToken)
            <div class="mt-4 rounded-2xl border border-firuza bg-firuza-soft p-4" x-data="{ copied: false }">
                <p class="text-sm font-semibold">Token yaratildi. Uni hozir nusxalab oling — keyin qayta ko‘rsatilmaydi.</p>
                <div class="mt-2 flex gap-2">
                    <input type="text" readonly value="{{ $newToken }}" class="field font-mono text-xs" x-ref="t" @focus="$el.select()">
                    <button type="button" class="btn btn-primary btn-sm" @click="navigator.clipboard.writeText($refs.t.value); copied = true" x-text="copied ? 'Nusxalandi' : 'Nusxalash'"></button>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('settings.tokens.store') }}" class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end">
            @csrf
            <x-input name="name" label="Token nomi" placeholder="Masalan: Telegram bot" class="flex-1" required maxlength="60" />
            <x-select name="expires_in_days" label="Muddati" :options="['30' => '30 kun', '90' => '90 kun', '365' => '1 yil', '7' => '7 kun']" value="90" />
            <button type="submit" class="btn btn-primary">Yaratish</button>
        </form>
    </section>

    <ul class="stream rounded-2xl border border-line">
        @forelse ($tokens as $token)
            <li class="flex items-center gap-4 px-4 py-3">
                <x-ico name="key" class="text-muted" />
                <div class="min-w-0 flex-1">
                    <p class="truncate font-medium">{{ $token->name }}</p>
                    <p class="text-sm text-muted">
                        {{ $token->last_used_at ? 'Oxirgi marta: '.\App\Support\Time::short($token->last_used_at) : 'Hali ishlatilmagan' }},
                        {{ $token->expires_at ? 'muddati: '.\App\Support\Time::date($token->expires_at) : 'muddatsiz' }}
                    </p>
                </div>
                <x-confirm-modal :action="route('settings.tokens.destroy', $token->id)" title="Tokenni bekor qilasizmi?" text="Bu token bilan ishlayotgan ilovalar darhol kirish huquqini yo‘qotadi." confirm="Bekor qilish">
                    <x-slot:trigger><button type="button" class="btn btn-ghost btn-sm text-anor">Bekor qilish</button></x-slot:trigger>
                </x-confirm-modal>
            </li>
        @empty
            <li><x-empty-state icon="key" title="Token yo‘q" class="!py-10" /></li>
        @endforelse
    </ul>
@endsection
