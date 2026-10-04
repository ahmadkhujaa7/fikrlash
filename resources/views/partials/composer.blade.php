{{--
  Post yozish formasi — platformaning yuragi.
  $post (tahrirlashda), $categories, $compact (bosh sahifada — fokus bo‘lganda ochiladi).
--}}
@php
    $post = $post ?? null;
    $compact = $compact ?? false;
    $max = config('fikrlash.posts.max_length');
    $content = old('content', $post?->content ?? '');
    $prompts = ['Bugun nimani o‘ylayapsiz?', 'Qanday g‘oya xayolingizdan ketmayapti?', 'Bugun nimani o‘rgandingiz?', 'Qaysi savol sizni o‘ylantiryapti?'];
    $isDraft = $post && $post->status === \App\Enums\PostStatus::Draft;
    $tagsValue = old('tags') ? (is_array(old('tags')) ? implode(', ', old('tags')) : old('tags')) : $post?->tags?->pluck('name')->implode(', ');
@endphp
<form method="POST" enctype="multipart/form-data"
      action="{{ $post ? route('posts.update', $post) : route('posts.store') }}"
      x-data="composer({ max: {{ $max }}, content: @js($content) })"
      @if ($compact) x-init="expanded = expanded || {{ $errors->any() ? 'true' : 'false' }}" @endif
      class="px-4 sm:px-5 {{ $compact ? 'pb-6 pt-10' : 'py-8' }}">
    @csrf
    @if ($post) @method('PUT') @endif

    <label for="content-{{ $post?->id ?? 'new' }}" class="sr-only">Post matni</label>
    <textarea id="content-{{ $post?->id ?? 'new' }}" name="content" x-model="content" x-ref="text"
              @focus="expanded = true" @input="grow($el)" x-init="grow($el)"
              rows="{{ $compact ? 1 : 6 }}" maxlength="{{ $max + 500 }}"
              placeholder="{{ $prompts[now()->dayOfYear % count($prompts)] }}"
              class="block w-full resize-none border-0 bg-transparent p-0 font-serif text-[1.75rem] leading-[1.3] tracking-[-0.01em] text-ink placeholder:text-muted/70 focus:outline-none focus:ring-0 {{ $compact ? 'min-h-10' : 'min-h-48' }}"
              @if (! $compact) autofocus @endif>{{ $content }}</textarea>
    @error('content')<p class="field-error">{{ $message }}</p>@enderror

    <template x-if="preview">
        <div class="relative mt-5 overflow-hidden rounded-2xl bg-sunken">
            <img :src="preview" alt="Tanlangan rasm" class="max-h-80 w-full object-cover">
            <button type="button" @click="clearImage" class="absolute right-3 top-3 rounded-full bg-ink/70 p-1.5 text-white hover:bg-ink" aria-label="Rasmni olib tashlash">
                <x-ico name="x" size="size-4" />
            </button>
        </div>
    </template>
    @if ($post?->image_path)
        <label class="mt-5 flex items-center gap-4 text-sm" x-show="!preview">
            <img src="{{ $post->imageUrl() }}" alt="" class="size-16 rounded-xl object-cover">
            <span class="flex-1 text-muted">Joriy rasm</span>
            <span class="flex items-center gap-2"><input type="checkbox" name="remove_image" value="1" class="accent-[var(--anor)]"> Olib tashlash</span>
        </label>
    @endif
    @error('image')<p class="field-error">{{ $message }}</p>@enderror

    <div x-show="expanded" @if ($compact) x-cloak @endif class="mt-6 space-y-4">
        <div class="flex flex-wrap gap-2">
            <select name="category_id" class="field !w-auto !rounded-full !py-2 !pl-4 !pr-9 text-[13px]" aria-label="Mavzu">
                <option value="">Mavzu: AI tanlaydi</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) old('category_id', $post?->category_id) === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <select name="visibility" class="field !w-auto !rounded-full !py-2 !pl-4 !pr-9 text-[13px]" aria-label="Kim ko‘radi">
                <option value="public" @selected(old('visibility', $post?->visibility?->value) !== 'followers')>Hamma ko‘radi</option>
                <option value="followers" @selected(old('visibility', $post?->visibility?->value) === 'followers')>Faqat obunachilar</option>
            </select>
            <input type="text" name="tags" class="field !w-auto min-w-0 flex-1 !rounded-full !py-2 !px-4 text-[13px]" maxlength="200"
                   value="{{ $tagsValue }}" placeholder="Teglar: ai, biznes, kitob" aria-label="Teglar">
        </div>
        @error('tags')<p class="field-error">{{ $message }}</p>@enderror
        @error('tags.*')<p class="field-error">{{ $message }}</p>@enderror

        <div class="flex items-center gap-2 border-t border-line pt-4">
            <label class="cursor-pointer rounded-full p-2 text-muted transition-colors hover:bg-sunken hover:text-ink" title="Rasm qo‘shish (JPG, PNG, WEBP, 5 MB gacha)">
                <x-ico name="photo" />
                <span class="sr-only">Rasm qo‘shish</span>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="sr-only" x-ref="image" @change="pick">
            </label>

            <span class="ml-auto text-[13px] tabular-nums" :class="tooLong ? 'font-medium text-anor' : (left < 200 ? 'text-amber' : 'text-muted')"
                  x-show="content.length > 0" x-text="left"></span>

            @if (! $post || $isDraft)
                <button type="submit" name="draft" value="1" class="btn btn-ghost btn-sm" :disabled="!content.trim() || tooLong">Qoralama</button>
            @endif
            @if ($isDraft)
                <input type="hidden" name="publish" value="1" x-ref="publish" disabled>
            @endif
            <button type="submit" class="btn btn-primary" :disabled="!content.trim() || tooLong"
                    @if ($isDraft) @click="$refs.publish.disabled = false" @endif>
                {{ $post && $post->isPublished() ? 'Saqlash' : 'Chop etish' }}
            </button>
        </div>
    </div>
</form>
