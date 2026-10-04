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
}
