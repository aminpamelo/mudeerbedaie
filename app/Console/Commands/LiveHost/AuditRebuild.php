<?php

declare(strict_types=1);

namespace App\Console\Commands\LiveHost;

use App\Services\LiveHost\AutoVerifyService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One command to make a date range's live attribution trustworthy: re-home every
 * verified live to the host whose slot window contains its launch time, and
 * quarantine the ones that can't be trusted (unpublished -1 GMV, or a placeholder
 * launch time that collides with another live). Ends with an audit report.
 *
 * Dry-run by default. Human-verified and payroll-locked sessions are never
 * touched. Idempotent. What stays quarantined needs a real TikTok re-sync — a
 * fabricated time cannot be recovered from the data we have.
 */
class AuditRebuild extends Command
{
    protected $signature = 'livehost:audit-rebuild
        {--from= : Rebuild lives launched from this date (Y-m-d)}
        {--until= : ...until this date (Y-m-d)}
        {--apply : Actually re-home and quarantine (default is a read-only report)}';

    protected $description = 'Rebuild trustworthy live attribution for a range: re-home by launch window, quarantine placeholder/-1 lives, report.';

    public function handle(AutoVerifyService $service): int
    {
        $from = $this->option('from');
        $until = $this->option('until');
        if (! $from || ! $until) {
            $this->error('Pass both --from and --until (Y-m-d).');

            return self::INVALID;
        }

        $fromAt = CarbonImmutable::parse($from)->startOfDay();
        $toAt = CarbonImmutable::parse($until)->endOfDay();
        $apply = (bool) $this->option('apply');

        if (! $apply) {
            $this->warn('DRY RUN — no rows will change. Pass --apply to rebuild.');
        }

        $s = $service->auditRebuild($fromAt, $toAt, $apply);

        $this->newLine();
        $this->line(sprintf('Checked %d verified live(s) in %s … %s:', $s['checked'], $from, $until));
        $this->line(sprintf('  • re-homed to correct host ....... %d', $s['reattributed']));
        $this->line(sprintf('  • quarantined (placeholder time) . %d', $s['quarantined_placeholder']));
        $this->line(sprintf('  • quarantined (unpublished -1 GMV) %d', $s['quarantined_gmv']));
        $this->line(sprintf('  • orphan (no hosted slot) ........ %d', $s['orphan']));
        $this->line(sprintf('  • skipped (human / payroll-locked) %d', $s['skipped']));

        $this->newLine();
        $this->line($apply ? 'Post-rebuild audit:' : 'Current state (before rebuild):');
        $this->auditReport($fromAt, $toAt);

        if (! $apply) {
            $this->newLine();
            $this->warn('Re-run with --apply to rebuild. Quarantined lives await a real TikTok re-sync (their times are fabricated and cannot be recovered here).');
        }

        return self::SUCCESS;
    }

    private function auditReport(CarbonImmutable $from, CarbonImmutable $to): void
    {
        $verified = DB::table('live_sessions')
            ->where('verification_status', 'verified')
            ->whereBetween('scheduled_start_at', [$from, $to])
            ->selectRaw('COUNT(*) n, COALESCE(ROUND(SUM(gmv_amount),2),0) gmv')
            ->first();

        $placeholderLinked = DB::table('live_session_actual_live_record as p')
            ->join('actual_live_records as r', 'r.id', '=', 'p.actual_live_record_id')
            ->join('live_sessions as s', 's.id', '=', 'p.live_session_id')
            ->where('s.verification_status', 'verified')
            ->whereBetween('r.launched_time', [$from, $to])
            ->where('r.live_attributed_gmv_myr', '<', 0)
            ->count();

        $this->line(sprintf('  • verified sessions .............. %d  (RM %s)', $verified->n ?? 0, number_format((float) ($verified->gmv ?? 0), 2)));
        $this->line(sprintf('  • still-linked -1 GMV (should be 0) %d', $placeholderLinked));
    }
}
