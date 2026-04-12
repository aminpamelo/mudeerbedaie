<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncTikTokAffiliates;
use App\Models\Platform;
use App\Models\PlatformAccount;
use Illuminate\Console\Command;

class TikTokSyncAffiliates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tiktok:sync-affiliates
                            {--account= : Specific platform account ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync TikTok affiliate creators, orders, and collaboration content';

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

        $this->info("Dispatching affiliate sync for {$accounts->count()} account(s)...");

        foreach ($accounts as $account) {
            SyncTikTokAffiliates::dispatch($account);
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
