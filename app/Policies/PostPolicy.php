<?php

namespace App\Policies;

use App\Enums\PostVisibility;
use App\Models\Follow;
use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function view(?User $user, Post $post): bool
    {
        if ($post->trashed()) {
            return (bool) $user?->isAdmin();
        }

        if ($post->isOwnedBy($user) || $user?->isAdmin()) {
            return true;
        }

        if (! $post->isPublished() || ! in_array($post->user?->status, User::VISIBLE_STATUSES, true) || $post->user?->trashed()) {
            return false;
        }

        if ($post->visibility === PostVisibility::Followers) {
            return $user !== null && Follow::query()->where(['follower_id' => $user->id, 'following_id' => $post->user_id])->exists();
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->canInteract();
    }

    public function update(User $user, Post $post): bool
    {
        return $post->isOwnedBy($user) && $user->canInteract() && ! $post->trashed();
    }

    public function delete(User $user, Post $post): bool
    {
        return $post->isOwnedBy($user) || $user->isAdmin();
    }

    /** Like, save, comment, report — ko‘ra oladigan va faol foydalanuvchi. */
    public function interact(User $user, Post $post): bool
    {
        return $user->canInteract() && $post->isPublished() && $this->view($user, $post);
    }
}
