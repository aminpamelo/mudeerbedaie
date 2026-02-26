<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PlatformAccount;
use App\Services\TikTok\TikTokOrderSyncService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncTikTokOrders implements ShouldQueue
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
     *
     * @param  array{
     *     create_time_from?: int,
     *     create_time_to?: int,
     *     update_time_from?: int,
     *     update_time_to?: int,
     *     order_status?: string,
     *     page_size?: int
     * }  $filters
     */
    public function __construct(
        public PlatformAccount $account,
        public array $filters = [],
        public bool $notifyOnCompletion = false
    ) {
        // Use default queue so it works with standard queue worker
    }

    /**
     * Execute the job.
     */
    public function handle(TikTokOrderSyncService $syncService): void
    {
        Log::info('[TikTok Order Sync Job] Starting sync', [
            'account_id' => $this->account->id,
            'account_name' => $this->account->name,
            'filters' => $this->filters,
        ]);

        // Check if account is still active
        if (! $this->account->is_active) {
            Log::warning('[TikTok Order Sync Job] Account is not active, skipping', [
                'account_id' => $this->account->id,
            ]);

            return;
        }

        try {
            $result = $syncService->syncOrders($this->account, $this->filters);

            Log::info('[TikTok Order Sync Job] Sync completed', [
                'account_id' => $this->account->id,
                'result' => $result,
            ]);

            // Notify if requested and there are new orders
            if ($this->notifyOnCompletion && $result['created'] > 0) {
                $this->notifyNewOrders($result);
            }
        } catch (Exception $e) {
            Log::error('[TikTok Order Sync Job] Sync failed', [
                'account_id' => $this->account->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Exception $exception): void
    {
        Log::error('[TikTok Order Sync Job] Job failed permanently', [
            'account_id' => $this->account->id,
            'error' => $exception?->getMessage(),
        ]);

        // Update account metadata with failure info
        $this->account->update([
            'metadata' => array_merge($this->account->metadata ?? [], [
                'last_sync_failure' => [
                    'time' => now()->toIso8601String(),
                    'error' => $exception?->getMessage(),
                ],
            ]),
        ]);
    }

    /**
     * Notify about new orders (placeholder for notification system).
     */
    private function notifyNewOrders(array $result): void
    {
        // TODO: Implement notification (email, Slack, etc.)
        Log::info('[TikTok Order Sync Job] New orders notification', [
            'account_id' => $this->account->id,
            'new_orders' => $result['created'],
        ]);
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
            'account:'.$this->account->id,
        ];
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [60, 300, 900]; // 1 min, 5 min, 15 min
    }
}
