<?php

namespace App\Console\Commands;

use App\Jobs\SyncFacebookAdMonitor;
use App\Models\FacebookAdAccount;
use Illuminate\Console\Command;

class FacebookMonitorSync extends Command
{
    protected $signature = 'facebook:monitor-sync {--days=3 : Days of ad-level insights to refresh} {--account= : Only this ad account id} {--now : Run in this process instead of queueing}';

    protected $description = 'Sync ad-level Meta insights, budgets and creatives for the Monitor Iklan';

    public function handle(): int
    {
        $accounts = FacebookAdAccount::query()
            ->monitorable()
            ->when($this->option('account'), fn ($q, $id) => $q->whereKey($id))
            ->pluck('id');

        foreach ($accounts as $id) {
            $this->option('now')
                ? SyncFacebookAdMonitor::dispatchSync((int) $id, (int) $this->option('days'))
                : SyncFacebookAdMonitor::dispatch((int) $id, (int) $this->option('days'));
        }

        $this->info(($this->option('now') ? 'Synced' : 'Queued')." {$accounts->count()} ad account(s).");

        return self::SUCCESS;
    }
}
