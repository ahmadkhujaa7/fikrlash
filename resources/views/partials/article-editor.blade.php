{{--
  Maqola muharriri: sarlavha + bloklar (paragraf, kichik sarlavha, rasm, iqtibos, ro‘yxat, ajratgich).
  Mantiq — resources/js/app.js → articleEditor. $post — tahrirlashda.
--}}
@php
    use App\Enums\PostStatus;
    use App\Models\Post;
    use App\Support\ContentFormatter;

    $post = $post ?? null;
    $me = auth()->user();
    $isDraft = $post && $post->status === PostStatus::Draft;
    $isPublished = $post && $post->isPublished();

    // Validatsiya xatosidan qaytganda — yuborilgan bloklar; aks holda posting o‘zinikilar.
    $oldBlocks = old('blocks');
    $blocks = is_string($oldBlocks) ? (json_decode($oldBlocks, true) ?: []) : ($post?->blocks ?? []);
    $blocks = array_map(fn ($b) => ($b['type'] ?? null) === 'image' && ! empty($b['path'])
        ? $b + ['url' => Post::mediaUrl((string) $b['path'])]
        : $b, is_array($blocks) ? $blocks : []);

    if (old('tags') !== null) {
        $chips = array_values(array_filter(array_map('trim', explode(',', (string) old('tags')))));
    } else {
        $inText = $post ? array_keys(ContentFormatter::hashtags($post->content)) : [];
        $chips = $post ? $post->tags->reject(fn ($t) => in_array($t->slug, $inText, true))->pluck('name')->values()->all() : [];
    }

    $config = [
        'title' => old('title', $post?->title ?? ''),
        'blocks' => $blocks,
        'tags' => $chips,
        'maxTags' => (int) config('fikrlash.posts.max_tags'),
        'max' => (int) config('fikrlash.articles.max_length'),
        'draftKey' => $post ? null : 'fikrlash:article:'.$me->id.':new',
        'tagsUrl' => route('compose.tags'),
        'uploadUrl' => route('compose.images'),
    ];
    $tool = 'grid size-[38px] sm:size-10 shrink-0 place-items-center rounded-xl text-ink-soft transition-colors hover:bg-sunken hover:text-ink aria-pressed:bg-lapis-soft aria-pressed:text-lapis';
