{{-- Postlar oqimi + avtomatik keyingi sahifa. $empty — bo‘sh holat uchun slot o‘rnida. --}}
<div x-data="infinite('{{ $posts->hasMorePages() ? $posts->nextPageUrl() : '' }}')">
    <div x-ref="list" class="stream">
        @foreach ($posts as $post)
            @include('partials.post-card', ['post' => $post])
        @endforeach
    </div>

    @if ($posts->isEmpty())
        {{ $empty ?? '' }}
        @isset($emptyTitle)
            <x-empty-state :title="$emptyTitle" :text="$emptyText ?? null" />
        @endisset
    @endif

    <div x-show="next" x-intersect:enter.margin.600px="load()" class="flex justify-center py-8">
        <x-spinner x-show="loading" />
        <button x-show="failed" x-cloak type="button" class="btn btn-secondary btn-sm" @click="load()">Qayta yuklash</button>
    </div>
    @if ($posts->isNotEmpty() && isset($endText))
        <p x-show="!next" x-cloak class="flex items-center justify-center gap-3 px-6 py-10 text-center text-[13px] text-muted">
            <span class="h-px w-8 bg-line-strong"></span>{{ $endText }}<span class="h-px w-8 bg-line-strong"></span>
        </p>
    @endif
    <noscript>{{ $posts->links() }}</noscript>
</div>
