<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isSelf = $request->user()?->is($this->resource) ?? false;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'avatar_url' => $this->avatarUrl(),
            'bio' => $this->bio,
            'followers_count' => $this->followers_count,
            'following_count' => $this->following_count,
            'is_following' => $this->when($this->resource->hasAttribute('is_following'), fn () => (bool) $this->is_following),
            'profile_url' => $this->profileUrl(),
            'created_at' => $this->created_at?->toIso8601String(),
            // Shaxsiy ma'lumotlar faqat egasiga.
            $this->mergeWhen($isSelf, fn () => [
                'phone' => PhoneNumber::mask($this->phone),
                'email' => $this->email,
                'gender' => $this->gender?->value,
                'birth_date' => $this->birth_date?->toDateString(),
                'role' => $this->role->value,
                'status' => $this->status->value,
            ]),
        ];
    }
}
