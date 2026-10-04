<?php

namespace App\Filament;

use App\Models\User;
use Filament\AvatarProviders\Contracts\AvatarProvider;
use Illuminate\Database\Eloquent\Model;

/**
 * Tashqi xizmatsiz (ui-avatars.com o‘rniga) — CSP va privacy uchun mahalliy SVG.
 * Rang saytdagidek: har bir foydalanuvchining doimiy koshin rangi.
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public const TONES = ['#2343b8', '#1b7a74', '#b3334c', '#b8741a', '#56762c', '#67408c'];

    public function get(Model $record): string
    {
        $letter = e(mb_strtoupper(mb_substr((string) ($record->name ?? '?'), 0, 1)));
        $color = self::TONES[$record instanceof User ? $record->tone() : 0];
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40"><rect width="40" height="40" fill="'.$color.'"/>'
            .'<text x="50%" y="55%" dominant-baseline="middle" text-anchor="middle" font-family="Georgia,serif" font-size="18" font-weight="500" fill="#fff">'.$letter.'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /** Admin jadvallari uchun: rasm bo‘lsa — rasm, bo‘lmasa — rangli bosh harf. */
    public static function urlFor(?User $user): string
    {
        return $user?->avatarUrl() ?? (new self)->get($user ?? new User);
    }
}
