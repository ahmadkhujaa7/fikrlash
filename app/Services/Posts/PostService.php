<?php

namespace App\Services\Posts;

use App\Enums\PostStatus;
use App\Enums\PostVisibility;
use App\Events\PostCreated;
use App\Jobs\AnalyzePostJob;
use App\Models\Post;
use App\Models\User;
use App\Services\Feed\ScoreCalculator;
use App\Services\Media\ImageService;
use App\Services\Social\TagService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class PostService
{
    public function __construct(
        private ImageService $images,
        private TagService $tags,
        private ScoreCalculator $scores,
    ) {}

    /**
     * @param  array{content: string, category_id?: int|null, visibility?: string|null, tags?: list<string>, draft?: bool}  $data
     */
    public function create(User $author, array $data, ?UploadedFile $image = null): Post
    {
        // Fayl tranzaksiyadan oldin saqlanadi; DB xatosi bo‘lsa — o‘chiriladi.
        $imagePath = $image ? $this->images->storePostImage($image) : null;
        $isDraft = (bool) ($data['draft'] ?? false);

        try {
            $post = DB::transaction(function () use ($author, $data, $imagePath, $isDraft) {
                $post = new Post([
                    'content' => $data['content'],
                    'category_id' => $data['category_id'] ?? null,
                    'visibility' => $data['visibility'] ?? PostVisibility::Public->value,
                    'image_path' => $imagePath,
                    'status' => $isDraft ? PostStatus::Draft : PostStatus::Published,
                    'published_at' => $isDraft ? null : now(),
                ]);
                $post->user()->associate($author);
                $post->score = $isDraft ? 0 : $this->scores->hot($post);
                $post->save();

                $this->tags->syncForPost($post, $data['tags'] ?? []);

                return $post;
            });
        } catch (Throwable $e) {
            $this->images->delete($imagePath);
            throw $e;
        }

        if ($post->isPublished()) {
            PostCreated::dispatch($post);
        }

        return $post;
    }

    /**
     * @param  array{content?: string, category_id?: int|null, visibility?: string, tags?: list<string>, remove_image?: bool, publish?: bool}  $data
     */
    public function update(Post $post, array $data, ?UploadedFile $image = null): Post
    {
        $newImage = $image ? $this->images->storePostImage($image) : null;
        $oldImage = $post->image_path;
        $wasPublished = $post->isPublished();

        try {
            DB::transaction(function () use ($post, $data, $newImage) {
                $post->fill(array_intersect_key($data, array_flip(['content', 'category_id', 'visibility'])));

                if ($newImage) {
                    $post->image_path = $newImage;
                } elseif (! empty($data['remove_image'])) {
                    $post->image_path = null;
                }

                if (($data['publish'] ?? false) && $post->status === PostStatus::Draft) {
                    $post->status = PostStatus::Published;
                    $post->published_at = now();
                    $post->score = $this->scores->hot($post);
                } elseif ($post->isPublished() && $post->isDirty('content')) {
                    $post->edited_at = now();
                }

                $post->save();
                $this->tags->syncForPost($post, $data['tags'] ?? $post->tags()->pluck('name')->all());
            });
        } catch (Throwable $e) {
            $this->images->delete($newImage);
            throw $e;
        }

        if ($oldImage && $oldImage !== $post->image_path) {
            $this->images->delete($oldImage);
        }

        if ($post->isPublished() && ! $wasPublished) {
            PostCreated::dispatch($post);
        } elseif ($post->isPublished() && $post->wasChanged('content')) {
            // Matn o‘zgardi — AI qayta tahlil qiladi (content hash bo‘yicha dublikat chaqiruv bo‘lmaydi).
            AnalyzePostJob::dispatchSafely($post->id);
        }

        return $post;
    }

    public function delete(Post $post): void
    {
        // Soft delete: admin tiklashi mumkin; rasm akkaunt to‘liq o‘chirilganda tozalanadi.
        $post->delete();
    }
}
