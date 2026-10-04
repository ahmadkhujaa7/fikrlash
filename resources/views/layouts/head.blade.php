<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#121212" media="(prefers-color-scheme: dark)">
@php
    $pageTitle = trim($__env->yieldContent('title'));
    $fullTitle = $pageTitle ? $pageTitle.' — Fikrlash.uz' : 'Fikrlash.uz — fikrlar, g‘oyalar va savollar';
    $description = trim($__env->yieldContent('description')) ?: 'Fikrlash.uz — o‘zbek tilidagi fikr almashish platformasi. O‘z fikringizni yozing, qiziqarli g‘oyalarni o‘qing va muhokama qiling.';
@endphp
<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ $description }}">
<link rel="canonical" href="{{ trim($__env->yieldContent('canonical')) ?: url()->current() }}">
<meta property="og:site_name" content="Fikrlash.uz">
<meta property="og:locale" content="uz_UZ">
<meta property="og:type" content="@yield('og_type', 'website')">
<meta property="og:title" content="{{ $pageTitle ?: 'Fikrlash.uz' }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ url()->current() }}">
@hasSection('og_image')<meta property="og:image" content="@yield('og_image')">@endif
<meta name="twitter:card" content="{{ View::hasSection('og_image') ? 'summary_large_image' : 'summary' }}">
@stack('meta')
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.bunny.net">
<link rel="stylesheet" href="https://fonts.bunny.net/css?family=newsreader:400,400i,500,500i,600|onest:400,500,600&display=swap">
@vite(['resources/css/app.css', 'resources/js/app.js'])
