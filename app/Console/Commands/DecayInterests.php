<?php

namespace App\Console\Commands;

use App\Services\Social\InterestService;
use Illuminate\Console\Command;

class DecayInterests extends Command
{
    protected $signature = 'interests:decay';

    protected $description = 'Foydalanuvchi qiziqishlari vaznini haftalik kamaytiradi';

    public function handle(InterestService $interests): int
    {
        $interests->decay();

        return self::SUCCESS;
    }
}
