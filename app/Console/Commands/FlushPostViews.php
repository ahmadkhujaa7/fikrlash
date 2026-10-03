<?php

namespace App\Console\Commands;

use App\Services\Feed\ViewRecorder;
use Illuminate\Console\Command;

class FlushPostViews extends Command
{
    protected $signature = 'views:flush';

    protected $description = 'Redis buffer\'dagi post ko‘rishlarini DB\'ga yozadi';

    public function handle(ViewRecorder $views): int
    {
        $this->info('Yozildi: '.$views->flush().' ta ko‘rish');

        return self::SUCCESS;
    }
}
