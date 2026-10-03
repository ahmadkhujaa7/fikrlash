<?php

namespace App\Http\Requests\Posts;

class UpdatePostRequest extends CreatePostRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'remove_image' => ['nullable', 'boolean'],
            'publish' => ['nullable', 'boolean'],
        ];
    }

    public function postData(): array
    {
        return $this->safe()->only(['content', 'category_id', 'visibility', 'tags', 'remove_image', 'publish']);
    }
}
