<?php

namespace App\Http\Requests\Posts;

use App\Models\Post;

class UpdatePostRequest extends CreatePostRequest
{
    /** Turi o‘zgarmaydi: qisqa fikr fikrligicha, maqola maqolaligicha qoladi. */
    protected function resolveType(): string
    {
        return $this->targetPost()?->type ?? Post::TYPE_POST;
    }

    protected function targetPost(): ?Post
    {
        $post = $this->route('post');

        return $post instanceof Post ? $post : null;
    }

    public function rules(): array
    {
        return parent::rules() + [
            'remove_image' => ['nullable', 'boolean'],
            'publish' => ['nullable', 'boolean'],
        ];
    }

    protected function dataKeys(): array
    {
        return ['content', 'category_id', 'visibility', 'tags', 'remove_image', 'publish'];
    }
}
