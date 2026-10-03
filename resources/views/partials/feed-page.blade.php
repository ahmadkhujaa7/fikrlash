{{-- Infinite scroll uchun sahifa bo‘lagi: [data-page] ichidagi [data-item] lar ro‘yxatga qo‘shiladi. --}}
<div data-page data-next="{{ $posts->hasMorePages() ? $posts->nextPageUrl() : '' }}" class="stream">
    @foreach ($posts as $post)
        @include('partials.post-card', ['post' => $post])
    @endforeach
</div>
