{{-- Xavfli amalni tasdiqlash: forma faqat foydalanuvchi tasdiqlagandan keyin yuboriladi. --}}
@props(['action', 'method' => 'DELETE', 'title', 'text' => null, 'confirm' => 'O‘chirish', 'trigger'])
<div x-data="{ open: false }" class="contents">
    <span @click="open = true" class="contents">{{ $trigger }}</span>
    <x-modal :title="$title">
        @if ($text)<p class="text-sm text-ink-soft">{{ $text }}</p>@endif
        <form method="POST" action="{{ $action }}" class="mt-6 flex justify-end gap-2">
            @csrf
            @method($method)
            {{ $slot }}
            <button type="button" class="btn btn-secondary" @click="open = false">Bekor qilish</button>
            <button type="submit" class="btn btn-danger">{{ $confirm }}</button>
        </form>
    </x-modal>
</div>
