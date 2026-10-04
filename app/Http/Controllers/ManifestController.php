<?php

namespace App\Http\Controllers;

use App\Support\Branding;
use Illuminate\Http\JsonResponse;

/**
 * Veb-ilova manifesti (PWA): telefonda "Bosh ekranga qo‘shish" — sayt alohida ilova kabi,
 * brauzer panelisiz ochiladi. Nomi admin panelidagi sayt nomidan olinadi.
 */
class ManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $name = Branding::name();

        return response()->json([
            'id' => '/',
            'name' => $name,
            'short_name' => mb_strimwidth($name, 0, 12, ''),
            'description' => $name.' — o‘zbek tilidagi fikr va maqolalar platformasi.',
            'lang' => 'uz',
            'dir' => 'ltr',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'display_override' => ['standalone', 'minimal-ui'],
            'background_color' => '#f1f2f6',
            'theme_color' => '#ffffff',
            'categories' => ['social', 'news', 'education'],
            'icons' => [
                ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/icons/maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => 'Fikr yozish', 'url' => '/compose', 'icons' => [['src' => '/icons/icon-192.png', 'sizes' => '192x192']]],
                ['name' => 'Maqola yozish', 'url' => '/compose?type=article', 'icons' => [['src' => '/icons/icon-192.png', 'sizes' => '192x192']]],
                ['name' => 'Qidiruv', 'url' => '/search', 'icons' => [['src' => '/icons/icon-192.png', 'sizes' => '192x192']]],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
