<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PlatformAccount;
use App\Services\TikTok\TikTokLiveSyncService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncTikTokLive implements ShouldQueue
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
    public function __construct(public PlatformAccount $account) {}

    /**
     * Execute the job.
     */
    public function handle(TikTokLiveSyncService $service): void
    {
        if (! $this->account->is_active) {
            Log::warning('[SyncTikTokLive] Account not active, skipping', [
                'account_id' => $this->account->id,
            ]);

            return;
        }

        Log::info('[SyncTikTokLive] Starting sync', [
            'account_id' => $this->account->id,
            'account_name' => $this->account->name,
        ]);

        $result = $service->syncLivePerformance($this->account);

        $this->account->updateSyncStatus('completed', 'live_analytics');

        $meta = $this->account->metadata ?? [];
        $meta['last_live_sync_result'] = array_merge($result, [
            'fetched_at' => now()->toIso8601String(),
        ]);
        $this->account->update(['metadata' => $meta]);

        Log::info('[SyncTikTokLive] Sync completed', [
            'account_id' => $this->account->id,
            'result' => $result,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Exception $exception): void
    {
        Log::error('[SyncTikTokLive] Job failed permanently', [
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
            'live-analytics',
            'account:'.$this->account->id,
        ];
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }
}
