<?php

namespace App\Listeners;

use App\Events\PostReported;
use App\Services\Moderation\ModerationService;

class EvaluateReport
{
    public function __construct(private ModerationService $moderation) {}

    public function handle(PostReported $event): void
    {
        $this->moderation->evaluateReports($event->report);
    }
}
