<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Gender;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\AvatarRequest;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Services\Account\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileSettingsController extends Controller
{
    public function __construct(private AccountService $accounts) {}

    public function edit(Request $request): View
    {
        return view('settings.profile', ['user' => $request->user(), 'genders' => Gender::options()]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $this->accounts->updateProfile($request->user(), $request->validated());

        return back()->with('toast', 'Profil saqlandi.');
    }

    public function updateAvatar(AvatarRequest $request): RedirectResponse
    {
        $this->accounts->updateAvatar($request->user(), $request->file('avatar'));

        return back()->with('toast', 'Rasm yangilandi.');
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        $this->accounts->removeAvatar($request->user());

        return back()->with('toast', 'Rasm olib tashlandi.');
    }
}
