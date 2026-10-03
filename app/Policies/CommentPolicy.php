<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    public function update(User $user, Comment $comment): bool
    {
        return $comment->isOwnedBy($user) && $user->canInteract();
    }

    /** Izoh muallifi, post muallifi yoki admin o‘chira oladi. */
    public function delete(User $user, Comment $comment): bool
    {
        return $comment->isOwnedBy($user)
            || $comment->post?->user_id === $user->id
            || $user->isAdmin();
    }

    public function interact(User $user, Comment $comment): bool
    {
        return $user->canInteract();
    }
}
