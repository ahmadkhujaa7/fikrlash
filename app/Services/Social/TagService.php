<?php

namespace App\Services\Social;

use App\Models\Post;
use App\Models\Tag;
use App\Support\ContentFormatter;
use App\Support\TextNormalizer;

class TagService
{
    private const MAX_TAGS_PER_POST = 10;

    /**
     * Post teglarini sinxronlash: kontentdagi #hashtaglar + qo‘lda kiritilgan teglar.
     *
     * @param  list<string>  $explicit
     */
    public function syncForPost(Post $post, array $explicit = []): void
    {
        $tags = [];
        foreach ($explicit as $name) {
            $slug = TextNormalizer::tagSlug($name);
            if ($slug !== '' && mb_strlen($slug) <= 50) {
                $tags[$slug] = ltrim(trim($name), '#');
            }
        }
        $tags += ContentFormatter::hashtags($post->content);
        $tags = array_slice($tags, 0, self::MAX_TAGS_PER_POST, true);

        if ($tags === []) {
            $post->tags()->sync([]);

            return;
        }

        $now = now();
        Tag::query()->insertOrIgnore(array_map(
            fn ($slug, $name) => ['slug' => $slug, 'name' => mb_substr($name, 0, 50), 'created_at' => $now, 'updated_at' => $now],
            array_keys($tags), $tags,
        ));

        $post->tags()->sync(Tag::query()->whereIn('slug', array_keys($tags))->pluck('id'));
    }

    /**
     * AI kalit so‘zlaridan teglar qo‘shish (foydalanuvchi teg yozmasa ham tavsiya tizimi postni "tushunadi").
     * Faqat bitta so‘zli, 3–30 harfli kalit so‘zlar; mavjud teglar o‘chirilmaydi; postda jami 4 tadan oshmaydi.
     *
     * @param  list<string>  $keywords
     */
    public function attachFromKeywords(Post $post, array $keywords, int $maxTotal = 4): void
    {
        $existing = $post->tags()->pluck('tags.id')->all();
        $room = $maxTotal - count($existing);
        if ($room <= 0) {
            return;
        }

        $tags = [];
        foreach ($keywords as $keyword) {
            $keyword = trim($keyword);
            $slug = TextNormalizer::tagSlug($keyword);
            if (preg_match('/\s/u', $keyword) || mb_strlen($slug) < 3 || mb_strlen($slug) > 30) {
                continue;
            }
            $tags[$slug] = $keyword;
            if (count($tags) >= $room) {
                break;
            }
        }
        if ($tags === []) {
            return;
        }

        $now = now();
        Tag::query()->insertOrIgnore(array_map(
            fn ($slug, $name) => ['slug' => $slug, 'name' => mb_substr($name, 0, 50), 'created_at' => $now, 'updated_at' => $now],
            array_keys($tags), $tags,
        ));
        $post->tags()->syncWithoutDetaching(Tag::query()->whereIn('slug', array_keys($tags))->pluck('id'));
    }
}
