{{--
  Marketing analitikasi (admin → Marketing → Sozlamalar): Meta Pixel, Google Analytics 4, Yandex Metrika.
  ID kiritilmagan bo‘lsa — hech narsa yuklanmaydi. Ro‘yxatdan o‘tgandan keyingi birinchi sahifada
  "ro‘yxatdan o‘tdi" hodisasi yuboriladi (reklama optimizatsiyasi uchun).
--}}
@php
    $tracking = \App\Support\TrackingIds::all();
    $signupEvent = session('marketing_event') === 'signup';
    $nonce = \Illuminate\Support\Facades\Vite::cspNonce();
@endphp
@if ($tracking['pixel'])
<script nonce="{{ $nonce }}">
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '{{ $tracking['pixel'] }}');
fbq('track', 'PageView');
@if ($signupEvent)
fbq('track', 'CompleteRegistration');
@endif
</script>
@endif
@if ($tracking['ga4'])
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $tracking['ga4'] }}" nonce="{{ $nonce }}"></script>
<script nonce="{{ $nonce }}">
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', '{{ $tracking['ga4'] }}');
@if ($signupEvent)
gtag('event', 'sign_up', { method: 'phone' });
@endif
</script>
@endif
@if ($tracking['metrika'])
<script nonce="{{ $nonce }}">
(function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};m[i].l=1*new Date();k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})(window,document,'script','https://mc.yandex.ru/metrika/tag.js','ym');
ym({{ $tracking['metrika'] }}, 'init', { clickmap: true, trackLinks: true, accurateTrackBounce: true });
@if ($signupEvent)
ym({{ $tracking['metrika'] }}, 'reachGoal', 'signup');
@endif
</script>
@endif
