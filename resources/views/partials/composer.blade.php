{{--
  Post yozish — iloji boricha sodda: matn va (ixtiyoriy) rasm.
  Mavzu, teglar va kimga ko‘rsatish — tizim o‘zi aniqlaydi (AI tahlili + tavsiya algoritmi).
  $post (tahrirlashda), $compact (bosh sahifada — fokus bo‘lganda ochiladi).
--}}
@php
    $post = $post ?? null;
    $compact = $compact ?? false;
    $max = config('fikrlash.posts.max_length');
    $content = old('content', $post?->content ?? '');
    $prompts = ['Bugun nimani o‘ylayapsiz?', 'Qanday g‘oya xayolingizdan ketmayapti?', 'Bugun nimani o‘rgandingiz?', 'Qaysi savol sizni o‘ylantiryapti?'];
    $isDraft = $post && $post->status === \App\Enums\PostStatus::Draft;
    $me = auth()->user();
@endphp
<form method="POST" enctype="multipart/form-data"
      action="{{ $post ? route('posts.update', $post) : route('posts.store') }}"
      x-data="composer({ max: {{ $max }}, content: @js($content) })"
      @if ($compact) x-init="expanded = expanded || {{ $errors->any() ? 'true' : 'false' }}" @endif
      class="{{ $compact ? 'border-b border-line px-4 pb-4 pt-5 sm:px-6' : 'flex min-h-[calc(100dvh-8.6rem)] flex-col px-4 pt-6 sm:px-6 md:min-h-0' }}">
    @csrf
    @if ($post) @method('PUT') @endif
    @if ($isDraft)<input type="hidden" name="publish" value="1">@endif

    <div class="flex flex-1 gap-3.5">
        <div class="{{ $compact ? '' : 'hidden sm:block' }} shrink-0 pt-0.5"><x-avatar :user="$me" :size="$compact ? 'sm' : 'md'" /></div>
        <div class="min-w-0 flex-1">
            <label for="content-{{ $post?->id ?? 'new' }}" class="sr-only">Post matni</label>
            <textarea id="content-{{ $post?->id ?? 'new' }}" name="content" x-model="content" x-ref="text"
                      @focus="expanded = true" @input="grow($el)" x-init="grow($el)"
                      @keydown.ctrl.enter="$el.form.requestSubmit()" @keydown.meta.enter="$el.form.requestSubmit()"
                      rows="{{ $compact ? 1 : 5 }}" maxlength="{{ $max + 500 }}"
                      placeholder="{{ $prompts[now()->dayOfYear % count($prompts)] }}"
                      class="block w-full resize-none border-0 bg-transparent p-0 font-serif tracking-[-0.01em] text-ink placeholder:text-muted/70 focus:outline-none focus:ring-0 {{ $compact ? 'min-h-9 pt-1 text-[1.3rem] leading-[1.4]' : 'min-h-40 text-[1.5rem] leading-[1.42] sm:text-[1.75rem] sm:leading-[1.35]' }}"
                      @if (! $compact) autofocus @endif>{{ $content }}</textarea>
            @error('content')<p class="field-error">{{ $message }}</p>@enderror

            <template x-if="preview">
                <div class="relative mt-4 overflow-hidden rounded-2xl bg-sunken">
                    <img :src="preview" alt="Tanlangan rasm" class="max-h-80 w-full object-cover">
                    <button type="button" @click="clearImage" class="absolute right-2.5 top-2.5 grid size-9 place-items-center rounded-full bg-ink/70 text-white backdrop-blur hover:bg-ink" aria-label="Rasmni olib tashlash">
                        <x-ico name="x" size="size-4" />
                    </button>
                </div>
            </template>
            @if ($post?->image_path)
                <label class="mt-4 flex items-center gap-4 text-sm" x-show="!preview">
                    <img src="{{ $post->imageUrl() }}" alt="" class="size-16 rounded-xl object-cover">
                    <span class="flex-1 text-muted">Joriy rasm</span>
                    <span class="flex items-center gap-2"><input type="checkbox" name="remove_image" value="1" class="accent-[var(--anor)]"> Olib tashlash</span>
                </label>
            @endif
            @error('image')<p class="field-error">{{ $message }}</p>@enderror
        </div>
    </div>

    {{-- Asboblar: rasm, belgilar hisobi, chop etish. To‘liq sahifada mobil ekranda pastga yopishadi. --}}
    <div x-show="expanded" @if ($compact) x-cloak x-transition.opacity.duration.150ms @endif
         class="{{ $compact ? 'mt-3 sm:pl-[3.125rem]' : 'sticky bottom-0 -mx-4 mt-6 border-t border-line bg-paper/95 px-4 pb-[calc(0.75rem+env(safe-area-inset-bottom))] pt-3 backdrop-blur sm:-mx-6 sm:rounded-b-[27px] sm:px-6' }}">
        <div class="flex items-center gap-2">
            <label class="grid size-10 cursor-pointer place-items-center rounded-full text-muted transition-colors hover:bg-sunken hover:text-lapis" title="Rasm qo‘shish (JPG, PNG, WEBP, 5 MB gacha)">
                <x-ico name="photo" />
                <span class="sr-only">Rasm qo‘shish</span>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="sr-only" x-ref="image" @change="pick">
            </label>

            <span class="flex-1"></span>

            {{-- Qolgan belgilar: limitga yaqinlashganda ko‘rinadi --}}
            <span class="text-[13px] tabular-nums" :class="tooLong ? 'font-medium text-anor' : (left < 200 ? 'text-amber' : 'text-muted')"
                  x-show="left < 300" x-cloak x-text="left"></span>

            <button type="submit" class="btn btn-primary min-w-[6.5rem]" :disabled="!content.trim() || tooLong">
                {{ $post && $post->isPublished() ? 'Saqlash' : 'Chop etish' }}
            </button>
        </div>
    </div>
</form>
