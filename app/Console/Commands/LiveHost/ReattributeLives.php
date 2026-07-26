<?php

declare(strict_types=1);

namespace App\Console\Commands\LiveHost;

use App\Models\ActualLiveRecord;
use App\Models\LiveScheduleAssignment;
use App\Models\LiveSession;
use App\Services\LiveHost\AutoVerifyService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repair mis-attributed commission: move each verified live to the host whose
 * slot window actually contains it on the live's own date.
 *
 * Where several hosts share one creator account across a day, the old proximity
 * matcher (compounded by drifted scheduled_start_at) linked a live to the wrong
 * host's slot — so the wrong host was credited the GMV/commission. This walks
 * every verified live in the range, and for any whose CURRENT slot window does
 * not contain it, finds the correct slot (by time-of-day, drift-immune) and
 * moves the live there via the same path as a PIC dragging it on the calendar
 * (detach from the wrong session, re-sum both, credit the right host).
 *
 * Commission buckets by the live's actual_end_at and the session that holds it,
 * so moving the live to the right host's session is exactly what corrects the
 * payout. Dry-run by default. Never touches a live whose session a human has
 * verified/edited (verification history) or a session in a locked payroll period.
 */
class ReattributeLives extends Command
{
    protected $signature = 'livehost:reattribute-lives
        {--from= : Scan lives launched from this date (Y-m-d)}
        {--until= : ...until this date (Y-m-d)}
        {--account= : Limit to one live_account_id}
        {--apply : Actually move the mis-attributed lives (default is read-only)}';

    protected $description = 'Move mis-attributed verified lives to the host whose slot window contains them, correcting commission.';

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

        $recordIds = DB::table('live_session_actual_live_record as p')
            ->join('actual_live_records as r', 'r.id', '=', 'p.actual_live_record_id')
            ->join('live_sessions as s', 's.id', '=', 'p.live_session_id')
            ->whereBetween('r.launched_time', [$fromAt, $toAt])
            ->where('s.verification_status', 'verified')
            ->when($this->option('account'), fn ($q) => $q->where('s.live_account_id', (int) $this->option('account')))
            ->orderBy('r.launched_time')
            ->pluck('p.actual_live_record_id')
            ->unique()
            ->values();

        $moves = [];
        $orphans = 0;
        $skipped = 0;
        $checked = 0;

        foreach ($recordIds as $recId) {
            $live = ActualLiveRecord::find($recId);
            if ($live === null) {
                continue;
            }

            $session = $this->sessionHolding($live);
            if ($session === null) {
                continue;
            }
            $checked++;

            if ($service->hasVerificationHistory($session) || $service->isPayrollLocked($session)) {
                $skipped++;

                continue;
            }

            // Only re-home TRUSTWORTHY lives. A live with unpublished GMV (-1) or a
            // placeholder launch time must not be attributed by its (fake) time —
            // those are handled by revert-unpublished-gmv / flag-placeholder-lives,
            // not moved onto a guessed host.
            if ((float) $live->live_attributed_gmv_myr < 0 || $service->launchTimeIsPlaceholder($live)) {
                $skipped++;

                continue;
            }

            $correct = $service->correctAssignmentForLive($live);
            if ($correct === null) {
                $orphans++;

                continue;
            }
            if ($correct->live_host_id === null) {
                $skipped++;

                continue;
            }

            $current = $session->liveScheduleAssignment;
            if ($current !== null && $correct->id === $current->id) {
                continue; // already on the slot the matcher would pick — correct
            }

            $moves[] = [
                'live' => $live,
                'from_session' => $session,
                'to_assignment' => $correct,
            ];
        }

        if ($moves === []) {
            $this->info("Checked {$checked} verified live(s) · no mis-attributed commission found · {$orphans} orphan(s) · {$skipped} skipped.");

            return self::SUCCESS;
        }

        $this->table(
            ['live', 'GMV', 'from host (wrong)', 'to host (correct)', 'correct slot'],
            collect($moves)->map(fn (array $m) => [
                $m['live']->launched_time?->copy()->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d H:i') ?? '—',
                number_format((float) $m['live']->live_attributed_gmv_myr, 2),
                $m['from_session']->liveHost?->name ?? '—',
                $m['to_assignment']->liveHost?->name ?? '—',
                $this->slotLabel($m['to_assignment']),
            ])->all(),
        );

        if (! $apply) {
            $this->newLine();
            $this->warn(count($moves).' mis-attributed live(s) — re-run with --apply to move them to the correct host.'
                ." ({$orphans} orphan(s) with no matching slot, {$skipped} skipped.)");

            return self::SUCCESS;
        }

        $moved = 0;
        $failed = 0;
        foreach ($moves as $m) {
            try {
                $service->linkLiveToAssignment($m['live'], $m['to_assignment']);
                $moved++;
            } catch (\RuntimeException $e) {
                $failed++;
                $this->warn("  ! live {$m['live']->id}: {$e->getMessage()}");
            }
        }

        $this->info("Moved {$moved} live(s) to the correct host.".($failed > 0 ? " {$failed} failed (see above)." : ''));

        return self::SUCCESS;
    }

    private function sessionHolding(ActualLiveRecord $live): ?LiveSession
    {
        $sessionId = DB::table('live_session_actual_live_record')
            ->where('actual_live_record_id', $live->id)
            ->value('live_session_id')
            ?? LiveSession::query()->where('matched_actual_live_record_id', $live->id)->value('id');

        return $sessionId
            ? LiveSession::with(['liveScheduleAssignment.timeSlot', 'liveHost'])->find($sessionId)
            : null;
    }

    private function slotLabel(LiveScheduleAssignment $assignment): string
    {
        $slot = $assignment->timeSlot;
        if ($slot === null || $slot->start_time === null) {
            return '—';
        }

        return substr($slot->start_time, 0, 5).'–'.substr((string) $slot->end_time, 0, 5);
    }
}
