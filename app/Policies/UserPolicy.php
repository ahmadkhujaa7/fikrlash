<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function follow(User $user, User $target): bool
    {
        return $user->canInteract() && ! $user->is($target);
    }
}
