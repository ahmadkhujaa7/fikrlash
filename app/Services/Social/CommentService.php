<?php

namespace App\Services\Social;

use App\Enums\CommentStatus;
use App\Events\CommentCreated;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Services\Feed\TasteService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommentService
{
    public function __construct(private TasteService $taste) {}

    public function create(User $author, Post $post, string $content, ?int $parentId = null): Comment
    {
        $parent = null;
        if ($parentId) {
            $parent = Comment::query()->where('post_id', $post->id)->published()->find($parentId);
            if (! $parent) {
                throw ValidationException::withMessages(['parent_id' => 'Javob berilayotgan izoh topilmadi.']);
            }
        }

        $comment = DB::transaction(function () use ($author, $post, $content, $parent) {
            $comment = new Comment(['content' => $content]);
            $comment->post()->associate($post);
            $comment->user()->associate($author);

            if ($parent) {
                // Bir darajali daraxt: javobga javob ham asosiy izohga bog‘lanadi.
                $comment->parent_id = $parent->parent_id ?? $parent->id;
                $comment->reply_to_user_id = $parent->user_id;
            }

            $comment->save();

            Post::query()->whereKey($post->id)->increment('comments_count');
            if ($comment->parent_id) {
                Comment::query()->whereKey($comment->parent_id)->increment('replies_count');
            }

            return $comment;
        });

        $post->comments_count++;
        $this->taste->engage($author->id, $post, 'comment');
        CommentCreated::dispatch($comment);

        return $comment;
    }

    public function update(Comment $comment, string $content): Comment
    {
        $comment->update(['content' => $content]);

        return $comment;
    }

    /** Asosiy izoh o‘chirilsa, javoblari ham o‘chadi. */
    public function delete(Comment $comment): void
    {
        DB::transaction(function () use ($comment) {
            $removed = 0;

            if ($comment->parent_id === null) {
                $removed += Comment::query()->where('parent_id', $comment->id)->published()->count();
                Comment::query()->where('parent_id', $comment->id)->update(['deleted_at' => now()]);
            } else {
                Comment::query()->whereKey($comment->parent_id)->where('replies_count', '>', 0)->decrement('replies_count');
            }

            if ($comment->status === CommentStatus::Published) {
                $removed++;
            }
            $comment->delete();

            if ($removed > 0) {
                Post::withTrashed()->whereKey($comment->post_id)
                    ->update(['comments_count' => DB::raw('CASE WHEN comments_count >= '.$removed.' THEN comments_count - '.$removed.' ELSE 0 END')]);
            }
        });
    }
}
