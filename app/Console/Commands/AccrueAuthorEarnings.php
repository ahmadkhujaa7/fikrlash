<?php

namespace App\Console\Commands;

use App\Services\Monetization\MonetizationService;
use Illuminate\Console\Command;

/** Mualliflar daromadini hisoblash (yangi ko‘rishlar → balans). Rejalashtiruvchi har 10 daqiqada ishlatadi. */
class AccrueAuthorEarnings extends Command
{
    protected $signature = 'monetization:accrue';

    protected $description = 'Muallif maqolalarining yangi ko‘rishlarini daromadga aylantiradi';

    public function handle(MonetizationService $monetization): int
    {
        $views = $monetization->accrue();
        $this->info("Hisoblangan ko‘rishlar: {$views}");

        return self::SUCCESS;
    }
}
