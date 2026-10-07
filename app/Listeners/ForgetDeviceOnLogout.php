<?php

namespace App\Listeners;

use App\Http\Controllers\DeviceController;
use App\Models\DeviceToken;
use Illuminate\Auth\Events\Logout;

/** Ilova ichida akkauntdan chiqilganda — shu telefonga push yuborilmaydi. */
class ForgetDeviceOnLogout
{
    public function handle(Logout $event): void
    {
        $id = (int) request()->cookie(DeviceController::COOKIE);
        if ($id && $event->user) {
            DeviceToken::query()->whereKey($id)->where('user_id', $event->user->getAuthIdentifier())->delete();
        }
    }
}
