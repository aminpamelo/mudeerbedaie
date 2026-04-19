<?php

namespace App\Console\Commands;

use App\Models\LiveSession;
use App\Services\LiveHost\CommissionCalculator;
use Illuminate\Console\Command;

class BackfillLiveSessionCommissionSnapshots extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'livehost:backfill-commission-snapshots';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill commission_snapshot_json for verified LiveSessions that lack a snapshot.';

    /**
     * Execute the console command.
     *
     * Iterates every LiveSession with gmv_locked_at set but commission_snapshot_json
     * null (historical verified sessions that predate the snapshot-on-verify
     * observer) and writes the calculated snapshot to each. Uses saveQuietly()
     * to avoid re-firing the observer — which would no-op anyway since
     * gmv_locked_at is already set, but skipping it keeps event chains clean.
     */
    public function handle(CommissionCalculator $calculator): int
    {
        $count = 0;

        LiveSession::query()
            ->whereNotNull('gmv_locked_at')
            ->whereNull('commission_snapshot_json')
            ->with(['liveHost.commissionProfile', 'liveHost.platformCommissionRates', 'platformAccount'])
            ->chunkById(100, function ($sessions) use ($calculator, &$count) {
                foreach ($sessions as $session) {
                    $session->commission_snapshot_json = $calculator->snapshot($session, null);
                    $session->saveQuietly();
                    $count++;
                }
            });

        $this->info("Processed {$count} sessions");

        return Command::SUCCESS;
    }
}
