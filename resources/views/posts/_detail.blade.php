{{--
  Post sahifasining asosiy qismi: post (yoki maqola), izohlar va o‘xshash fikrlar.
  O‘qish vaqti shu yerda o‘lchanadi (sahifadan chiqilganda yuboriladi).
--}}
<div @auth @if ($post->isPublished()) x-data="readTimer('{{ route('api.v1.posts.read', $post) }}')" @endif @endauth>

    @include('partials.post-card', ['post' => $post, 'detail' => true])

    @if ($post->isPublished())
        <section id="comments" class="pt-6" x-data="comments({ url: '{{ route('posts.comments', $post) }}' })">
            <h2 class="px-4 font-serif text-2xl font-medium tracking-[-0.01em] sm:px-6">Izohlar</h2>
            @auth
                @can('interact', $post)
                    <form method="POST" action="{{ route('comments.store', $post) }}" @submit.prevent="submit($el)" class="mt-4 flex gap-3 border-b border-line px-4 pb-5 sm:px-6">
                        @csrf
                        <input type="hidden" name="parent_id" :value="replyTo">
                        <x-avatar :user="auth()->user()" size="sm" />
                        <div class="min-w-0 flex-1">
                            <p x-show="replyTo" x-cloak class="mb-1 flex items-center gap-2 text-[13px] text-muted">
                                <span><span x-text="'@' + replyName"></span> ga javob</span>
                                <button type="button" class="text-ink underline underline-offset-4" @click="cancelReply">bekor qilish</button>
                            </p>
                            <textarea id="comment-input-{{ $post->id }}" name="content" x-model="content" x-ref="input" rows="1" maxlength="{{ config('fikrlash.comments.max_length') }}"
                                      @input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
                                      @keydown.ctrl.enter="submit($el.form)" @keydown.meta.enter="submit($el.form)"
                                      placeholder="Fikringizni qo‘shing…" aria-label="Izoh matni"
                                      class="block w-full resize-none border-0 bg-transparent px-0 py-2 text-[15px] leading-relaxed focus:outline-none focus:ring-0"></textarea>
                            <div class="flex justify-end" x-show="content.trim().length > 0" x-cloak>
                                <button type="submit" class="btn btn-primary btn-sm" :disabled="sending">Yuborish</button>
                            </div>
                        </div>
                    </form>
                @endcan
            @else
                <p class="mt-3 border-b border-line px-4 pb-5 text-[15px] text-muted sm:px-6">
                    Izoh qoldirish uchun <a href="{{ route('login') }}" class="text-ink underline underline-offset-4">kiring</a>
                    yoki <a href="{{ route('register') }}" class="text-ink underline underline-offset-4">ro‘yxatdan o‘ting</a>.
                </p>
            @endauth

            <div x-ref="thread">
                <div class="flex justify-center py-10"><x-spinner /></div>
            </div>
            <noscript><p class="px-4 sm:px-6 py-4 text-sm text-muted">Izohlarni ko‘rish uchun JavaScript yoqing.</p></noscript>
        </section>
    @endif

    @if ($related->isNotEmpty())
        <section class="mt-10 border-t border-line pt-10">
            <h2 class="px-4 font-serif text-2xl font-medium tracking-[-0.01em] sm:px-6">O‘xshash fikrlar</h2>
            <div class="stream">
                @foreach ($related as $item)
                    @include('partials.post-card', ['post' => $item])
                @endforeach
            </div>
        </section>
    @endif
</div>
