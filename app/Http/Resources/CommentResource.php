<?php

namespace App\Http\Resources;

use App\Models\Comment;
use App\Support\ContentFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Comment */
class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();

        return [
            'id' => $this->id,
            'post_id' => $this->post_id,
            'parent_id' => $this->parent_id,
            'content' => $this->content,
            'content_html' => ContentFormatter::toHtml($this->content),
            'author' => new UserResource($this->whenLoaded('user')),
            'reply_to' => $this->whenLoaded('replyToUser', fn () => $this->replyToUser ? ['username' => $this->replyToUser->username] : null),
            'likes_count' => $this->likes_count,
            'replies_count' => $this->replies_count,
            'replies' => CommentResource::collection($this->whenLoaded('replies')),
            'viewer' => $this->when($viewer !== null, fn () => [
                'liked' => (bool) $this->getAttribute('is_liked'),
                'can_delete' => $viewer->can('delete', $this->resource),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
