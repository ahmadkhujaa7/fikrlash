<?php

namespace App\Console\Commands;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Console\Command;

class LiftExpiredSuspensions extends Command
{
    protected $signature = 'users:lift-suspensions';

    protected $description = 'Muddati tugagan cheklovlarni olib tashlaydi';

    public function handle(): int
    {
        $count = User::query()->where('status', UserStatus::Suspended)
            ->whereNotNull('suspended_until')->where('suspended_until', '<=', now())
            ->update(['status' => UserStatus::Active, 'suspended_until' => null]);

        $this->info("Faollashtirildi: {$count}");

        return self::SUCCESS;
    }
}
