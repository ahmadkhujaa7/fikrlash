<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/** SEO: ochiq postlar xaritasi (1 soat keshlanadi). Mavzular foydalanuvchiga ko‘rsatilmaydi. */
class SitemapController extends Controller
{
    private const LIMIT = 5000;

    public function __invoke(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHour(), function () {
            $urls = [['loc' => route('home'), 'lastmod' => now()]];

            Post::query()->forFeed(null)->where('posts.visibility', 'public')
                ->latest('published_at')->limit(self::LIMIT)
                ->get(['posts.id', 'posts.published_at', 'posts.edited_at'])
                ->each(function (Post $post) use (&$urls) {
                    $urls[] = ['loc' => route('posts.show', $post), 'lastmod' => $post->edited_at ?? $post->published_at];
                });

            $body = collect($urls)->map(fn ($u) => '<url><loc>'.e($u['loc']).'</loc>'
                .($u['lastmod'] ? '<lastmod>'.$u['lastmod']->toAtomString().'</lastmod>' : '').'</url>')->implode('');

            return '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$body.'</urlset>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
