<?php

namespace App\Http\Requests\Posts;

use App\Models\Post;
use App\Services\Posts\ArticleBlocks;
use App\Support\Article;
use App\Support\ValidationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

/**
 * Ikki xil post:
 *   post    — qisqa fikr: matn + (ixtiyoriy) bitta rasm;
 *   article — maqola: sarlavha + bloklar (matn orasida rasmlar, kichik sarlavhalar, iqtibos...).
 */
class CreatePostRequest extends FormRequest
{
    /** Tekshirilgan va tozalangan maqola bloklari (after() da to‘ldiriladi). */
    private ?array $articleBlocks = null;

    protected function prepareForValidation(): void
    {
        $type = $this->resolveType();

        // Veb-formada faqat matn va rasm bor; kategoriya/teglar faqat yuborilganda o‘zgaradi
        // (aks holda tahrirlash AI aniqlagan kategoriya va teglarni o‘chirib yuborardi).
        $this->merge(array_filter([
            'type' => $type,
            'content' => trim(str_replace("\r\n", "\n", (string) $this->input('content'))),
            'tags' => $this->has('tags') ? $this->normalizeTags($this->input('tags')) : null,
            'category_id' => $this->has('category_id') ? ($this->input('category_id') ?: null) : null,
        ], fn ($v, $k) => in_array($k, ['type', 'content'], true) || $this->has($k), ARRAY_FILTER_USE_BOTH));

        if ($type === Post::TYPE_ARTICLE) {
            $blocks = $this->input('blocks');
            $this->merge([
                'title' => trim(preg_replace('/\s+/u', ' ', (string) $this->input('title')) ?? ''),
                'blocks' => is_string($blocks) ? json_decode($blocks, true) : $blocks,
            ]);
        }
    }

    protected function resolveType(): string
    {
        return $this->input('type') === Post::TYPE_ARTICLE ? Post::TYPE_ARTICLE : Post::TYPE_POST;
    }

    protected function isArticle(): bool
    {
        return $this->input('type') === Post::TYPE_ARTICLE;
    }

    public function rules(): array
    {
        $rules = [
            'category_id' => ValidationRules::category(),
            'visibility' => ValidationRules::visibility(),
            'tags' => ValidationRules::tags(),
            'tags.*' => ValidationRules::tagItem(),
            'draft' => ['nullable', 'boolean'],
        ];

        if ($this->isArticle()) {
            return $rules + [
                'title' => ['required', 'string', 'min:3', 'max:'.config('fikrlash.articles.title_max')],
                'blocks' => ['required', 'array', 'min:1', 'max:'.config('fikrlash.articles.max_blocks')],
            ];
        }

        return $rules + [
            'content' => ValidationRules::postContent(),
            'image' => ValidationRules::image(),
        ];
    }

    /** Maqola bloklari: tuzilma, uzunlik va rasmlarning egasi tekshiriladi. */
    public function after(): array
    {
        return [function (Validator $validator) {
            if (! $this->isArticle() || $validator->errors()->isNotEmpty()) {
                return;
            }

            try {
                $this->articleBlocks = app(ArticleBlocks::class)->normalize($this->input('blocks'), $this->user(), $this->targetPost());
            } catch (ValidationException $e) {
                $validator->errors()->add('blocks', $e->validator->errors()->first('blocks'));

                return;
            }

            $max = (int) config('fikrlash.articles.max_length');
            if (mb_strlen(Article::plainText((string) $this->input('title'), $this->articleBlocks)) > $max) {
                $validator->errors()->add('blocks', "Maqola {$max} belgidan oshmasligi kerak.");
            }
        }];
    }

    /** Tahrirlanayotgan post (yangi post uchun — null). */
    protected function targetPost(): ?Post
    {
        return null;
    }

    public function messages(): array
    {
        return [
            'content.required' => 'Fikringizni yozing.',
            'content.max' => 'Post :max belgidan oshmasligi kerak.',
            'title.required' => 'Maqolaga sarlavha yozing.',
            'title.min' => 'Sarlavha kamida :min belgi bo‘lsin.',
            'title.max' => 'Sarlavha :max belgidan oshmasligi kerak.',
            'blocks.required' => 'Maqola matnini yozing.',
            'blocks.min' => 'Maqola matnini yozing.',
            'tags.*.regex' => 'Teg faqat harf, raqam va _ belgisidan iborat bo‘lishi mumkin.',
        ];
    }

    /** "ai, #biznes, startap" yoki ["ai","biznes"] → ["ai","biznes","startap"] */
    protected function normalizeTags(mixed $tags): array
    {
        if (is_string($tags)) {
            $tags = preg_split('/[,\s]+/u', $tags) ?: [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($t) => ltrim(trim((string) $t), '#'),
            is_array($tags) ? $tags : [],
        ))));
    }

    protected function dataKeys(): array
    {
        return ['content', 'category_id', 'visibility', 'tags', 'draft'];
    }

    public function postData(): array
    {
        $data = $this->safe()->only($this->dataKeys());
        $data['type'] = $this->resolveType();

        if ($data['type'] === Post::TYPE_ARTICLE) {
            $data['title'] = (string) $this->validated('title');
            $data['blocks'] = $this->articleBlocks ?? [];
            $data['content'] = Article::plainText($data['title'], $data['blocks']);
        }

        return $data;
    }
}
