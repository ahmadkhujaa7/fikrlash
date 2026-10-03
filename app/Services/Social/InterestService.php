<?php

namespace App\Services\Social;

use App\Models\User;
use App\Models\UserInterest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Foydalanuvchi qiziqishlari: har bir kategoriya bo‘yicha vazn.
 * Like, save, comment, ko‘rish va o‘qish vaqti vaznni oshiradi; haftalik decay eskisini kamaytiradi.
 */
class InterestService
{
    public function bump(int $userId, ?int $categoryId, float $amount): void
    {
        if (! $categoryId || $amount <= 0) {
            return;
        }

        DB::table('user_interests')->upsert(
            [['user_id' => $userId, 'category_id' => $categoryId, 'weight' => $amount, 'updated_at' => now()]],
            ['user_id', 'category_id'],
            ['weight' => DB::raw('user_interests.weight + '.sprintf('%.4F', $amount)), 'updated_at' => now()],
        );

        Cache::forget($this->key($userId));
    }

    public function followCategory(User $user, int $categoryId): void
    {
        $this->bump($user->id, $categoryId, (float) config('fikrlash.interests.follow_category'));
        UserInterest::query()->where(['user_id' => $user->id, 'category_id' => $categoryId])->update(['followed_at' => now()]);
    }

    public function unfollowCategory(User $user, int $categoryId): void
    {
        UserInterest::query()->where(['user_id' => $user->id, 'category_id' => $categoryId])
            ->update(['followed_at' => null, 'weight' => DB::raw('weight / 2')]);
        Cache::forget($this->key($user->id));
    }

    /** @return array<int, float> category_id => 0..1 oralig‘idagi normallashtirilgan vazn */
    public function normalizedMap(int $userId): array
    {
        return Cache::remember($this->key($userId), now()->addMinutes(10), function () use ($userId) {
            $weights = UserInterest::query()->where('user_id', $userId)->pluck('weight', 'category_id')->all();
            $max = max([1.0, ...array_values($weights)]);

            return array_map(fn ($w) => round($w / $max, 4), $weights);
        });
    }

    /** @return list<int> */
    public function followedCategoryIds(int $userId): array
    {
        return UserInterest::query()->where('user_id', $userId)->whereNotNull('followed_at')->pluck('category_id')->all();
    }

    /** Haftalik: eski qiziqishlar asta-sekin so‘nadi. */
    public function decay(): void
    {
        $factor = (float) config('fikrlash.interests.weekly_decay');
        UserInterest::query()->update(['weight' => DB::raw('weight * '.sprintf('%.4F', $factor))]);
        UserInterest::query()->whereNull('followed_at')->where('weight', '<', 0.1)->delete();
    }

    private function key(int $userId): string
    {
        return "interests:{$userId}";
    }
}
