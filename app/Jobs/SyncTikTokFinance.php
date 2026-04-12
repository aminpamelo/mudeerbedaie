<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PlatformAccount;
use App\Models\TiktokFinanceStatement;
use App\Services\TikTok\TikTokFinanceSyncService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncTikTokFinance implements ShouldQueue
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
    ) {}

    /**
     * Execute the job.
     */
    public function handle(TikTokFinanceSyncService $service): void
    {
        if (! $this->account->is_active) {
            Log::warning('[SyncTikTokFinance] Account is not active, skipping', [
                'account_id' => $this->account->id,
            ]);

            return;
        }

        Log::info('[SyncTikTokFinance] Starting sync', [
            'account_id' => $this->account->id,
            'account_name' => $this->account->name,
        ]);

        try {
            $service->syncStatements($this->account);

            // Sync transactions for recent statements (last 30 days)
            $recentStatements = TiktokFinanceStatement::where('platform_account_id', $this->account->id)
                ->where('statement_time', '>=', now()->subDays(30))
                ->get();

            foreach ($recentStatements as $statement) {
                $service->syncStatementTransactions($this->account, $statement);
            }

            $this->account->updateSyncStatus('completed', 'finance');

            Log::info('[SyncTikTokFinance] Sync completed', [
                'account_id' => $this->account->id,
                'statements_processed' => $recentStatements->count(),
            ]);
        } catch (Exception $e) {
            Log::error('[SyncTikTokFinance] Sync failed', [
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
        Log::error('[SyncTikTokFinance] Job failed permanently', [
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
            'finance',
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
