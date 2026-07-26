<?php

declare(strict_types=1);

namespace App\Console\Commands\LiveHost;

use App\Models\ActualLiveRecord;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Report TikTok lives that carry a PLACEHOLDER launch time — several distinct
 * lives (different source_record_id) stamped with the exact same launched_time
 * on one account, because the sync couldn't read their real per-live start (such
 * rows also lack viewers/end). Two real lives never start on the same second, so
 * a collision means the time — and any slot/host attributed from it — is a guess.
 *
 * Read-only. The auto-verifier now DEFERS these (see AutoVerifyService); this
 * surfaces the ones already linked to a verified session so a PIC can review or
 * unlink them, plus the unlinked backlog waiting on a re-sync.
 */
class FlagPlaceholderLives extends Command
{
    protected $signature = 'livehost:flag-placeholder-lives
        {--from= : Only lives launched from this date (Y-m-d)}
        {--until= : ...until this date (Y-m-d)}
        {--linked-only : Only show placeholder lives already linked to a session}';

    protected $description = 'List TikTok lives with a placeholder (colliding) launch time, flagging any already attributed to a host.';

    public function handle(): int
    {
        $collisions = DB::table('actual_live_records as a')
            ->join('actual_live_records as b', function ($join) {
                $join->on('a.platform_account_id', '=', 'b.platform_account_id')
                    ->on('a.launched_time', '=', 'b.launched_time')
                    ->on('a.id', '<>', 'b.id')
                    ->on('a.source_record_id', '<>', 'b.source_record_id');
            })
            ->whereNotNull('a.source_record_id')
            ->whereNotNull('b.source_record_id')
            ->when($this->option('from'), fn ($q) => $q->whereDate('a.launched_time', '>=', $this->option('from')))
            ->when($this->option('until'), fn ($q) => $q->whereDate('a.launched_time', '<=', $this->option('until')))
            ->distinct()
            ->pluck('a.id');

        if ($collisions->isEmpty()) {
            $this->info('No placeholder (colliding) launch times found.');

            return self::SUCCESS;
        }

        $rows = ActualLiveRecord::query()
            ->whereIn('id', $collisions)
            ->orderBy('launched_time')
            ->get()
            ->map(function (ActualLiveRecord $r) {
                $link = DB::table('live_session_actual_live_record as p')
                    ->join('live_sessions as s', 's.id', '=', 'p.live_session_id')
                    ->leftJoin('users as u', 'u.id', '=', 's.live_host_id')
                    ->where('p.actual_live_record_id', $r->id)
                    ->select('s.id as sess', 'u.name as host', 's.verification_status as vst')
                    ->first();

                return [
                    'rec' => $r->id,
                    'launched' => (string) $r->launched_time,
                    'account' => $r->creator_handle,
                    'gmv' => number_format((float) $r->live_attributed_gmv_myr, 2),
                    'linked' => $link,
                ];
            })
            ->when($this->option('linked-only'), fn ($c) => $c->filter(fn ($row) => $row['linked'] !== null)->values());

        $linkedCount = $rows->filter(fn ($row) => $row['linked'] !== null)->count();

        $this->table(
            ['rec', 'launched_time (placeholder)', 'account', 'GMV', 'linked → session (host)'],
            $rows->map(fn ($row) => [
                $row['rec'],
                $row['launched'],
                $row['account'],
                $row['gmv'],
                $row['linked'] !== null
                    ? "⚠ s{$row['linked']->sess} · {$row['linked']->host} ({$row['linked']->vst})"
                    : '—',
            ])->all(),
        );

        $this->newLine();
        $this->warn(sprintf(
            '%d placeholder live(s) across %d timestamp(s) · %d already linked to a session (review these).',
            $rows->count(),
            $rows->pluck('launched')->unique()->count(),
            $linkedCount,
        ));
        $this->comment('Auto-verify defers these until a re-sync brings the real launch times.');

        return self::SUCCESS;
    }
}
