<?php

namespace App\Http\Requests\Posts;

use App\Support\ValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class CreatePostRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'content' => trim(str_replace("\r\n", "\n", (string) $this->input('content'))),
            'tags' => $this->normalizeTags($this->input('tags')),
            'category_id' => $this->input('category_id') ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'content' => ValidationRules::postContent(),
            'category_id' => ValidationRules::category(),
            'visibility' => ValidationRules::visibility(),
            'tags' => ValidationRules::tags(),
            'tags.*' => ValidationRules::tagItem(),
            'image' => ValidationRules::image(),
            'draft' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'Fikringizni yozing.',
            'content.max' => 'Post :max belgidan oshmasligi kerak.',
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

    public function postData(): array
    {
        return $this->safe()->only(['content', 'category_id', 'visibility', 'tags', 'draft']);
    }
}
