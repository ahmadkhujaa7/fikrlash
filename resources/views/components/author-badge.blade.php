{{--
  Muallif belgisi: admin monetizatsiyani tasdiqlagan, maqolalar yozadigan muallif.
  Tasdiqlangan belgisidan oldin turadi (x-verified ichida ham chiqadi).
--}}
@props(['user', 'size' => 'sm'])
@if ($user?->isMonetized())
    @php $px = ['xs' => 'size-3.5', 'sm' => 'size-4', 'md' => 'size-[18px]', 'lg' => 'size-6'][$size] ?? 'size-4'; @endphp
    <svg {{ $attributes->merge(['class' => "$px inline-block shrink-0 align-[-0.125em]"]) }} viewBox="0 0 24 24" role="img" aria-label="Muallif">
        <title>Muallif — maqolalari monetizatsiya qilingan</title>
        <circle cx="12" cy="12" r="11" fill="#E39A2D" />
        <path d="M12 4.6 16.3 11.4 12 19.4 7.7 11.4Z" fill="#fff" />
        <circle cx="12" cy="11.2" r="1.45" fill="#E39A2D" />
        <path d="M12 12.5v6.4" stroke="#E39A2D" stroke-width="1.1" stroke-linecap="round" />
    </svg>
@endif
