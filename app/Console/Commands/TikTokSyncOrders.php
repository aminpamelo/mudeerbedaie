<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncTikTokOrders;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Services\TikTok\TikTokOrderSyncService;
use Exception;
use Illuminate\Console\Command;

class TikTokSyncOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tiktok:sync-orders
                            {--account= : Specific account ID to sync}
                            {--all : Sync all active TikTok accounts}
                            {--days=7 : Number of days to look back for orders}
                            {--status= : Filter by order status (e.g., AWAITING_SHIPMENT)}
                            {--queue : Dispatch to queue instead of running synchronously}
                            {--force : Force sync even if recently synced}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync orders from TikTok Shop for connected accounts';

    /**
     * Execute the console command.
     */
    public function handle(TikTokOrderSyncService $syncService): int
    {
        $accountId = $this->option('account');
        $syncAll = $this->option('all');
        $days = (int) $this->option('days');
        $status = $this->option('status');
        $useQueue = $this->option('queue');
        $force = $this->option('force');

        // Build filters
        $filters = [
            'create_time_from' => now()->subDays($days)->timestamp,
            'create_time_to' => now()->timestamp,
        ];

        if ($status) {
            $filters['order_status'] = $status;
        }

        // Get accounts to sync
        $accounts = $this->getAccountsToSync($accountId, $syncAll);

        if ($accounts->isEmpty()) {
            $this->error('No active TikTok Shop accounts found to sync.');

            return Command::FAILURE;
        }

        $this->info("Found {$accounts->count()} account(s) to sync.");
        $this->newLine();

        $totalSynced = 0;
        $totalCreated = 0;
        $totalUpdated = 0;
        $totalFailed = 0;

        foreach ($accounts as $account) {
            // Check if recently synced (unless forced)
            if (! $force && $this->wasRecentlySynced($account)) {
                $this->warn("Account '{$account->name}' was synced recently. Use --force to override.");

                continue;
            }

            $this->info("Syncing orders for: {$account->name}");

            if ($useQueue) {
                // Dispatch to queue
                SyncTikTokOrders::dispatch($account, $filters);
                $this->info('  → Dispatched to queue');
            } else {
                // Run synchronously
                try {
                    $result = $syncService->syncOrders($account, $filters);

                    $this->table(
                        ['Metric', 'Count'],
                        [
                            ['Synced', $result['synced']],
                            ['Created', $result['created']],
                            ['Updated', $result['updated']],
                            ['Failed', $result['failed']],
                        ]
                    );

                    if (! empty($result['errors'])) {
                        $this->warn('Errors:');
                        foreach (array_slice($result['errors'], 0, 5) as $error) {
                            $this->warn("  - {$error}");
                        }
                        if (count($result['errors']) > 5) {
                            $this->warn('  ... and '.(count($result['errors']) - 5).' more errors');
                        }
                    }

                    $totalSynced += $result['synced'];
                    $totalCreated += $result['created'];
                    $totalUpdated += $result['updated'];
                    $totalFailed += $result['failed'];
                } catch (Exception $e) {
                    $this->error("  → Failed: {$e->getMessage()}");
                    $totalFailed++;
                }
            }

            $this->newLine();
        }

        // Summary
        if (! $useQueue) {
            $this->newLine();
            $this->info('=== Sync Summary ===');
            $this->table(
                ['Metric', 'Total'],
                [
                    ['Total Synced', $totalSynced],
                    ['Total Created', $totalCreated],
                    ['Total Updated', $totalUpdated],
                    ['Total Failed', $totalFailed],
                ]
            );
        } else {
            $this->info("Dispatched {$accounts->count()} sync job(s) to queue.");
        }

        return Command::SUCCESS;
    }

    /**
     * Get accounts to sync based on options.
     */
    private function getAccountsToSync(?string $accountId, bool $syncAll): \Illuminate\Support\Collection
    {
        $platform = Platform::where('slug', 'tiktok-shop')->first();

        if (! $platform) {
            return collect();
        }

        $query = PlatformAccount::where('platform_id', $platform->id)
            ->where('is_active', true);

        if ($accountId) {
            return $query->where('id', $accountId)->get();
        }

        if ($syncAll) {
            // Only sync accounts with auto_sync_orders enabled
            return $query->where('auto_sync_orders', true)->get();
        }

        // Interactive selection
        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            return collect();
        }

        if ($accounts->count() === 1) {
            return $accounts;
        }

        // Let user choose
        $choices = $accounts->pluck('name', 'id')->toArray();
        $choices['all'] = 'All accounts';

        $selected = $this->choice(
            'Which account would you like to sync?',
            $choices,
            'all'
        );

        if ($selected === 'All accounts') {
            return $accounts;
        }

        $selectedId = array_search($selected, $choices);

        return $accounts->where('id', $selectedId);
    }

    /**
     * Check if account was synced recently (within last 5 minutes).
     */
    private function wasRecentlySynced(PlatformAccount $account): bool
    {
        if (! $account->last_order_sync_at) {
            return false;
        }

        return $account->last_order_sync_at->gt(now()->subMinutes(5));
    }
}
