{{--
  Tasdiqlangan akkaunt belgisi: sakkiz qirrali yulduz (girih naqshidagi kabi) ichida belgi.
  Faqat admin beradi; foydalanuvchi ismiga ✓ yozib soxtalashtira olmaydi (ism validatsiyasi).
--}}
@props(['user', 'size' => 'sm'])
@if ($user?->isVerified())
    @php $px = ['xs' => 'size-3.5', 'sm' => 'size-4', 'md' => 'size-[18px]', 'lg' => 'size-6'][$size] ?? 'size-4'; @endphp
    <svg {{ $attributes->merge(['class' => "$px inline-block shrink-0 align-[-0.125em] text-lapis"]) }} viewBox="0 0 24 24" role="img" aria-label="Tasdiqlangan akkaunt">
        <title>Tasdiqlangan akkaunt</title>
        <g fill="currentColor" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round">
            <rect x="5.2" y="5.2" width="13.6" height="13.6" rx="1.2" />
            <rect x="5.2" y="5.2" width="13.6" height="13.6" rx="1.2" transform="rotate(45 12 12)" />
        </g>
        <path d="m8.4 12.3 2.4 2.4 4.8-5.1" fill="none" stroke="#fff" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
@endif
