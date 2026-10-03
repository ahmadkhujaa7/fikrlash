<?php

namespace App\Http\Requests\Posts;

use Illuminate\Foundation\Http\FormRequest;

class CreateCommentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['content' => trim(str_replace("\r\n", "\n", (string) $this->input('content')))]);
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:'.config('fikrlash.comments.max_length')],
            'parent_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return ['content.required' => 'Izoh matnini yozing.'];
    }
}
