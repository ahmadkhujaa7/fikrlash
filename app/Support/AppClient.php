<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Sayt mobil ilova ichida ochilganmi (Capacitor WebView "FikrlashApp/1.0 (android; push)" qo‘shadi).
 * Ilova ichida: brauzer bildirishnomalari o‘rniga native push, "Ilovani yuklab oling" kabi takliflar yo‘q.
 */
final class AppClient
{
    public static function isApp(?Request $request = null): bool
    {
        return str_contains((string) ($request ?? request())->userAgent(), 'FikrlashApp/');
    }

    /** android | ios | null */
    public static function platform(?Request $request = null): ?string
    {
        if (! preg_match('#FikrlashApp/[\w.\-]+ \((android|ios)#i', (string) ($request ?? request())->userAgent(), $m)) {
            return null;
        }

        return strtolower($m[1]);
    }
}
