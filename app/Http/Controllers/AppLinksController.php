<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Havolalar ilovada ochilishi uchun: Android App Links (assetlinks.json) va iOS Universal Links
 * (apple-app-site-association). Sozlamalar — config/fikrlash.php → mobile.
 */
class AppLinksController extends Controller
{
    public function android(): JsonResponse
    {
        $fingerprints = config('fikrlash.mobile.android_sha256');
        abort_if(empty($fingerprints), 404);

        return response()->json([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => config('fikrlash.mobile.android_package'),
                'sha256_cert_fingerprints' => array_map('strtoupper', $fingerprints),
            ],
        ]])->header('Cache-Control', 'public, max-age=3600');
    }

    public function apple(): JsonResponse
    {
        $appId = config('fikrlash.mobile.ios_app_id');
        abort_unless($appId, 404);

        return response()->json([
            'applinks' => [
                'details' => [[
                    'appIDs' => [$appId],
                    'components' => [
                        ['/' => '/admin*', 'exclude' => true],
                        ['/' => '/api/*', 'exclude' => true],
                        ['/' => '/*'],
                    ],
                ]],
            ],
            'webcredentials' => ['apps' => [$appId]],
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}
