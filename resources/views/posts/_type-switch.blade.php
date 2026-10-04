{{-- Yozish turi: qisqa fikr yoki maqola (sarlavha, rasmlar, bo‘limlar). --}}
<nav class="seg ml-1" aria-label="Yozish turi">
    <a href="{{ route('posts.create') }}" @if ($current === 'post') aria-current="page" @endif>Fikr</a>
    <a href="{{ route('posts.create', ['type' => 'article']) }}" @if ($current === 'article') aria-current="page" @endif>Maqola</a>
</nav>
