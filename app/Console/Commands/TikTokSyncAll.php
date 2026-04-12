<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncTikTokAffiliates;
use App\Jobs\SyncTikTokAnalytics;
use App\Jobs\SyncTikTokFinance;
use App\Models\Platform;
use App\Models\PlatformAccount;
use Illuminate\Console\Command;

class TikTokSyncAll extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tiktok:sync-all
                            {--account= : Specific platform account ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync ALL TikTok Shop data with staggered delays';

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

        $this->info("Dispatching full TikTok sync for {$accounts->count()} account(s) with staggered delays...");

        foreach ($accounts as $account) {
            SyncTikTokAnalytics::dispatch($account, 'all');
            $this->info("  → Analytics dispatched for: {$account->name} (immediate)");

            SyncTikTokAffiliates::dispatch($account)->delay(now()->addMinutes(2));
            $this->info("  → Affiliates dispatched for: {$account->name} (delay: 2 min)");

            SyncTikTokFinance::dispatch($account)->delay(now()->addMinutes(4));
            $this->info("  → Finance dispatched for: {$account->name} (delay: 4 min)");

            $this->newLine();
        }

        $this->info('All sync jobs dispatched successfully.');

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
