<?php

namespace App\Jobs;

use App\Services\WhatsApp\WhatsAppCostService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncWhatsAppCostAnalyticsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * Execute the job.
     */
    public function handle(WhatsAppCostService $costService): void
    {
        Log::info('SyncWhatsAppCostAnalyticsJob: Starting daily sync');

        $count = $costService->syncDailyAnalytics();

        Log::info('SyncWhatsAppCostAnalyticsJob: Sync completed', [
            'records_synced' => $count,
        ]);
    }
}
