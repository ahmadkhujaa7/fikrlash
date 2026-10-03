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
@endphp
<form method="POST" enctype="multipart/form-data"
      action="{{ $post ? route('posts.update', $post) : route('posts.store') }}"
      x-data="composer({ max: {{ $max }}, content: @js($content) })"
      @if ($compact) x-init="expanded = expanded || {{ $errors->any() ? 'true' : 'false' }}" @endif
      class="flex gap-3 px-4 py-4 sm:px-5">
    @csrf
    @if ($post) @method('PUT') @endif

    <x-avatar :user="auth()->user()" class="mt-0.5" />

    <div class="min-w-0 flex-1">
        <label for="content-{{ $post?->id ?? 'new' }}" class="sr-only">Post matni</label>
        <textarea id="content-{{ $post?->id ?? 'new' }}" name="content" x-model="content" x-ref="text"
                  @focus="expanded = true" @input="grow($el)" x-init="grow($el)"
                  rows="{{ $compact ? 1 : 5 }}" maxlength="{{ $max + 500 }}"
                  placeholder="{{ $prompts[now()->dayOfYear % count($prompts)] }}"
                  class="block w-full resize-none border-0 bg-transparent p-0 py-2 font-serif text-[1.3rem] leading-snug text-ink placeholder:text-muted/80 focus:outline-none focus:ring-0 {{ $compact ? 'min-h-11' : 'min-h-40' }}"
                  @if (! $compact) autofocus @endif>{{ $content }}</textarea>
        @error('content')<p class="field-error">{{ $message }}</p>@enderror

        {{-- Rasm ko‘rinishi --}}
        <template x-if="preview">
            <div class="relative mt-3 overflow-hidden rounded-2xl border border-line">
                <img :src="preview" alt="Tanlangan rasm" class="max-h-80 w-full object-cover">
                <button type="button" @click="clearImage" class="absolute right-2 top-2 rounded-full bg-ink/70 p-1.5 text-white hover:bg-ink" aria-label="Rasmni olib tashlash">
                    <x-ico name="x" size="size-4" />
                </button>
            </div>
        </template>
        @if ($post?->image_path)
            <label class="mt-3 flex items-center gap-3 rounded-2xl border border-line p-2 text-sm" x-show="!preview">
                <img src="{{ $post->imageUrl() }}" alt="" class="size-16 rounded-xl object-cover">
                <span class="flex-1 text-muted">Joriy rasm</span>
                <input type="checkbox" name="remove_image" value="1" class="accent-[var(--anor)]"> <span class="pr-2">Olib tashlash</span>
            </label>
        @endif
        @error('image')<p class="field-error">{{ $message }}</p>@enderror

        <div x-show="expanded" @if ($compact) x-cloak @endif x-transition.opacity class="mt-3 space-y-3">
            <div class="grid gap-2 sm:grid-cols-2">
                <select name="category_id" class="field !py-2 text-sm" aria-label="Mavzu">
                    <option value="">Mavzu tanlang (AI taklif qiladi)</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('category_id', $post?->category_id) === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <select name="visibility" class="field !py-2 text-sm" aria-label="Kim ko‘radi">
                    <option value="public" @selected(old('visibility', $post?->visibility?->value) !== 'followers')>Hamma ko‘radi</option>
                    <option value="followers" @selected(old('visibility', $post?->visibility?->value) === 'followers')>Faqat obunachilar</option>
                </select>
            </div>
            <input type="text" name="tags" class="field !py-2 text-sm" maxlength="200"
                   value="{{ old('tags') ? (is_array(old('tags')) ? implode(', ', old('tags')) : old('tags')) : $post?->tags?->pluck('name')->implode(', ') }}"
                   placeholder="Teglar: ai, biznes, kitob (ixtiyoriy, 5 tagacha)" aria-label="Teglar">
            @error('tags')<p class="field-error">{{ $message }}</p>@enderror
            @error('tags.*')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div x-show="expanded" @if ($compact) x-cloak @endif class="mt-3 flex items-center gap-2 border-t border-line pt-3">
            <label class="btn-ghost cursor-pointer rounded-full p-2 text-lapis" title="Rasm qo‘shish (JPG, PNG, WEBP, 5 MB gacha)">
                <x-ico name="photo" />
                <span class="sr-only">Rasm qo‘shish</span>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="sr-only" x-ref="image" @change="pick">
            </label>

            <span class="ml-auto text-sm tabular-nums" :class="tooLong ? 'font-semibold text-anor' : (left < 200 ? 'text-amber' : 'text-muted')"
                  x-show="content.length > 0" x-text="left"></span>

            @if (! $post || $post->status === \App\Enums\PostStatus::Draft)
                <button type="submit" name="draft" value="1" class="btn btn-ghost btn-sm" :disabled="!content.trim() || tooLong"
                        @if ($post) formaction="{{ route('posts.update', $post) }}" @endif>
                    Qoralama
                </button>
            @endif
            @if ($post && $post->status === \App\Enums\PostStatus::Draft)
                <input type="hidden" name="publish" value="1" x-ref="publish" disabled>
            @endif
            <button type="submit" class="btn btn-primary" :disabled="!content.trim() || tooLong"
                    @if ($post && $post->status === \App\Enums\PostStatus::Draft) @click="$refs.publish.disabled = false" @endif>
                {{ $post && $post->isPublished() ? 'Saqlash' : 'Chop etish' }}
            </button>
        </div>
    </div>
</form>
