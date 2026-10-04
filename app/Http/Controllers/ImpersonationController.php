<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Users\UserResource;
use App\Services\Security\ImpersonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    /** Foydalanuvchi nomidan ko‘rishni tugatish — admin o‘z akkauntiga va foydalanuvchi sahifasiga qaytadi. */
    public function stop(Request $request, ImpersonationService $impersonation): RedirectResponse
    {
        $target = $impersonation->stop($request);

        return $target
            ? redirect(UserResource::getUrl('view', ['record' => $target]))
            : redirect()->route('login');
    }
}
