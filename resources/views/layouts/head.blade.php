<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#14151b" media="(prefers-color-scheme: dark)">
{{-- Ilova sifatida o‘rnatish (PWA): bosh ekranga qo‘shilganda brauzer panelisiz ochiladi --}}
<link rel="manifest" href="{{ route('manifest') }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ \App\Support\Branding::name() }}">
@php
    $siteName = \App\Support\Branding::name();
    $favicon = \App\Support\Branding::faviconUrl();
    $pageTitle = trim($__env->yieldContent('title'));
    $fullTitle = $pageTitle ? $pageTitle.' — '.$siteName : $siteName.' — fikrlar, g‘oyalar va savollar';
    $description = trim($__env->yieldContent('description')) ?: $siteName.' — o‘zbek tilidagi fikr almashish platformasi. O‘z fikringizni yozing, qiziqarli g‘oyalarni o‘qing va muhokama qiling.';
@endphp
<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ $description }}">
<link rel="canonical" href="{{ trim($__env->yieldContent('canonical')) ?: url()->current() }}">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:locale" content="uz_UZ">
<meta property="og:type" content="@yield('og_type', 'website')">
<meta property="og:title" content="{{ $pageTitle ?: $siteName }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ url()->current() }}">
@hasSection('og_image')<meta property="og:image" content="@yield('og_image')">@endif
<meta name="twitter:card" content="{{ View::hasSection('og_image') ? 'summary_large_image' : 'summary' }}">
@stack('meta')
@if ($favicon)
    <link rel="icon" href="{{ $favicon }}">
@else
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/icons/icon-192.png" type="image/png" sizes="192x192">
@endif
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.bunny.net">
<link rel="stylesheet" href="https://fonts.bunny.net/css?family=newsreader:400,400i,500,500i,600|onest:400,500,600&display=swap">
@vite(['resources/css/app.css', 'resources/js/app.js'])
{{-- Sahifa o‘tishi yo‘nalishi (View Transitions): birinchi chizishdan oldin aniqlanishi kerak, shuning uchun shu yerda.
     forward — postga kirish, back — orqaga, fade — menyu bo‘limlari. Niyat — resources/js/navigation.js da yoziladi. --}}
<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
(() => {
    let cached = false;
    addEventListener('pageshow', (e) => { cached = e.persisted; });
    addEventListener('pagereveal', (e) => {
        if (!e.viewTransition) return;
        let intent = null;
        try { intent = JSON.parse(sessionStorage.getItem('fikrlash:nav')); sessionStorage.removeItem('fikrlash:nav'); } catch (_) {}
        const a = window.navigation && navigation.activation;
        const nav = performance.getEntriesByType('navigation')[0];
        if (matchMedia('(prefers-reduced-motion: reduce)').matches || (a ? a.navigationType === 'reload' : nav && nav.type === 'reload')) {
            e.viewTransition.skipTransition();
            return;
        }
        let type = 'fade';
        if (a && a.navigationType === 'traverse') type = a.from && a.entry.index < a.from.index ? 'back' : 'forward';
        else if (!a && (cached || (nav && nav.type === 'back_forward'))) type = 'back';
        else if (intent && Date.now() - intent.at < 10000) type = intent.type;
        e.viewTransition.types.add(type);
    });
})();
</script>
@include('partials.tracking')
