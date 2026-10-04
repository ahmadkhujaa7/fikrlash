@extends('layouts.app')

@section('content')
    <article class="px-4 pb-16 pt-12 sm:px-6">
        <h1 class="display !text-[2.25rem]">@yield('title')</h1>
        <div class="mt-6 space-y-4 font-serif text-read leading-relaxed text-ink [&_h2]:mt-8 [&_h2]:font-sans [&_h2]:text-lg [&_h2]:font-semibold [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:pl-6 [&_a]:text-lapis">
            @yield('body')
        </div>
    </article>
@endsection
