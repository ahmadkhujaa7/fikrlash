{{-- Ro‘yxatdan o‘tish bosqichlari: 1 — ma'lumotlar, 2 — raqamni tasdiqlash. --}}
@props(['current' => 1])
@php $steps = [1 => 'Ma’lumotlar', 2 => 'Raqamni tasdiqlash']; @endphp
<ol class="mb-8 flex items-center gap-3 text-[13px]" aria-label="Ro‘yxatdan o‘tish bosqichlari">
    @foreach ($steps as $n => $label)
        <li class="flex items-center gap-2 {{ $n === $current ? 'font-medium text-ink' : 'text-muted' }}" @if ($n === $current) aria-current="step" @endif>
            <span class="grid size-6 place-items-center rounded-full text-[12px] font-semibold
                {{ $n < $current ? 'bg-lapis text-white' : ($n === $current ? 'bg-ink text-on-ink' : 'border border-line-strong text-muted') }}">
                @if ($n < $current)
                    <x-ico name="check" size="size-3.5" />
                @else
                    {{ $n }}
                @endif
            </span>
            {{ $label }}
        </li>
        @if (! $loop->last)<li class="h-px w-6 shrink-0 bg-line-strong sm:w-10" aria-hidden="true"></li>@endif
    @endforeach
</ol>
