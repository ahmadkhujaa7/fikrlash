<?php

namespace App\Services\Social;

use App\Events\PostLiked;
use App\Models\Comment;
use App\Models\CommentLike;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\SavedPost;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Like / save. Race condition'ga chidamli:
 * unique(user_id, post_id) + insertOrIgnore → hisoblagich faqat haqiqatan yangi qator qo‘shilganda o‘zgaradi.
 */
class InteractionService
{
    public function __construct(private InterestService $interests) {}

    public function likePost(User $user, Post $post): bool
    {
        $added = DB::transaction(function () use ($user, $post) {
            $inserted = PostLike::query()->insertOrIgnore(['post_id' => $post->id, 'user_id' => $user->id, 'created_at' => now()]);
            if ($inserted) {
                Post::query()->whereKey($post->id)->increment('likes_count');
            }

            return $inserted > 0;
        });

        if ($added) {
            $post->likes_count++;
            $this->interests->bump($user->id, $post->category_id, (float) config('fikrlash.interests.like'));
            PostLiked::dispatch($post, $user);
        }

        return $added;
    }

    public function unlikePost(User $user, Post $post): bool
    {
        $removed = DB::transaction(function () use ($user, $post) {
            $deleted = PostLike::query()->where(['post_id' => $post->id, 'user_id' => $user->id])->delete();
            if ($deleted) {
                Post::query()->whereKey($post->id)->where('likes_count', '>', 0)->decrement('likes_count');
            }

            return $deleted > 0;
        });

        if ($removed) {
            $post->likes_count = max(0, $post->likes_count - 1);
        }

        return $removed;
    }

    public function savePost(User $user, Post $post): bool
    {
        $added = DB::transaction(function () use ($user, $post) {
            $inserted = SavedPost::query()->insertOrIgnore(['post_id' => $post->id, 'user_id' => $user->id, 'created_at' => now()]);
            if ($inserted) {
                Post::query()->whereKey($post->id)->increment('saves_count');
            }

            return $inserted > 0;
        });

        if ($added) {
            $post->saves_count++;
            $this->interests->bump($user->id, $post->category_id, (float) config('fikrlash.interests.save'));
        }

        return $added;
    }

    public function unsavePost(User $user, Post $post): bool
    {
        $removed = DB::transaction(function () use ($user, $post) {
            $deleted = SavedPost::query()->where(['post_id' => $post->id, 'user_id' => $user->id])->delete();
            if ($deleted) {
                Post::query()->whereKey($post->id)->where('saves_count', '>', 0)->decrement('saves_count');
            }

            return $deleted > 0;
        });

        if ($removed) {
            $post->saves_count = max(0, $post->saves_count - 1);
        }

        return $removed;
    }

    public function likeComment(User $user, Comment $comment): bool
    {
        return DB::transaction(function () use ($user, $comment) {
            $inserted = CommentLike::query()->insertOrIgnore(['comment_id' => $comment->id, 'user_id' => $user->id, 'created_at' => now()]);
            if ($inserted) {
                Comment::query()->whereKey($comment->id)->increment('likes_count');
                $comment->likes_count++;
            }

            return $inserted > 0;
        });
    }

    public function unlikeComment(User $user, Comment $comment): bool
    {
        return DB::transaction(function () use ($user, $comment) {
            $deleted = CommentLike::query()->where(['comment_id' => $comment->id, 'user_id' => $user->id])->delete();
            if ($deleted) {
                Comment::query()->whereKey($comment->id)->where('likes_count', '>', 0)->decrement('likes_count');
                $comment->likes_count = max(0, $comment->likes_count - 1);
            }

            return $deleted > 0;
        });
    }
}
