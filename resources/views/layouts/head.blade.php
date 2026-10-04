<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#f1f2f5" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0b0c0f" media="(prefers-color-scheme: dark)">
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
    <link rel="apple-touch-icon" href="{{ $favicon }}">
@else
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
@endif
<link rel="preconnect" href="https://fonts.bunny.net">
<link rel="stylesheet" href="https://fonts.bunny.net/css?family=newsreader:400,400i,500,500i,600|onest:400,500,600&display=swap">
@vite(['resources/css/app.css', 'resources/js/app.js'])
