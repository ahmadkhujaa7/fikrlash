<?php

namespace App\Http\Resources;

use App\Models\Post;
use App\Support\ContentFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Post */
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();

        return [
            'id' => $this->id,
            'content' => $this->content,
            'content_html' => ContentFormatter::toHtml($this->content),
            'image_url' => $this->imageUrl(),
            'status' => $this->status->value,
            'visibility' => $this->visibility->value,
            'author' => new UserResource($this->whenLoaded('user')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'stats' => [
                'likes' => $this->likes_count,
                'comments' => $this->comments_count,
                'views' => $this->views_count,
                'saves' => $this->saves_count,
            ],
            'viewer' => $this->when($viewer !== null, fn () => [
                'liked' => (bool) $this->getAttribute('is_liked'),
                'saved' => (bool) $this->getAttribute('is_saved'),
                'can_edit' => $viewer->can('update', $this->resource),
                'can_delete' => $viewer->can('delete', $this->resource),
            ]),
            // AI natijasi foydalanuvchiga sodda ko‘rinishda: mavzu va qisqa mazmun.
            'ai' => $this->when($this->ai_analyzed_at !== null, fn () => [
                'topic' => $this->ai_topic,
                'summary' => $this->ai_summary,
            ]),
            'url' => $this->url(),
            'published_at' => $this->published_at?->toIso8601String(),
            'edited_at' => $this->edited_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
