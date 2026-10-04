{{-- Lenta ustidagi oynada ochiladigan post (layout'siz bo‘lak). data-title — brauzer sarlavhasi uchun. --}}
<div data-title="{{ $post->user->name }}: “{{ $post->excerpt(60) }}” — {{ \App\Support\Branding::name() }}">
    @include('posts._detail')
</div>
