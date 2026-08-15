<?php

namespace App\Console\Commands;

use App\Models\FacebookAdConnection;
use App\Services\Funnel\FacebookAdsService;
use Illuminate\Console\Command;

class FacebookSyncAds extends Command
{
    protected $signature = 'facebook:sync-ads {--days=3 : How many days of insights to refresh} {--connection= : Only sync this connection ID}';

    protected $description = 'Sync ad accounts and daily insights from every connected Facebook Business Manager';

    public function handle(FacebookAdsService $adsService): int
    {
        $connections = FacebookAdConnection::query()
            ->when($this->option('connection'), fn ($q, $id) => $q->where('id', $id))
            ->get();

        if ($connections->isEmpty()) {
            $this->info('No Facebook Ads connections configured.');

            return self::SUCCESS;
        }

        $failed = 0;
        foreach ($connections as $connection) {
            $result = $adsService->syncConnection($connection, (int) $this->option('days'));
            if ($result['success']) {
                $this->line("{$connection->name}: {$result['message']}");
            } else {
                $failed++;
                $this->warn("{$connection->name}: {$result['message']}");
            }
        }

        $this->info("Synced {$connections->count()} connection(s), {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
