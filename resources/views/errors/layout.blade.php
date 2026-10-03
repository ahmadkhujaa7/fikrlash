<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — Fikrlash.uz</title>
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=literata:600|onest:400,500,600&display=swap">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite('resources/css/app.css')
    @endif
</head>
<body>
<main class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-6 py-16">
    <a href="/" class="mb-10" aria-label="Bosh sahifa"><x-logo /></a>
    <p class="font-serif text-6xl font-semibold text-lapis">@yield('code')</p>
    <h1 class="mt-4 font-serif text-2xl font-semibold">@yield('title')</h1>
    <p class="mt-2 text-ink-soft">@yield('message')</p>
    <div class="mt-8 flex gap-2">
        <a href="/" class="btn btn-primary">Bosh sahifaga</a>
        @yield('extra')
    </div>
</main>
</body>
</html>
