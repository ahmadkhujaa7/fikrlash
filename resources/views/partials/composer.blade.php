{{--
  Post yozish. Asosiy g‘oya — sodda, lekin qulay:
    • matn + (ixtiyoriy) rasm; mavzuni tizim o‘zi aniqlaydi;
    • # va @ yozilganda takliflar (mavjud teg yoki yangi teg yaratish, odamni eslatish);
    • teglar uchun alohida chip-maydon (to‘liq sahifada);
    • rasmni sudrab tashlash yoki Ctrl+V bilan qo‘yish;
    • qoralama avtomatik saqlanadi (brauzerda) va qaytib kelganda tiklanadi;
    • "Ko‘rinishi" — post qanday chiqishini oldindan ko‘rish; Ctrl+Enter — chop etish.
  $post (tahrirlashda), $compact (bosh sahifada — fokus bo‘lganda ochiladi).
--}}
@php
    use App\Support\ContentFormatter;

    $post = $post ?? null;
    $compact = $compact ?? false;
    $full = ! $compact;
    $max = config('fikrlash.posts.max_length');
    $content = old('content', $post?->content ?? '');
    $prompts = ['Bugun nimani o‘ylayapsiz?', 'Qanday g‘oya xayolingizdan ketmayapti?', 'Bugun nimani o‘rgandingiz?', 'Qaysi savol sizni o‘ylantiryapti?'];
    $isDraft = $post && $post->status === \App\Enums\PostStatus::Draft;
    $me = auth()->user();

    // Chip ko‘rinishidagi teglar: matnda #teg sifatida yo‘q bo‘lganlari (masalan, avval qo‘shilgan yoki AI qo‘ygan).
    if (old('tags') !== null) {
        $chips = array_values(array_filter(array_map('trim', is_array(old('tags')) ? old('tags') : explode(',', (string) old('tags')))));
    } else {
        $inText = array_keys(ContentFormatter::hashtags($content));
        $chips = $post ? $post->tags->reject(fn ($t) => in_array($t->slug, $inText, true))->pluck('name')->values()->all() : [];
    }

    $config = [
        'max' => $max,
        'content' => $content,
        'tags' => $chips,
        'maxTags' => (int) config('fikrlash.posts.max_tags'),
        'full' => $full,
        'draftKey' => $post ? null : 'fikrlash:draft:'.$me->id.':new',
        'tagsUrl' => route('compose.tags'),
        'usersUrl' => route('compose.users'),
    ];
    $field = 'content-'.($post?->id ?? ($compact ? 'quick' : 'new'));
