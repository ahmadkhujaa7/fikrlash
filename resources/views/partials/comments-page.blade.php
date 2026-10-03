<div x-data="infinite('{{ $comments->hasMorePages() ? $comments->nextPageUrl() : '' }}')">
    <div x-ref="list" data-page data-next="{{ $comments->hasMorePages() ? $comments->nextPageUrl() : '' }}" class="stream">
        @foreach ($comments as $comment)
            @include('partials.comment', ['comment' => $comment])
        @endforeach
    </div>
    @if ($comments->isEmpty() && ! request('cursor'))
        <x-empty-state icon="chat" title="Hali izoh yo‘q" text="Fikringizni birinchi bo‘lib bildiring." />
    @endif
    <div x-show="next" x-cloak class="flex justify-center py-4">
        <button type="button" class="btn btn-secondary btn-sm" @click="load()" :disabled="loading">
            <span x-show="!loading">Yana izohlar</span><x-spinner x-show="loading" class="size-4" />
        </button>
    </div>
</div>
