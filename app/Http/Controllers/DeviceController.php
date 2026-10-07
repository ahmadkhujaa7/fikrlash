<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Mobil ilova push tokenini ro‘yxatdan o‘tkazish / o‘chirish.
 * Veb (ilova ichidagi sayt, sessiya bilan) va API (Sanctum token) — ikkalasi ham shu yerga keladi.
 * Ilova ichida chiqib ketilganda token "fk_device" cookie orqali topiladi va o‘chiriladi (Logout hodisasi).
 */
class DeviceController extends Controller
{
    public const COOKIE = 'fk_device';

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:512'],
            'platform' => ['required', Rule::in(DeviceToken::PLATFORMS)],
            'app_version' => ['nullable', 'string', 'max:32'],
        ]);

        // Shu telefonda boshqa akkaunt bilan kirilgan bo‘lsa — token yangi egasiga o‘tadi.
        $device = DeviceToken::query()->updateOrCreate(
            ['token' => $data['token']],
            ['user_id' => $request->user()->id, 'platform' => $data['platform'], 'app_version' => $data['app_version'] ?? null, 'last_seen_at' => now()],
        );

        return response()->json(['ok' => true])
            ->cookie(self::COOKIE, (string) $device->id, 60 * 24 * 365 * 5);
    }

    public function destroy(Request $request): JsonResponse
    {
        $token = $request->input('token');
        $query = DeviceToken::query()->where('user_id', $request->user()->id);

        $deleted = $token
            ? $query->where('token', $token)->delete()
            : ($request->cookie(self::COOKIE) ? $query->whereKey((int) $request->cookie(self::COOKIE))->delete() : 0);

        return response()->json(['ok' => true, 'deleted' => $deleted])->withoutCookie(self::COOKIE);
    }
}