@endphp
<form method="POST" enctype="multipart/form-data"
      action="{{ $post ? route('posts.update', $post) : route('posts.store') }}"
      x-data="composer(@js($config))"
      @if ($compact) x-init="expanded = expanded || {{ $errors->any() ? 'true' : 'false' }}" @endif
      @submit="onSubmit($event)" @compose-focus.window="expanded = true; $nextTick(() => $refs.text.focus())"
      @dragover.prevent="dragging = true" @dragleave.self="dragging = false" @drop.prevent="drop($event)" @paste="paste($event)"
      class="relative transition-shadow {{ $compact ? 'border-b border-line px-4 pb-4 pt-5 sm:px-6' : 'flex min-h-[calc(100dvh-3.5rem)] flex-col px-4 pt-5 sm:px-6 md:min-h-0' }}"
      :class="{ 'ring-2 ring-inset ring-lapis/40': dragging }">
    @csrf
    @if ($post) @method('PUT') @endif
    @if ($isDraft)<input type="hidden" name="publish" value="1">@endif
    @if ($full)<input type="hidden" name="tags" :value="tags.join(',')" value="{{ implode(',', $chips) }}">@endif

    {{-- Tiklangan qoralama haqida xabar --}}
    <div x-show="restored" x-cloak class="mb-4 flex items-center gap-3 rounded-xl bg-lapis-soft/60 px-3.5 py-2.5 text-[13px] text-ink-soft">
        <x-ico name="document" size="size-4" class="text-lapis" />
        <span class="flex-1">Saqlangan qoralamangiz tiklandi.</span>
        <button type="button" class="font-medium text-ink underline decoration-line-strong underline-offset-4 hover:decoration-ink" @click="discardDraft()">Tozalash</button>
    </div>

    <div class="flex flex-1 gap-3.5">
        <div class="{{ $compact ? '' : 'hidden sm:block' }} shrink-0 pt-0.5"><x-avatar :user="$me" :size="$compact ? 'sm' : 'md'" /></div>
        <div class="relative min-w-0 flex-1">
            <label for="{{ $field }}" class="sr-only">Post matni</label>
            <textarea id="{{ $field }}" name="content" x-model="content" x-ref="text" x-show="!showPreview"
                      @focus="expanded = true" @input="onInput($event)" @keydown="onKeydown($event)" @click="detectToken()" @blur="setTimeout(() => closeAc(), 150)"
                      x-init="grow($el)"
                      @keydown.ctrl.enter="$el.form.requestSubmit()" @keydown.meta.enter="$el.form.requestSubmit()"
                      rows="{{ $compact ? 1 : 5 }}" maxlength="{{ $max + 500 }}"
                      role="combobox" aria-autocomplete="list" :aria-expanded="ac.open" aria-controls="{{ $field }}-ac"
                      placeholder="{{ ($placeholder ?? null) ?: $prompts[now()->dayOfYear % count($prompts)] }}"
                      class="block w-full resize-none border-0 bg-transparent p-0 font-serif tracking-[-0.01em] text-ink placeholder:text-muted/70 focus:outline-none focus:ring-0 {{ $compact ? 'min-h-9 pt-1 text-[1.3rem] leading-[1.4]' : 'min-h-40 text-[1.5rem] leading-[1.42] sm:text-[1.75rem] sm:leading-[1.35]' }}"
                      @if ($full) autofocus @endif>{{ $content }}</textarea>

            @if ($full)
                {{-- Oldindan ko‘rish: post lentada qanday chiqishi --}}
                <div x-show="showPreview" x-cloak class="min-h-40 rounded-2xl border border-dashed border-line-strong p-4 sm:p-5">
                    <div class="mb-3 flex items-center gap-2.5">
                        <x-avatar :user="$me" size="sm" />
                        <span class="flex items-center gap-1 text-sm font-medium">{{ $me->name }}<x-verified :user="$me" /></span>
                        <span class="meta">hozir</span>
                    </div>
                    <div class="prose-post" x-html="previewHtml"></div>
                    <template x-if="preview"><img :src="preview" alt="" class="mt-4 max-h-80 w-full rounded-2xl object-cover"></template>
                    <div class="mt-3 flex flex-wrap gap-x-3 gap-y-1" x-show="tags.length">
                        <template x-for="t in tags" :key="t"><span class="meta text-lapis" x-text="'#' + t"></span></template>
                    </div>
                </div>
            @endif

            {{-- # va @ uchun takliflar --}}
            <div id="{{ $field }}-ac" x-show="ac.open" x-cloak x-transition.opacity.duration.100ms role="listbox"
                 class="absolute inset-x-0 z-30 mt-2 max-h-72 overflow-y-auto rounded-2xl border border-line bg-surface py-1.5 shadow-[0_16px_48px_-16px_rgb(0_0_0/0.3)]">
                <p class="px-3.5 pb-1 pt-1 text-[12px] font-medium text-muted" x-text="ac.type === 'user' ? 'Kimni eslatamiz?' : 'Teg tanlang yoki yangisini yarating'"></p>
                <template x-for="(item, i) in ac.items" :key="item.key">
                    <button type="button" role="option" :aria-selected="i === ac.index" @mousedown.prevent="choose(item)" @mouseenter="ac.index = i"
                            class="flex w-full items-center gap-3 px-3.5 py-2 text-left text-sm" :class="i === ac.index ? 'bg-sunken' : ''">
                        <template x-if="item.type === 'user'">
                            <span class="flex min-w-0 flex-1 items-center gap-2.5">
                                <template x-if="item.avatar"><img :src="item.avatar" alt="" class="size-7 shrink-0 rounded-full object-cover"></template>
                                <template x-if="!item.avatar"><span class="bg-tone grid size-7 shrink-0 place-items-center rounded-full font-serif text-[13px] text-white" :class="'tone-' + item.tone" x-text="item.initials"></span></template>
                                <span class="truncate font-medium text-ink" x-text="item.name"></span>
                                <svg x-show="item.verified" class="size-3.5 shrink-0 text-lapis" viewBox="0 0 24 24" aria-label="Tasdiqlangan"><g fill="currentColor" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"><rect x="5.2" y="5.2" width="13.6" height="13.6" rx="1.2"/><rect x="5.2" y="5.2" width="13.6" height="13.6" rx="1.2" transform="rotate(45 12 12)"/></g><path d="m8.4 12.3 2.4 2.4 4.8-5.1" fill="none" stroke="#fff" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <span class="truncate text-muted" x-text="'@' + item.username"></span>
                            </span>
                        </template>
                        <template x-if="item.type === 'tag'">
                            <span class="flex min-w-0 flex-1 items-center justify-between gap-3">
                                <span class="truncate font-serif text-[1rem] text-ink"><span x-show="item.isNew" class="mr-1 font-sans text-[12px] font-medium text-lapis">Yangi teg</span><span x-text="'#' + item.name"></span></span>
                                <span class="shrink-0 text-[12px] text-muted" x-show="!item.isNew" x-text="item.count + ' ta fikr'"></span>
                            </span>
                        </template>
                    </button>
                </template>
            </div>

            <template x-if="preview">
                <div class="relative mt-4 overflow-hidden rounded-2xl bg-sunken" x-show="!showPreview">
                    <img :src="preview" alt="Tanlangan rasm" class="max-h-80 w-full object-cover">
                    <button type="button" @click="clearImage" class="absolute right-2.5 top-2.5 grid size-9 place-items-center rounded-full bg-ink/70 text-white backdrop-blur hover:bg-ink" aria-label="Rasmni olib tashlash">
                        <x-ico name="x" size="size-4" />
                    </button>
                    <span class="absolute bottom-2.5 left-2.5 rounded-full bg-ink/70 px-2.5 py-1 text-[12px] text-white backdrop-blur" x-text="imageInfo"></span>
                </div>
            </template>
            @if ($post?->image_path)
                <label class="mt-4 flex items-center gap-4 text-sm" x-show="!preview">
                    <img src="{{ $post->imageUrl() }}" alt="" class="size-16 rounded-xl object-cover">
                    <span class="flex-1 text-muted">Joriy rasm</span>
                    <span class="flex items-center gap-2"><input type="checkbox" name="remove_image" value="1" class="accent-[var(--anor)]"> Olib tashlash</span>
                </label>
            @endif

            {{-- Rasmni sudrab tashlash uchun ko‘rsatma --}}
            <div x-show="dragging" x-cloak class="pointer-events-none absolute inset-0 grid place-items-center rounded-2xl bg-lapis-soft/80 text-sm font-medium text-lapis">Rasmni shu yerga tashlang</div>

            @error('content')<p class="field-error">{{ $message }}</p>@enderror
            @error('image')<p class="field-error">{{ $message }}</p>@enderror
            @error('tags')<p class="field-error">{{ $message }}</p>@enderror
            @error('tags.*')<p class="field-error">{{ $message }}</p>@enderror

            @if ($full)
                {{-- Teglar: matndagi #teglar avtomatik + qo‘lda qo‘shilganlar (mavjudini tanlash yoki yangi yaratish) --}}
                <div class="mt-6 border-t border-line pt-4" x-show="!showPreview">
                    @include('partials.tag-field', ['field' => $field])
                </div>
            @endif
        </div>
    </div>

    {{-- Asboblar paneli. To‘liq sahifada mobil ekranda pastga yopishadi. --}}
    <div x-show="expanded" @if ($compact) x-cloak x-transition.opacity.duration.150ms @else :style="kb ? `transform: translateY(-${kb}px)` : ''" @endif
         class="{{ $compact ? 'mt-3 sm:pl-[3.125rem]' : 'sticky bottom-0 -mx-4 mt-6 border-t border-line bg-paper/95 px-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] pt-3 backdrop-blur sm:-mx-6 sm:rounded-b-[27px] sm:px-5' }}">
        <div class="flex items-center gap-0.5">
            <label class="grid size-10 cursor-pointer place-items-center rounded-full text-muted transition-colors hover:bg-sunken hover:text-lapis" title="Rasm qo‘shish — yoki sudrab tashlang / Ctrl+V (JPG, PNG, WEBP, 5 MB gacha)">
                <x-ico name="photo" />
                <span class="sr-only">Rasm qo‘shish</span>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="sr-only" x-ref="image" @change="pick">
            </label>
            <button type="button" class="grid size-10 place-items-center rounded-full font-serif text-[1.3rem] leading-none text-muted transition-colors hover:bg-sunken hover:text-lapis"
                    title="Teg qo‘shish (#)" @click="{{ $full ? '$refs.tagInput ? $refs.tagInput.focus() : insertSymbol(\'#\')' : 'insertSymbol(\'#\')' }}">#<span class="sr-only">Teg qo‘shish</span></button>
            <button type="button" class="grid size-10 place-items-center rounded-full text-[1.15rem] leading-none text-muted transition-colors hover:bg-sunken hover:text-lapis"
                    title="Kimnidir eslatish (@)" @click="insertSymbol('@')">@<span class="sr-only">Kimnidir eslatish</span></button>
            @if ($compact)
                <a href="{{ route('posts.create', ['type' => 'article']) }}" class="ml-1 inline-flex h-8 items-center gap-1.5 rounded-full border border-line px-3 text-[12px] font-medium text-ink-soft transition-colors hover:border-lapis hover:text-lapis"
                   title="Maqola yozish — sarlavha, rasmlar va bo‘limlar bilan">
                    <x-ico name="newspaper" size="size-4" /> Maqola
                </a>
            @endif
            @if ($full)
                <button type="button" class="grid size-10 place-items-center rounded-full transition-colors hover:bg-sunken"
                        :class="showPreview ? 'bg-lapis-soft text-lapis' : 'text-muted hover:text-lapis'" @click="togglePreview()"
                        :aria-pressed="showPreview" title="Ko‘rinishi — post qanday chiqadi">
                    <x-ico name="eye" /><span class="sr-only">Ko‘rinishi</span>
                </button>
            @endif

            <span class="flex-1"></span>

            @if ($full)
                <span class="mr-2 hidden text-[12px] tabular-nums text-muted sm:inline" x-show="words > 0" x-cloak>
                    <span x-text="words"></span> so‘z · ~<span x-text="readMinutes"></span> daqiqa
                </span>
            @endif
            <span class="mr-2 hidden text-[12px] text-muted sm:inline" x-show="savedLabel" x-cloak x-text="savedLabel"></span>

            {{-- Belgilar halqasi: limitga yaqinlashganda raqam chiqadi --}}
            <span class="relative mr-2 grid size-7 place-items-center" x-show="content.length > 0" x-cloak :title="left + ' belgi qoldi'">
                <svg class="size-7 -rotate-90" viewBox="0 0 28 28" aria-hidden="true">
                    <circle cx="14" cy="14" r="11" fill="none" stroke="var(--line)" stroke-width="2.5" />
                    <circle cx="14" cy="14" r="11" fill="none" stroke-width="2.5" stroke-linecap="round"
                            :stroke="tooLong ? 'var(--anor)' : (left < 200 ? 'var(--amber)' : 'var(--lapis)')"
                            stroke-dasharray="69.115" :stroke-dashoffset="69.115 * (1 - progress)" />
                </svg>
                <span class="absolute text-[10px] font-medium tabular-nums" :class="tooLong ? 'text-anor' : 'text-amber'" x-show="left < 200" x-text="left"></span>
            </span>

            <button type="submit" class="btn btn-primary min-w-[6.5rem]" :disabled="!content.trim() || tooLong || submitting">
                <x-spinner class="!size-4 !text-current" x-show="submitting" x-cloak />
                {{ $post && $post->isPublished() ? 'Saqlash' : 'Chop etish' }}
            </button>
        </div>
    </div>
</form>
