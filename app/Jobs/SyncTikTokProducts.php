<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PlatformAccount;
use App\Services\TikTok\TikTokProductSyncService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncTikTokProducts implements ShouldQueue
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
     *     status?: string,
     *     page_size?: int,
     *     max_pages?: int
     * }  $options
     */
    public function __construct(
        public PlatformAccount $account,
        public array $options = [],
        public bool $notifyOnCompletion = false
    ) {
        // Use default queue
    }

    /**
     * Execute the job.
     */
    public function handle(TikTokProductSyncService $syncService): void
    {
        Log::info('[TikTok Product Sync Job] Starting sync', [
            'account_id' => $this->account->id,
            'account_name' => $this->account->name,
            'options' => $this->options,
        ]);

        // Check if account is still active
        if (! $this->account->is_active) {
            Log::warning('[TikTok Product Sync Job] Account is not active, skipping', [
                'account_id' => $this->account->id,
            ]);

            return;
        }

        try {
            $result = $syncService->syncProducts($this->account, $this->options);

            Log::info('[TikTok Product Sync Job] Sync completed', [
                'account_id' => $this->account->id,
                'result' => $result->toArray(),
            ]);

            // Notify if requested
            if ($this->notifyOnCompletion) {
                $this->notifySyncCompletion($result);
            }
        } catch (Exception $e) {
            Log::error('[TikTok Product Sync Job] Sync failed', [
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
        Log::error('[TikTok Product Sync Job] Job failed permanently', [
            'account_id' => $this->account->id,
            'error' => $exception?->getMessage(),
        ]);

        // Update account metadata with failure info
        $this->account->update([
            'metadata' => array_merge($this->account->metadata ?? [], [
                'last_product_sync_failure' => [
                    'time' => now()->toIso8601String(),
                    'error' => $exception?->getMessage(),
                ],
            ]),
        ]);
    }

    /**
     * Notify about sync completion (placeholder for notification system).
     */
    private function notifySyncCompletion($result): void
    {
        Log::info('[TikTok Product Sync Job] Sync completion notification', [
            'account_id' => $this->account->id,
            'total' => $result->total,
            'auto_linked' => $result->autoLinked,
            'queued' => $result->queuedForReview,
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
            'tiktok-product-sync',
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
