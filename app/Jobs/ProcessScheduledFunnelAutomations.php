<?php

namespace App\Jobs;

use App\Services\Funnel\FunnelAutomationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessScheduledFunnelAutomations implements ShouldQueue
{
    use Queueable;

    public function __construct() {}

    public function handle(FunnelAutomationService $automationService): void
    {
        $processed = $automationService->processScheduledActions();

        if ($processed > 0) {
            Log::info('FunnelAutomation: Processed scheduled actions', [
                'count' => $processed,
            ]);
        }
    }
}
