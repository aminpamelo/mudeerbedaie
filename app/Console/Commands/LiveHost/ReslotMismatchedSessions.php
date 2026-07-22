<?php

declare(strict_types=1);

namespace App\Console\Commands\LiveHost;

use App\Models\ActualLiveRecord;
use App\Models\LiveSession;
use App\Services\LiveHost\ActualLiveRecordCandidateFinder;
use App\Services\LiveHost\AutoVerifyService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Undo mis-slotted auto-verifies. The old proximity matcher could link a live
 * to the slot nearest its scheduled time rather than the slot whose window
 * actually contains it, so on days where several hosts share one creator account
 * a live ended up on the wrong host's session (the "crossing links" on the
 * calendar). This finds auto-verified sessions whose every linked live sits
 * ENTIRELY OUTSIDE the slot's time window, resets them to pending, then (with
 * --reverify) re-runs the now window-constrained matcher to relink the freed
 * lives to the correct slots.
 *
 * Dry-run by default. Never touches a session that a human has verified/edited
 * (verification history) or one in a locked payroll period.
 */
class ReslotMismatchedSessions extends Command
{
    protected $signature = 'livehost:reslot-mismatched
        {--from= : Scan auto-verified sessions scheduled from this date (Y-m-d)}
        {--until= : ...until this date (Y-m-d)}
        {--host= : Limit to one live_host_id}
        {--account= : Limit to one live_account_id}
        {--apply : Actually reset the mis-slotted sessions to pending (default is read-only)}
        {--reverify : After resetting, re-run auto-verify over the range to relink the freed lives}';

    protected $description = 'Find and reset auto-verified sessions whose linked live falls outside the slot window, then optionally relink correctly.';

    public function handle(AutoVerifyService $service, ActualLiveRecordCandidateFinder $finder): int
    {
        $from = $this->option('from');
        $until = $this->option('until');

        if (! $from || ! $until) {
            $this->error('Pass both --from and --until (Y-m-d).');

            return self::INVALID;
        }

        $fromDate = CarbonImmutable::parse($from);
        $untilDate = CarbonImmutable::parse($until);
        $apply = (bool) $this->option('apply');

        $sessions = LiveSession::query()
            ->where('auto_verified', true)
            ->where('verification_status', 'verified')
            ->whereDate('scheduled_start_at', '>=', $fromDate->toDateString())
            ->whereDate('scheduled_start_at', '<=', $untilDate->toDateString())
            ->when($this->option('host'), fn ($q) => $q->where('live_host_id', (int) $this->option('host')))
            ->when($this->option('account'), fn ($q) => $q->where('live_account_id', (int) $this->option('account')))
            ->with(['liveScheduleAssignment.timeSlot', 'actualLiveRecords', 'liveHost'])
            ->orderBy('scheduled_start_at')
            ->get();

        $mismatched = $sessions->filter(function (LiveSession $s) use ($service, $finder): bool {
            if ($service->hasVerificationHistory($s) || $service->isPayrollLocked($s)) {
                return false;
            }

            return $finder->linkedRecordsOverlapWindow($s) === false;
        })->values();

        if ($mismatched->isEmpty()) {
            $this->info("Scanned {$sessions->count()} auto-verified session(s) · no mis-slotted links found.");

            return self::SUCCESS;
        }

        $this->table(
            ['session', 'scheduled', 'host', 'slot window', 'linked lives (launch · GMV)', 'GMV'],
            $mismatched->map(fn (LiveSession $s) => [
                $s->id,
                $s->scheduled_start_at?->format('m-d H:i') ?? '—',
                $s->liveHost?->name ?? '—',
                $this->slotWindowLabel($s),
                $this->linkedLabel($s->actualLiveRecords),
                number_format((float) $s->gmv_amount, 2),
            ])->all(),
        );

        if (! $apply) {
            $this->newLine();
            $this->warn("{$mismatched->count()} mis-slotted session(s) — re-run with --apply to reset them"
                .' (add --reverify to relink in the same pass).');

            return self::SUCCESS;
        }

        $reset = 0;
        foreach ($mismatched as $s) {
            $service->revertToPending($s);
            $reset++;
        }
        $this->info("Reset {$reset} session(s) to pending.");

        if ($this->option('reverify')) {
            $stats = $service->run($fromDate->startOfDay(), $untilDate->endOfDay());
            $this->info(sprintf(
                'Reverify: %d sessions verified (%d records) · scanned %d · no-match %d · no-host %d · unsettled %d · skipped %d',
                $stats['sessions_verified'],
                $stats['records_linked'],
                $stats['scanned'],
                $stats['no_match'],
                $stats['no_host'],
                $stats['unsettled'],
                $stats['skipped'],
            ));
        } else {
            $this->comment('Freed lives are unmatched again — run livehost:auto-verify-sessions or re-run with --reverify to relink.');
        }

        return self::SUCCESS;
    }

    private function slotWindowLabel(LiveSession $session): string
    {
        $slot = $session->liveScheduleAssignment?->timeSlot;
        if ($slot === null || $slot->start_time === null || $slot->end_time === null) {
            return '—';
        }

        return substr($slot->start_time, 0, 5).'–'.substr($slot->end_time, 0, 5);
    }

    /**
     * @param  Collection<int, ActualLiveRecord>  $records
     */
    private function linkedLabel(Collection $records): string
    {
        return $records
            ->sortBy(fn (ActualLiveRecord $r) => $r->launched_time?->getTimestamp() ?? 0)
            ->map(fn (ActualLiveRecord $r) => sprintf(
                '%s·%s',
                $r->launched_time?->copy()->setTimezone('Asia/Kuala_Lumpur')->format('H:i') ?? '??',
                number_format((float) $r->live_attributed_gmv_myr, 0),
            ))
            ->implode(', ');
    }
}
