<?php

declare(strict_types=1);

namespace App\Console\Commands\LiveHost;

use App\Models\ActualLiveRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repair mis-stamped live start times. An older sync wrote some
 * actual_live_records.launched_time with the SYNC MOMENT instead of the real
 * broadcast start, so distinct lives collide on one timestamp and stack on the
 * calendar. The authoritative start time survives in tiktok_live_reports (keyed
 * by TikTok live id), so this copies it back — no re-sync or CSV needed.
 *
 * Matches actual_live_records.source_record_id = tiktok_live_reports.tiktok_live_id
 * on the same platform account, only where the two disagree. Dry-run by default.
 * The current sync already stamps new lives correctly; this is a one-off backfill
 * for the legacy rows. Re-run auto-verify / audit-rebuild afterwards so
 * attribution uses the corrected times.
 */
class RepairLaunchTimes extends Command
{
    protected $signature = 'livehost:repair-launch-times
        {--apply : Actually write the corrected times (default is a read-only report)}';

    protected $description = 'Backfill actual_live_records.launched_time from the real time in tiktok_live_reports.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $pairs = DB::table('actual_live_records as r')
            ->join('tiktok_live_reports as rep', function ($j) {
                $j->on('rep.tiktok_live_id', '=', 'r.source_record_id')
                    ->on('rep.platform_account_id', '=', 'r.platform_account_id');
            })
            ->where('r.source', 'api_sync')
            ->whereNotNull('rep.launched_time')
            ->whereColumn('r.launched_time', '<>', 'rep.launched_time')
            ->select('r.id', 'r.launched_time as wrong_time', 'rep.launched_time as real_time', 'r.creator_handle')
            ->orderBy('rep.launched_time')
            ->get();

        if ($pairs->isEmpty()) {
            $this->info('No mis-stamped launch times found — all actual_live_records match their report.');

            return self::SUCCESS;
        }

        if (! $apply) {
            $this->warn('DRY RUN — no rows will change. Pass --apply to repair.');
        }

        $this->table(
            ['rec', 'account', 'wrong (sync time)', '→ real (report)'],
            $pairs->take(15)->map(fn ($p) => [
                $p->id, $p->creator_handle, $p->wrong_time, $p->real_time,
            ])->all(),
        );
        if ($pairs->count() > 15) {
            $this->line('  … and '.($pairs->count() - 15).' more.');
        }

        if (! $apply) {
            $this->newLine();
            $this->warn("{$pairs->count()} record(s) would be corrected — re-run with --apply.");

            return self::SUCCESS;
        }

        $fixed = 0;
        foreach ($pairs as $p) {
            ActualLiveRecord::where('id', $p->id)->update(['launched_time' => $p->real_time]);
            $fixed++;
        }

        $this->info("Repaired {$fixed} launch time(s) from tiktok_live_reports. Re-run auto-verify / audit-rebuild so attribution uses the corrected times.");

        return self::SUCCESS;
    }
}
