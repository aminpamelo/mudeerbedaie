<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncTikTokAnalytics;
use App\Models\Platform;
use App\Models\PlatformAccount;
use Illuminate\Console\Command;

class TikTokSyncAnalytics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tiktok:sync-analytics
                            {--account= : Specific platform account ID}
                            {--type=all : Sync type: all, shop, videos, products}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync TikTok Shop analytics data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $accounts = $this->getAccounts();

        if ($accounts->isEmpty()) {
            $this->error('No active TikTok Shop accounts found.');

            return Command::FAILURE;
        }

        $type = $this->option('type');

        $this->info("Dispatching analytics sync for {$accounts->count()} account(s) [type: {$type}]...");

        foreach ($accounts as $account) {
            SyncTikTokAnalytics::dispatch($account, $type);
            $this->info("  → Dispatched for: {$account->name} (ID: {$account->id})");
        }

        return Command::SUCCESS;
    }

    /**
     * Get accounts to sync.
     */
    private function getAccounts(): \Illuminate\Support\Collection
    {
        $platform = Platform::where('slug', 'tiktok-shop')->first();

        if (! $platform) {
            return collect();
        }

        $query = PlatformAccount::where('platform_id', $platform->id)
            ->where('is_active', true);

        if ($accountId = $this->option('account')) {
            $query->where('id', $accountId);
        }

        return $query->get();
    }
}
