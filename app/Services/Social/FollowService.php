<?php

namespace App\Services\Social;

use App\Events\UserFollowed;
use App\Models\Follow;
use App\Models\User;
use App\Services\Feed\TasteService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FollowService
{
    public function __construct(private TasteService $taste) {}

    public function follow(User $follower, User $target): bool
    {
        if ($follower->is($target)) {
            throw ValidationException::withMessages(['user' => 'O‘zingizga obuna bo‘lib bo‘lmaydi.']);
        }

        $added = DB::transaction(function () use ($follower, $target) {
            $inserted = Follow::query()->insertOrIgnore([
                'follower_id' => $follower->id, 'following_id' => $target->id, 'created_at' => now(),
            ]);

            if ($inserted) {
                User::query()->whereKey($target->id)->increment('followers_count');
                User::query()->whereKey($follower->id)->increment('following_count');
            }

            return $inserted > 0;
        });

        if ($added) {
            $target->followers_count++;
            $follower->following_count++;
            $this->taste->followAuthor($follower->id, $target->id);
            UserFollowed::dispatch($follower, $target);
        }

        return $added;
    }

    public function unfollow(User $follower, User $target): bool
    {
        $removed = DB::transaction(function () use ($follower, $target) {
            $deleted = Follow::query()->where(['follower_id' => $follower->id, 'following_id' => $target->id])->delete();

            if ($deleted) {
                User::query()->whereKey($target->id)->where('followers_count', '>', 0)->decrement('followers_count');
                User::query()->whereKey($follower->id)->where('following_count', '>', 0)->decrement('following_count');
            }

            return $deleted > 0;
        });

        if ($removed) {
            $target->followers_count = max(0, $target->followers_count - 1);
            $follower->following_count = max(0, $follower->following_count - 1);
        }

        return $removed;
    }

    /** @return list<int> */
    public function followingIds(User $user): array
    {
        return Follow::query()->where('follower_id', $user->id)->pluck('following_id')->all();
    }
}
