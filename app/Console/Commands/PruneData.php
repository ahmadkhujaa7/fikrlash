<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\PhoneVerification;
use App\Models\PostView;
use Illuminate\Console\Command;

class PruneData extends Command
{
    protected $signature = 'fikrlash:prune';

    protected $description = 'Eskirgan OTP, ko‘rishlar tarixi va o‘qilgan bildirishnomalarni tozalaydi';

    public function handle(): int
    {
        $otp = PhoneVerification::query()->where('created_at', '<', now()->subDays(2))->delete();
        $views = PostView::query()->where('last_viewed_at', '<', now()->subDays(config('fikrlash.views.retention_days')))->delete();
        $notifications = Notification::query()->whereNotNull('read_at')
            ->where('created_at', '<', now()->subDays(config('fikrlash.notifications.retention_days')))->delete();

        $this->info("OTP: {$otp}, ko‘rishlar: {$views}, bildirishnomalar: {$notifications}");
        $this->call('sanctum:prune-expired', ['--hours' => 24]);

        return self::SUCCESS;
    }
}
