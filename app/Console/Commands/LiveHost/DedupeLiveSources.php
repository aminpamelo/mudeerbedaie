<?php

declare(strict_types=1);

namespace App\Console\Commands\LiveHost;

use App\Models\ActualLiveRecord;
use App\Models\LiveAccount;
use App\Services\LiveHost\AutoVerifyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Dedupe the two live-record sources. Every live is ingested twice — once from an
 * uploaded CSV export (source=csv_import, no TikTok live id) and once from the
 * TikTok API (source=api_sync, with a live id + fuller/final numbers). With no
 * shared key they were never deduped, so a live shows twice on the calendar and a
 * session that linked both twins double-counts its GMV.
 *
 * API sync is the source of truth. This removes each csv_import record that has an
 * api_sync twin (same account, launch within --within minutes): if the csv row is
 * linked to a session it is first unlinked (the session re-settles against its
 * remaining records — keeping the api twin if it was also linked, else reverting
 * to pending for auto-verify to re-link the api record), then the csv row is
 * deleted. csv rows with NO api twin are kept.
 *
 * Dry-run by default. Skips csv rows whose session is payroll-locked. Matching is
 * done in PHP so it behaves identically on MySQL and SQLite. Re-run auto-verify
 * afterwards to re-link any sessions left pending.
 */
class DedupeLiveSources extends Command
{
    protected $signature = 'livehost:dedupe-live-sources
        {--within=10 : Match a csv row to an api row launched within this many minutes on the same account}
        {--apply : Actually unlink + delete the csv duplicates (default is a read-only count)}';

    protected $description = 'Remove csv_import live records that duplicate an api_sync record (API is source of truth).';

    public function handle(AutoVerifyService $service): int
    {
        $within = max(1, (int) $this->option('within'));
        $apply = (bool) $this->option('apply');

        $apiByAccount = ActualLiveRecord::query()
            ->where('source', 'api_sync')
            ->whereNotNull('launched_time')
            ->get(['id', 'platform_account_id', 'creator_handle', 'launched_time'])
            ->groupBy('platform_account_id');

        $csv = ActualLiveRecord::query()
            ->where('source', 'csv_import')
            ->whereNotNull('launched_time')
            ->orderBy('id')
            ->get(['id', 'platform_account_id', 'creator_handle', 'launched_time']);

        $twins = 0;
        $deleted = 0;
        $unlinked = 0;
        $skippedLocked = 0;

        foreach ($csv as $c) {
            // Same shop AND same creator (a shop can host several creators, so the
            // handle must match — never dedupe a different creator's live) AND the
            // launch is within the window.
            $csvHandle = LiveAccount::normalizeHandle($c->creator_handle);
            $hasTwin = ($apiByAccount[$c->platform_account_id] ?? collect())
                ->contains(fn ($a) => LiveAccount::normalizeHandle($a->creator_handle) === $csvHandle
                    && $csvHandle !== null
                    && abs($a->launched_time->diffInMinutes($c->launched_time)) <= $within);
            if (! $hasTwin) {
                continue;
            }
            $twins++;

            $sessionId = DB::table('live_session_actual_live_record')
                ->where('actual_live_record_id', $c->id)
                ->value('live_session_id');

            if ($apply) {
                if ($sessionId !== null) {
                    try {
                        $service->unlinkLive($c);
                        $unlinked++;
                    } catch (\RuntimeException) {
                        $skippedLocked++;

                        continue; // leave payroll-locked rows intact
                    }
                }
                ActualLiveRecord::where('id', $c->id)->delete();
                $deleted++;
            }
        }

        // Pass 2 — remove csv rows that duplicate ANOTHER csv row: same shop +
        // creator + EXACT launch/end window = the same live re-imported (uploading
        // the same export more than once; csv rows have no live-id so nothing
        // prevented it). Keep the earliest row, drop the rest. Re-queried fresh so
        // in --apply mode it runs on what pass 1 left behind.
        $csvSelfDupes = 0;
        $seen = [];
        $csvAll = ActualLiveRecord::query()
            ->where('source', 'csv_import')
            ->whereNotNull('launched_time')
            ->orderBy('id')
            ->get(['id', 'platform_account_id', 'creator_handle', 'launched_time', 'ended_time']);

        foreach ($csvAll as $r) {
            $key = implode('|', [
                $r->platform_account_id,
                LiveAccount::normalizeHandle($r->creator_handle),
                $r->launched_time?->format('Y-m-d H:i:s'),
                $r->ended_time?->format('Y-m-d H:i:s'),
            ]);
            if (! isset($seen[$key])) {
                $seen[$key] = $r->id;

                continue;
            }
            $csvSelfDupes++;
            if ($apply) {
                $sessionId = DB::table('live_session_actual_live_record')
                    ->where('actual_live_record_id', $r->id)
                    ->value('live_session_id');
                if ($sessionId !== null) {
                    try {
                        $service->unlinkLive($r);
                        $unlinked++;
                    } catch (\RuntimeException) {
                        $skippedLocked++;

                        continue;
                    }
                }
                ActualLiveRecord::where('id', $r->id)->delete();
                $deleted++;
            }
        }

        if (! $apply) {
            $this->warn('DRY RUN — no rows changed. Pass --apply to dedupe.');
            $this->info("{$twins} csv row(s) have an api_sync twin · {$csvSelfDupes} csv row(s) duplicate another csv row — all would be removed.");

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Deduped %d csv record(s) (%d api-twins + %d csv-vs-csv) · %d unlinked from a session first · %d skipped (payroll-locked). Re-run livehost:auto-verify-sessions to re-link any freed sessions.',
            $deleted,
            $twins,
            $csvSelfDupes,
            $unlinked,
            $skippedLocked,
        ));

        return self::SUCCESS;
    }
}
