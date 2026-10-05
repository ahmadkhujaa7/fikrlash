<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

/** Suhbatni faqat uning ikki ishtirokchisi ko‘radi (admin ham emas — bu shaxsiy yozishma). */
class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->hasParticipant($user);
    }
}
