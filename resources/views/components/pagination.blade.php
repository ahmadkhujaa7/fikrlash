{{-- Oddiy "oldingi/keyingi" sahifalash (length-aware va simple paginatorlar uchun). --}}
@if ($paginator->hasPages())
    <nav class="flex items-center justify-between gap-3 px-4 py-5 sm:px-6" aria-label="Sahifalar">
        @if ($paginator->onFirstPage())
            <span class="btn btn-secondary btn-sm opacity-40" aria-disabled="true">Oldingi</span>
        @else
            <a class="btn btn-secondary btn-sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">Oldingi</a>
        @endif
        @if (method_exists($paginator, 'currentPage'))
            <span class="text-sm text-muted">{{ $paginator->currentPage() }}-sahifa</span>
        @endif
        @if ($paginator->hasMorePages())
            <a class="btn btn-secondary btn-sm" href="{{ $paginator->nextPageUrl() }}" rel="next">Keyingi</a>
        @else
            <span class="btn btn-secondary btn-sm opacity-40" aria-disabled="true">Keyingi</span>
        @endif
    </nav>
@endif
