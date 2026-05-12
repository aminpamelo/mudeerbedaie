<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncTikTokLive;
use App\Models\Platform;
use App\Models\PlatformAccount;
use Illuminate\Console\Command;

class TikTokSyncLive extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tiktok:sync-live
                            {--account= : Specific platform account ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync TikTok Shop per-LIVE performance data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $platform = Platform::where('slug', 'tiktok-shop')->first();

        if (! $platform) {
            $this->error('TikTok Shop platform not found.');

            return Command::FAILURE;
        }

        $query = PlatformAccount::where('platform_id', $platform->id)
            ->where('is_active', true);

        if ($accountId = $this->option('account')) {
            $query->where('id', $accountId);
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->error('No active TikTok Shop accounts found.');

            return Command::FAILURE;
        }

        $this->info("Dispatching LIVE sync for {$accounts->count()} account(s)...");

        foreach ($accounts as $account) {
            SyncTikTokLive::dispatch($account);
            $this->info("  → Dispatched for: {$account->name} (ID: {$account->id})");
        }

        return Command::SUCCESS;
    }
}
