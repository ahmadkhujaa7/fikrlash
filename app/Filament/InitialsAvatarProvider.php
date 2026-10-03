<?php

namespace App\Filament;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Illuminate\Database\Eloquent\Model;

/** Tashqi xizmatsiz (ui-avatars.com o‘rniga) — CSP va privacy uchun mahalliy SVG. */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        $letter = e(mb_strtoupper(mb_substr((string) ($record->name ?? '?'), 0, 1)));
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40"><rect width="40" height="40" fill="#e8edfa"/>'
            .'<text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" font-family="Georgia,serif" font-size="18" font-weight="600" fill="#2447a6">'.$letter.'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
