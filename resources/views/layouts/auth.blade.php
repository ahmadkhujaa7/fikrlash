<!DOCTYPE html>
<html lang="uz">
<head>
    @include('layouts.head')
    <meta name="robots" content="noindex">
</head>
<body @if (session('toast')) data-toast="{{ session('toast') }}" @endif>
<main id="main" class="mx-auto flex min-h-screen max-w-[420px] flex-col px-5 py-10 sm:py-16">
    <a href="{{ route('home') }}" class="mb-10 self-start" aria-label="Bosh sahifa"><x-logo /></a>
    @yield('content')
    <footer class="mt-auto flex gap-4 pt-12 text-sm text-muted">
        <a href="{{ route('about') }}" class="hover:text-ink">Loyiha haqida</a>
        <a href="{{ route('terms') }}" class="hover:text-ink">Shartlar</a>
        <a href="{{ route('privacy') }}" class="hover:text-ink">Maxfiylik</a>
    </footer>
</main>
<x-toasts />
</body>
</html>
