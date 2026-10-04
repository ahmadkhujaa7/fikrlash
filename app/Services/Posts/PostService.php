<?php

namespace App\Services\Posts;

use App\Enums\PostStatus;
use App\Enums\PostVisibility;
use App\Events\PostCreated;
use App\Jobs\AnalyzePostJob;
use App\Models\MediaUpload;
use App\Models\Post;
use App\Models\User;
use App\Services\Feed\ScoreCalculator;
use App\Services\Media\ImageService;
use App\Services\Social\TagService;
use App\Support\Article;
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
     * @param  array{type?: string, content: string, title?: string, blocks?: list<array>, category_id?: int|null, visibility?: string|null, tags?: list<string>, draft?: bool}  $data
     */
    public function create(User $author, array $data, ?UploadedFile $image = null): Post
    {
        $isArticle = ($data['type'] ?? Post::TYPE_POST) === Post::TYPE_ARTICLE;

        // Fayl tranzaksiyadan oldin saqlanadi; DB xatosi bo‘lsa — o‘chiriladi.
        // Maqolada rasmlar oldindan yuklangan (media_uploads); muqova — birinchi rasm.
        $imagePath = ! $isArticle && $image ? $this->images->storePostImage($image) : null;
        $isDraft = (bool) ($data['draft'] ?? false);

        try {
            $post = DB::transaction(function () use ($author, $data, $imagePath, $isDraft, $isArticle) {
                $post = new Post([
                    'type' => $isArticle ? Post::TYPE_ARTICLE : Post::TYPE_POST,
                    'title' => $isArticle ? $data['title'] : null,
                    'blocks' => $isArticle ? $data['blocks'] : null,
                    'content' => $data['content'],
                    'category_id' => $data['category_id'] ?? null,
                    'visibility' => $data['visibility'] ?? PostVisibility::Public->value,
                    'image_path' => $isArticle ? Article::cover($data['blocks']) : $imagePath,
                    'status' => $isDraft ? PostStatus::Draft : PostStatus::Published,
                    'published_at' => $isDraft ? null : now(),
                ]);
                $post->user()->associate($author);
                $post->score = $isDraft ? 0 : $this->scores->hot($post);
                $post->save();

                $this->attachMedia($post);
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
     * @param  array{content?: string, title?: string, blocks?: list<array>, category_id?: int|null, visibility?: string, tags?: list<string>, remove_image?: bool, publish?: bool}  $data
     */
    public function update(Post $post, array $data, ?UploadedFile $image = null): Post
    {
        if ($post->isArticle()) {
            return $this->updateArticle($post, $data);
        }

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

                $this->applyPublishState($post, $data);
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

        $this->afterUpdate($post, $wasPublished);

        return $post;
    }

    /** Maqola: bloklar almashtiriladi; olib tashlangan rasmlar fayli bilan o‘chiriladi. */
    private function updateArticle(Post $post, array $data): Post
    {
        $wasPublished = $post->isPublished();
        $oldImages = Article::images($post->blocks ?? []);

        DB::transaction(function () use ($post, $data) {
            $post->fill(array_intersect_key($data, array_flip(['title', 'blocks', 'content', 'category_id', 'visibility'])));
            $post->image_path = Article::cover($post->blocks ?? []);

            $this->applyPublishState($post, $data);
            $post->save();

            $this->attachMedia($post);
            $this->tags->syncForPost($post, $data['tags'] ?? $post->tags()->pluck('name')->all());
        });

        $removed = array_diff($oldImages, Article::images($post->blocks ?? []));
        if ($removed !== []) {
            MediaUpload::query()->where('post_id', $post->id)->whereIn('path', $removed)->delete();
            array_map(fn ($path) => $this->images->delete($path), $removed);
        }

        $this->afterUpdate($post, $wasPublished);

        return $post;
    }

    /** Qoralamani chop etish yoki chop etilgan postda "tahrirlangan" belgisi. */
    private function applyPublishState(Post $post, array $data): void
    {
        if (($data['publish'] ?? false) && $post->status === PostStatus::Draft) {
            $post->status = PostStatus::Published;
            $post->published_at = now();
            $post->score = $this->scores->hot($post);
        } elseif ($post->isPublished() && $post->isDirty(['content', 'title', 'blocks'])) {
            $post->edited_at = now();
        }
    }

    private function afterUpdate(Post $post, bool $wasPublished): void
    {
        if ($post->isPublished() && ! $wasPublished) {
            PostCreated::dispatch($post);
        } elseif ($post->isPublished() && $post->wasChanged('content')) {
            // Matn o‘zgardi — AI qayta tahlil qiladi (content hash bo‘yicha dublikat chaqiruv bo‘lmaydi).
            AnalyzePostJob::dispatchSafely($post->id);
        }
    }

    /** Maqoladagi yuklangan rasmlarni shu postga biriktiradi (endi ular avtomatik tozalanmaydi). */
    private function attachMedia(Post $post): void
    {
        $paths = $post->isArticle() ? Article::images($post->blocks ?? []) : [];
        if ($paths !== []) {
            MediaUpload::query()->where('user_id', $post->user_id)->whereNull('post_id')->whereIn('path', $paths)->update(['post_id' => $post->id]);
        }
    }

    public function delete(Post $post): void
    {
        // Soft delete: admin tiklashi mumkin; rasm akkaunt to‘liq o‘chirilganda tozalanadi.
        $post->delete();
    }
}
