<?php

namespace App\Http\Resources;

use App\Models\Notification;
use App\Support\NotificationPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Notification */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $presented = NotificationPresenter::present($this->resource);

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'text' => $presented['text'],
            'url' => $presented['url'],
            'excerpt' => $presented['excerpt'],
            'actor' => $this->actor ? [
                'name' => $this->actor->name,
                'username' => $this->actor->username,
                'avatar_url' => $this->actor->avatarUrl(),
            ] : null,
            'read' => $this->read_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
