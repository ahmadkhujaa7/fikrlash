<?php

namespace App\Console\Commands;

use App\Services\Feed\TasteService;
use Illuminate\Console\Command;

class DecayInterests extends Command
{
    protected $signature = 'interests:decay';

    protected $description = 'Foydalanuvchi qiziqishlari vaznini haftalik kamaytiradi';

    public function handle(TasteService $taste): int
    {
        $taste->decay();

        return self::SUCCESS;
    }
}