@endphp
<form method="POST" action="{{ $post ? route('posts.update', $post) : route('posts.store') }}"
      x-data="articleEditor(@js($config))" @submit="onSubmit($event)"
      @dragover.prevent="dragging = true" @dragleave.self="dragging = false" @drop.prevent="drop($event)"
      class="relative flex min-h-[100dvh] flex-col md:min-h-[calc(100vh-7rem)]">
    @csrf
    @if ($post) @method('PUT') @endif
    <input type="hidden" name="type" value="article">
    <input type="hidden" name="title" :value="title">
    <input type="hidden" name="blocks" :value="serialized">
    <input type="hidden" name="tags" :value="tags.join(',')">
    @unless ($post)<input type="hidden" name="draft" :value="asDraft ? 1 : 0">@endunless
    @if ($isDraft)<input type="hidden" name="publish" :value="asDraft ? 0 : 1">@endif

    {{-- Ilova paneli: orqaga, tur tanlash, ko‘rish va chop etish --}}
    <div class="app-bar">
        <a href="{{ $isPublished ? route('posts.show', $post) : route('home') }}" @click.prevent="backOr($el.href)" class="icon-btn" aria-label="Orqaga"><x-ico name="arrow-left" /></a>
        @if ($post)
            <p class="truncate text-[15px] font-medium">{{ $isPublished ? 'Maqolani tahrirlash' : 'Qoralama' }}</p>
        @else
            @include('posts._type-switch', ['current' => 'article'])
        @endif
        <span class="flex-1"></span>
        @if (! $post || $isDraft)
            <button type="button" class="btn btn-ghost btn-sm !px-3" @click="saveDraft()" :disabled="submitting" title="Serverda saqlash — keyin davom ettirasiz">Qoralama</button>
        @endif
        <button type="button" class="btn btn-primary btn-sm ml-0.5" @click="publish()" :disabled="!canSubmit" :title="blockedReason">
            <x-spinner class="!size-4 !text-current" x-show="submitting" x-cloak />
            {{ $isPublished ? 'Saqlash' : 'Chop etish' }}
        </button>
    </div>

    <div x-show="restored" x-cloak class="mx-5 mt-4 flex items-center gap-3 rounded-xl bg-lapis-soft/60 px-3.5 py-2.5 text-[13px] text-ink-soft sm:mx-10">
        <x-ico name="document" size="size-4" class="text-lapis" />
        <span class="flex-1">Saqlangan qoralamangiz tiklandi.</span>
        <button type="button" class="font-medium text-ink underline decoration-line-strong underline-offset-4 hover:decoration-ink" @click="discardDraft()">Tozalash</button>
    </div>

    @if ($errors->hasAny(['title', 'blocks', 'tags', 'tags.*']))
        <div class="mx-5 mt-4 rounded-xl bg-anor-soft px-4 py-3 text-[13px] text-anor sm:mx-10" role="alert">
            {{ $errors->first('title') ?: $errors->first('blocks') ?: $errors->first('tags') ?: $errors->first('tags.*') }}
        </div>
    @endif

    {{-- Yozish --}}
    <div x-show="!showPreview" class="flex-1 px-5 pb-40 pt-7 sm:px-10 sm:pb-12 sm:pt-10">
        <label for="article-title" class="sr-only">Sarlavha</label>
        <textarea id="article-title" x-ref="title" x-model="title" data-grow rows="1" maxlength="{{ config('fikrlash.articles.title_max') }}"
                  placeholder="Sarlavha" enterkeyhint="next" @input="grow($el); restored = false" @keydown="titleKey($event)"
                  class="article-title block w-full resize-none border-0 bg-transparent p-0 placeholder:text-muted/45 focus:outline-none focus:ring-0"></textarea>

        <div class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px] text-muted">
            <x-avatar :user="$me" size="xs" />
            <span class="font-medium text-ink-soft">{{ $me->name }}</span>
            <span aria-hidden="true">·</span>
            <span x-text="words + ' so‘z'">0 so‘z</span>
            <span aria-hidden="true">·</span>
            <span x-text="'~' + readMinutes + ' daqiqalik o‘qish'"></span>
            <span x-show="savedLabel" x-cloak class="text-firuza" x-text="'· ' + savedLabel"></span>
            <span x-show="tooLong" x-cloak class="text-anor" x-text="'· ' + chars + ' / {{ config('fikrlash.articles.max_length') }} belgi'"></span>
        </div>

        <div class="ae mt-8" aria-label="Maqola matni">
            <template x-for="(b, i) in blocks" :key="b.id">
                <div class="ae-block group/block relative" :class="{ 'ae-flash': flash === b.id, ['is-' + b.type]: true }">
                    {{-- Paragraf, kichik sarlavha, iqtibos --}}
                    <template x-if="isText(b)">
                        <textarea :data-idx="i" data-grow rows="1" x-model="b.text" :class="'ae-' + b.type" :placeholder="placeholder(b, i)"
                                  :aria-label="b.type === 'h' ? 'Kichik sarlavha' : (b.type === 'quote' ? 'Iqtibos' : 'Paragraf')"
                                  :enterkeyhint="b.type === 'p' ? 'enter' : 'next'"
                                  @keydown="onKey($event, i)" @input="onInput($event, i)" @paste="onPaste($event, i)"
                                  @focus="track($event, i)" @click="track($event, i)" @keyup="track($event, i)" @select="track($event, i)"
                                  x-init="$nextTick(() => grow($el))" x-effect="b.type; $nextTick(() => grow($el))"></textarea>
                    </template>

                    {{-- Ro‘yxat: har bir band alohida --}}
                    <template x-if="b.type === 'list'">
                        <div class="ae-list" role="list">
                            <template x-for="(item, j) in b.items" :key="item.id">
                                <div class="ae-item" role="listitem">
                                    <span class="ae-marker" aria-hidden="true" x-text="b.ordered ? (j + 1) + '.' : '•'"></span>
                                    <textarea :data-idx="i" :data-item="j" data-grow rows="1" x-model="item.text" placeholder="Ro‘yxat bandi" aria-label="Ro‘yxat bandi"
                                              @keydown="onItemKey($event, i, j)" @input="grow($event.target); track($event, i, j)" @paste="onPaste($event, i, j)"
                                              @focus="track($event, i, j)" @click="track($event, i, j)" @keyup="track($event, i, j)" @select="track($event, i, j)"
                                              x-init="$nextTick(() => grow($el))"></textarea>
                                </div>
                            </template>
                        </div>
                    </template>

                    {{-- Rasm --}}
                    <template x-if="b.type === 'image'">
                        <figure class="ae-figure">
                            <div class="relative overflow-hidden bg-sunken sm:rounded-2xl">
                                <img :src="b.url" alt="" class="block w-full" :style="b.w ? `aspect-ratio: ${b.w} / ${b.h}` : ''">
                                <div x-show="b.uploading" class="absolute inset-0 grid place-items-center bg-paper/55 backdrop-blur-[2px]">
                                    <div class="w-44 text-center">
                                        <div class="h-1.5 overflow-hidden rounded-full bg-line-strong/60"><div class="h-full rounded-full bg-lapis transition-[width] duration-200" :style="`width: ${Math.max(6, Math.round(b.progress * 100))}%`"></div></div>
                                        <p class="mt-2 font-sans text-[12px] font-medium text-ink-soft">Yuklanmoqda…</p>
                                    </div>
                                </div>
                                <div class="absolute right-2 top-2 flex gap-1.5 transition-opacity sm:opacity-0 sm:group-hover/block:opacity-100 sm:group-focus-within/block:opacity-100">
                                    <button type="button" class="ae-chip" @click="moveBlock(i, -1)" x-show="i > 0" aria-label="Yuqoriga"><x-ico name="arrow-up" size="size-4" /></button>
                                    <button type="button" class="ae-chip" @click="moveBlock(i, 1)" x-show="i < blocks.length - 1" aria-label="Pastga"><x-ico name="arrow-down" size="size-4" /></button>
                                    <button type="button" class="ae-chip hover:!bg-anor" @click="removeBlock(i)" aria-label="Rasmni olib tashlash"><x-ico name="trash" size="size-4" /></button>
                                </div>
                                <span x-show="i === blocks.findIndex(x => x.type === 'image') && !b.uploading" class="absolute bottom-2 left-2 rounded-full bg-ink/65 px-2.5 py-1 font-sans text-[11px] font-medium text-white backdrop-blur">Muqova</span>
                            </div>
                            <input type="text" x-model="b.caption" maxlength="{{ config('fikrlash.articles.caption_max') }}" placeholder="Rasm izohi (ixtiyoriy)" aria-label="Rasm izohi"
                                   class="ae-caption" @focus="focused = i; focusedItem = null" @keydown.enter.prevent="focusNearest(i + 1, 'start', 1)">
                        </figure>
                    </template>

                    {{-- Ajratgich --}}
                    <template x-if="b.type === 'hr'">
                        <div class="relative">
                            <hr class="ae-hr">
                            <button type="button" class="ae-chip absolute right-0 top-1/2 -translate-y-1/2 sm:opacity-0 sm:group-hover/block:opacity-100" @click="removeBlock(i)" aria-label="Ajratgichni olib tashlash"><x-ico name="x" size="size-4" /></button>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        <div class="mt-12 border-t border-line pt-5">
            @include('partials.tag-field', ['field' => 'article', 'hint' => 'Teglar maqolani topishga yordam beradi.'])
        </div>
    </div>

    {{-- Ko‘rinishi: o‘quvchi ko‘radigan holat --}}
    <div x-show="showPreview" x-cloak class="flex-1 px-5 pb-16 pt-8 sm:px-10 sm:pt-10">
        <p class="article-kicker">Maqola · <span x-text="readMinutes"></span> daqiqalik o‘qish</p>
        <h1 class="article-title mt-3" x-text="title.trim() || 'Sarlavhasiz maqola'"></h1>
        <div class="mt-5 flex items-center gap-2.5 text-[13px] text-muted"><x-avatar :user="$me" size="xs" /><span class="font-medium text-ink-soft">{{ $me->name }}</span></div>
        <div class="article-body mt-8" x-html="previewHtml"></div>
        <button type="button" class="btn btn-secondary btn-sm mt-10 mb-16" @click="togglePreview()"><x-ico name="pencil" size="size-4" /> Yozishga qaytish</button>
    </div>

    {{-- Asboblar paneli: mobilda klaviatura ustida, kompyuterda varaq pastida --}}
    <div :style="`bottom: ${kb}px`"
         class="fixed inset-x-0 z-30 border-t border-line bg-paper/95 pb-[env(safe-area-inset-bottom)] backdrop-blur-md md:sticky md:rounded-b-[28px] md:pb-0">
        <div class="mx-auto flex max-w-[680px] items-center gap-0.5 overflow-x-auto px-1.5 py-1.5 [scrollbar-width:none] sm:px-4" :class="showPreview && '[&>.tool]:pointer-events-none [&>.tool]:opacity-30'">
            <label class="tool {{ $tool }} cursor-pointer" title="Rasm qo‘shish — yoki sudrab tashlang / Ctrl+V">
                <x-ico name="photo" />
                <span class="sr-only">Rasm qo‘shish</span>
                <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="sr-only" @change="pickImages($event)">
            </label>
            <button type="button" class="tool {{ $tool }} font-serif text-[1.15rem] font-semibold" @mousedown.prevent @click="setType('h')" :aria-pressed="isActive('h')" title="Kichik sarlavha (## )">H<span class="sr-only">Kichik sarlavha</span></button>
            <button type="button" class="tool {{ $tool }}" @mousedown.prevent @click="setType('quote')" :aria-pressed="isActive('quote')" title="Iqtibos (> )"><x-ico name="quote" /><span class="sr-only">Iqtibos</span></button>
            <button type="button" class="tool {{ $tool }}" @mousedown.prevent @click="setList(false)" :aria-pressed="isActive('list', false)" title="Ro‘yxat (- )"><x-ico name="list-bullet" /><span class="sr-only">Ro‘yxat</span></button>
            <button type="button" class="tool {{ $tool }}" @mousedown.prevent @click="setList(true)" :aria-pressed="isActive('list', true)" title="Raqamli ro‘yxat (1. )"><x-ico name="list-number" /><span class="sr-only">Raqamli ro‘yxat</span></button>
            <button type="button" class="tool {{ $tool }}" @mousedown.prevent @click="insertHr()" title="Ajratgich (---)"><x-ico name="minus" /><span class="sr-only">Ajratgich</span></button>
            <span class="mx-1 h-6 w-px shrink-0 bg-line" aria-hidden="true"></span>
            <button type="button" class="tool {{ $tool }} font-sans text-[15px] font-bold" @mousedown.prevent @click="wrap('**')" title="Qalin (Ctrl+B)">B<span class="sr-only">Qalin</span></button>
            <button type="button" class="tool {{ $tool }} font-serif text-[1.1rem] italic" @mousedown.prevent @click="wrap('*')" title="Kursiv (Ctrl+I)">I<span class="sr-only">Kursiv</span></button>
            <span class="min-w-1 flex-1"></span>
            <button type="button" class="{{ $tool }}" @click="togglePreview()" :aria-pressed="showPreview" title="Ko‘rinishi — o‘quvchi qanday ko‘radi"><x-ico name="eye" /><span class="sr-only">Ko‘rinishi</span></button>
        </div>
    </div>

    <div x-show="dragging" x-cloak class="pointer-events-none absolute inset-0 z-40 grid place-items-center rounded-[28px] bg-lapis-soft/85 text-sm font-medium text-lapis">Rasmlarni shu yerga tashlang</div>
</form>
