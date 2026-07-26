<?php

declare(strict_types=1);

namespace App\Console\Commands\LiveHost;

use App\Models\ActualLiveRecord;
use Carbon\CarbonImmutable;
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

        $rows = DB::table('actual_live_records as r')
            ->join('tiktok_live_reports as rep', function ($j) {
                $j->on('rep.tiktok_live_id', '=', 'r.source_record_id')
                    ->on('rep.platform_account_id', '=', 'r.platform_account_id');
            })
            ->where('r.source', 'api_sync')
            ->whereNotNull('rep.launched_time')
            ->select('r.id', 'r.launched_time', 'r.ended_time', 'r.duration_seconds', 'rep.launched_time as real_launched', 'r.creator_handle')
            ->orderBy('rep.launched_time')
            ->get();

        $fixes = [];
        foreach ($rows as $r) {
            $realLaunched = CarbonImmutable::parse($r->real_launched)->format('Y-m-d H:i:s');
            // ended_time is derived from the real start + duration. Recomputing it
            // here is essential: without it, correcting launched_time alone leaves a
            // stale ended_time (from the old wrong start), stretching the calendar
            // card across days.
            $realEnded = ($r->duration_seconds !== null && (int) $r->duration_seconds > 0)
                ? CarbonImmutable::parse($r->real_launched)->addSeconds((int) $r->duration_seconds)->format('Y-m-d H:i:s')
                : ($r->ended_time ? CarbonImmutable::parse($r->ended_time)->format('Y-m-d H:i:s') : null);

            $curLaunched = $r->launched_time ? CarbonImmutable::parse($r->launched_time)->format('Y-m-d H:i:s') : null;
            $curEnded = $r->ended_time ? CarbonImmutable::parse($r->ended_time)->format('Y-m-d H:i:s') : null;

            if ($curLaunched === $realLaunched && $curEnded === $realEnded) {
                continue;
            }

            $fixes[] = ['id' => $r->id, 'account' => $r->creator_handle, 'from' => $curLaunched, 'to' => $realLaunched, 'ended' => $realEnded];
        }

        if ($fixes === []) {
            $this->info('No mis-stamped times found — actual_live_records launched/ended match their report.');

            return self::SUCCESS;
        }

        if (! $apply) {
            $this->warn('DRY RUN — no rows will change. Pass --apply to repair.');
        }

        $this->table(
            ['rec', 'account', 'wrong launched', '→ real launched', '→ real ended'],
            collect($fixes)->take(15)->map(fn ($f) => [$f['id'], $f['account'], $f['from'], $f['to'], $f['ended']])->all(),
        );
        if (count($fixes) > 15) {
            $this->line('  … and '.(count($fixes) - 15).' more.');
        }

        if (! $apply) {
            $this->newLine();
            $this->warn(count($fixes).' record(s) would be corrected — re-run with --apply.');

            return self::SUCCESS;
        }

        foreach ($fixes as $f) {
            ActualLiveRecord::where('id', $f['id'])->update([
                'launched_time' => $f['to'],
                'ended_time' => $f['ended'],
            ]);
        }

        $this->info(sprintf('Repaired %d record(s) (launched + ended) from tiktok_live_reports. Re-run auto-verify / audit-rebuild so attribution uses the corrected times.', count($fixes)));

        return self::SUCCESS;
    }
}
