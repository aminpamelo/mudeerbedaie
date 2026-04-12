<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PlatformAccount;
use App\Services\TikTok\TikTokAnalyticsSyncService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncTikTokAnalytics implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 2;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public PlatformAccount $account,
        public string $type = 'all',
    ) {}

    /**
     * Execute the job.
     */
    public function handle(TikTokAnalyticsSyncService $service): void
    {
        if (! $this->account->is_active) {
            Log::warning('[SyncTikTokAnalytics] Account is not active, skipping', [
                'account_id' => $this->account->id,
            ]);

            return;
        }

        Log::info('[SyncTikTokAnalytics] Starting sync', [
            'account_id' => $this->account->id,
            'account_name' => $this->account->name,
            'type' => $this->type,
        ]);

        try {
            if (in_array($this->type, ['all', 'shop'])) {
                $service->syncShopPerformance($this->account);
            }

            if (in_array($this->type, ['all', 'videos'])) {
                $service->syncVideoPerformanceList($this->account);
            }

            if (in_array($this->type, ['all', 'products'])) {
                $service->syncProductPerformance($this->account);
            }

            $this->account->updateSyncStatus('completed', 'analytics');

            Log::info('[SyncTikTokAnalytics] Sync completed', [
                'account_id' => $this->account->id,
                'type' => $this->type,
            ]);
        } catch (Exception $e) {
            Log::error('[SyncTikTokAnalytics] Sync failed', [
                'account_id' => $this->account->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Exception $exception): void
    {
        Log::error('[SyncTikTokAnalytics] Job failed permanently', [
            'account_id' => $this->account->id,
            'error' => $exception?->getMessage(),
        ]);

        $this->account->recordSyncError($exception?->getMessage() ?? 'Unknown error');
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'tiktok-sync',
            'analytics',
            'account:'.$this->account->id,
        ];
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }
}
