<?php

namespace App\Http\Controllers\Settings;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Qaysi bildirishnomalar kelsin + qurilmadagi ruxsatlar (bildirishnoma, mikrofon, kamera, joylashuv). */
class NotificationSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.notifications', [
            'types' => NotificationType::optional(),
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $values = array_map(fn (NotificationType $t) => $t->value, NotificationType::optional());
        $data = $request->validate([
            'enabled' => ['nullable', 'array'],
            'enabled.*' => ['string', 'in:'.implode(',', $values)],
        ]);

        $enabled = $data['enabled'] ?? [];
        $muted = array_values(array_diff($values, $enabled));
        $settings = $request->user()->notification_settings ?? [];
        $settings['muted'] = $muted;
        $request->user()->forceFill(['notification_settings' => $settings])->save();

        return back()->with('toast', 'Bildirishnoma sozlamalari saqlandi.');
    }

    public function permissions(): View
    {
        return view('settings.permissions');
    }
}
