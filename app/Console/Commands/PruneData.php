<?php

namespace App\Console\Commands;

use App\Models\LoginEvent;
use App\Models\MediaUpload;
use App\Models\Notification;
use App\Models\PhoneVerification;
use App\Models\PostView;
use App\Services\Media\ImageService;
use Illuminate\Console\Command;

class PruneData extends Command
{
    protected $signature = 'fikrlash:prune';

    protected $description = 'Eskirgan OTP, ko‘rishlar tarixi, o‘qilgan bildirishnomalar va ishlatilmagan rasmlarni tozalaydi';

    public function handle(ImageService $images): int
    {
        $otp = PhoneVerification::query()->where('created_at', '<', now()->subDays(2))->delete();
        $views = PostView::query()->where('last_viewed_at', '<', now()->subDays(config('fikrlash.views.retention_days')))->delete();
        $notifications = Notification::query()->whereNotNull('read_at')
            ->where('created_at', '<', now()->subDays(config('fikrlash.notifications.retention_days')))->delete();

        $logins = LoginEvent::query()->where('created_at', '<', now()->subDays(180))->delete();

        // Maqolaga yuklanib, lekin hech qachon saqlanmagan rasmlar (fayli bilan).
        $media = 0;
        MediaUpload::query()->unattached()
            ->where('created_at', '<', now()->subDays(config('fikrlash.articles.unattached_ttl_days')))
            ->chunkById(200, function ($uploads) use ($images, &$media) {
                foreach ($uploads as $upload) {
                    $images->delete($upload->path);
                    $upload->delete();
                    $media++;
                }
            });

        $this->info("OTP: {$otp}, ko‘rishlar: {$views}, bildirishnomalar: {$notifications}, kirishlar tarixi: {$logins}, rasmlar: {$media}");
        $this->call('sanctum:prune-expired', ['--hours' => 24]);

        return self::SUCCESS;
    }
}
