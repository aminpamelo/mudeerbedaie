<?php

namespace App\Services\LiveHost;

use App\Models\ActualLiveRecord;
use App\Models\LiveAccount;
use App\Models\LiveAnalytics;
use App\Models\LiveHostPayrollRun;
use App\Models\LiveScheduleAssignment;
use App\Models\LiveSession;
use App\Models\LiveSessionVerificationEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Auto-verify pipeline: for each synced TikTok live not yet attributed to a
 * session, find the hosted schedule slot on the same creator account and day
 * whose window overlaps the live, materialize the dated slot/session (carrying
 * the scheduled host), then link the live(s) and lock GMV — exactly what a PIC
 * would do by hand in the verify step, but automatically.
 *
 * Only ever touches a session that is `pending` and has never been verified or
 * rejected, so it never undoes a human decision and unverifying keeps a session
 * from being auto-verified again.
 */
class AutoVerifyService
{
    private const TIMEZONE = 'Asia/Kuala_Lumpur';

    /**
     * Don't lock a live until its last segment ended at least this long ago. A
     * still-running or only-just-ended broadcast has partial 24h-attributed GMV
     * and may still gain reconnect segments on a later sync — locking it now
     * captures the wrong (early) number and the wrong composition. The refresh
     * pass keeps the GMV current after the first verify.
     */
    private const SETTLE_MINUTES = 30;

    /**
     * Keep re-summing an auto-verified session's GMV until its live ended this
     * many hours ago — long enough for TikTok's 24h live-GMV attribution window
     * to close. Past this the numbers are final and the session is left frozen.
     */
    private const REFRESH_WINDOW_HOURS = 30;

    public function __construct(private readonly ActualLiveRecordCandidateFinder $candidateFinder) {}

    /**
     * @return array<string, int>
     */
    public function run(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $lives = $this->unmatchedLives($from, $to);
        $stats = [
            'scanned' => $lives->count(),
            'sessions_verified' => 0,
            'records_linked' => 0,
            'no_match' => 0,
            'no_host' => 0,
            'unsettled' => 0,
            'skipped' => 0,
        ];

        if ($lives->isEmpty()) {
            return $stats;
        }

        $index = $this->accountIndex($lives);
        $consumed = [];

        foreach ($lives as $live) {
            if (in_array($live->id, $consumed, true)) {
                continue;
            }

            $account = $this->resolveAccount($live, $index);
            if ($account === null) {
                $stats['no_match']++;

                continue;
            }

            $klStart = CarbonImmutable::parse($live->launched_time)->setTimezone(self::TIMEZONE);
            $assignment = $this->matchingAssignment($account, $live, $klStart);
            if ($assignment === null) {
                $stats['no_match']++;

                continue;
            }
            if ($assignment->live_host_id === null) {
                $stats['no_host']++;

                continue;
            }

            $session = $this->sessionFor($assignment, $klStart);
            if ($session === null
                || $session->verification_status !== 'pending'
                || $this->hasVerificationHistory($session)
                || $this->isPayrollLocked($session)) {
                $stats['skipped']++;

                continue;
            }

            $records = $this->clusterFor($session);
            if ($records->isEmpty() || $this->anyLinkedElsewhere($records, $session)) {
                $stats['skipped']++;

                continue;
            }

            // Settle delay: skip a cluster whose last segment is still running or
            // only just ended — its GMV and split segments are not final yet. The
            // next cycle (once settled) picks it up.
            if (! $this->clusterHasSettled($records)) {
                $stats['unsettled']++;

                continue;
            }

            $this->verify($session, $records);
            $stats['sessions_verified']++;
            $stats['records_linked'] += $records->count();
            foreach ($records as $r) {
                $consumed[] = $r->id;
            }
        }

        return $stats;
    }

    /**
     * Read-only: explain why the auto-verifier would (or would not) verify a
     * single session — mirrors the per-session gates in run(), and names the
     * other session(s) already holding the candidate lives when that's the
     * blocker. For the livehost:explain-autoverify diagnostic.
     *
     * @return array<string, mixed>
     */
    public function explainSession(LiveSession $session): array
    {
        $candidates = $this->candidateFinder->forSession($session);
        $clusterIds = $candidates->isEmpty() ? [] : $this->candidateFinder->suggestedClusterIds($candidates, $session);
        $records = $candidates->whereIn('id', $clusterIds)->values();
        $recordIds = $records->pluck('id')->all();

        $heldByOthers = [];
        if ($recordIds !== []) {
            $heldByOthers = DB::table('live_session_actual_live_record')
                ->whereIn('actual_live_record_id', $recordIds)
                ->where('live_session_id', '!=', $session->id)
                ->pluck('live_session_id')
                ->merge(
                    LiveSession::query()
                        ->whereIn('matched_actual_live_record_id', $recordIds)
                        ->where('id', '!=', $session->id)
                        ->pluck('id')
                )
                ->unique()
                ->values()
                ->all();
        }

        $hasHistory = $this->hasVerificationHistory($session);
        $payrollLocked = $this->isPayrollLocked($session);
        $linkedElsewhere = $records->isNotEmpty() && $this->anyLinkedElsewhere($records, $session);
        $settled = $records->isEmpty() ? true : $this->clusterHasSettled($records);

        $verdict = match (true) {
            $session->verification_status !== 'pending' => "skip: not pending (already {$session->verification_status})",
            $hasHistory => 'skip: has verification history (touched before)',
            $payrollLocked => 'skip: payroll period locked',
            $records->isEmpty() => 'no-match: no candidate TikTok live for this slot',
            $linkedElsewhere => 'skip: candidate live(s) already linked to session(s) '.implode(', ', $heldByOthers),
            ! $settled => 'wait: cluster not settled yet',
            default => 'WOULD VERIFY',
        };

        return [
            'session_id' => $session->id,
            'verification_status' => $session->verification_status,
            'candidate_count' => $candidates->count(),
            'suggested_cluster' => $recordIds,
            'held_by_other_sessions' => $heldByOthers,
            'has_verification_history' => $hasHistory,
            'payroll_locked' => $payrollLocked,
            'cluster_settled' => $settled,
            'verdict' => $verdict,
        ];
    }

    /**
     * Keep already auto-verified sessions honest as fresh sync data lands. For
     * every session auto-verify locked (and that no human has since touched, and
     * whose payroll is still open, and whose live is recent enough that GMV may
     * still be moving), re-derive the linked cluster and re-sum GMV from the
     * LATEST synced records — so a session locked early tracks TikTok's growing
     * 24h attribution instead of freezing at a partial number. It also pulls in
     * reconnect segments that only synced after the first lock, and reconciles
     * drift (a live whose slot no longer matches where it is linked).
     *
     * Only ever GROWS a session's record set on a plain refresh — a shrink (the
     * algorithm would drop a currently-linked live) is flagged for a human, never
     * applied silently, so we can't quietly erase attributed GMV.
     *
     * @return array{stats: array<string, int>, findings: array<int, array<string, mixed>>}
     */
    public function refresh(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $stats = [
            'refresh_scanned' => 0,
            'gmv_updated' => 0,
            'segments_added' => 0,
            'drift_fixed' => 0,
            'drift_flagged' => 0,
        ];
        /** @var array<int, array<string, mixed>> $findings */
        $findings = [];

        $cutoff = $to->subHours(self::REFRESH_WINDOW_HOURS);

        $sessions = LiveSession::query()
            ->where('auto_verified', true)
            ->where('verification_status', 'verified')
            ->whereNotNull('matched_actual_live_record_id')
            ->where(function ($q) use ($cutoff) {
                $q->where('actual_end_at', '>=', $cutoff)
                    ->orWhere('actual_start_at', '>=', $cutoff);
            })
            ->with(['actualLiveRecords', 'liveAccount', 'matchedActualLiveRecord', 'liveScheduleAssignment.timeSlot'])
            ->orderBy('scheduled_start_at')
            ->get()
            ->filter(fn (LiveSession $s) => ! $this->hasVerificationHistory($s) && ! $this->isPayrollLocked($s))
            ->values();

        $stats['refresh_scanned'] = $sessions->count();

        foreach ($sessions as $session) {
            // Drift first: if the primary live's slot no longer matches this
            // session and it clearly belongs elsewhere, move (or flag) it. A move
            // re-verifies the target, so we skip the same-session refresh below.
            if ($this->reconcileDrift($session, $stats, $findings)) {
                continue;
            }

            $this->refreshSession($session, $stats, $findings);
        }

        return ['stats' => $stats, 'findings' => $findings];
    }

    /**
     * Re-sum an auto-verified session against the latest synced records and pull
     * in any newly-synced contiguous segments. Never drops a currently-linked
     * record (that is flagged as drift instead).
     *
     * @param  array<string, int>  $stats
     * @param  array<int, array<string, mixed>>  $findings
     */
    private function refreshSession(LiveSession $session, array &$stats, array &$findings): void
    {
        $current = $session->actualLiveRecords()->get();
        if ($current->isEmpty()) {
            return;
        }

        $records = $this->clusterFor($session);
        if ($records->isEmpty()) {
            return;
        }

        $recordIds = $records->pluck('id')->map(fn ($v) => (int) $v)->all();
        $currentIds = $current->pluck('id')->map(fn ($v) => (int) $v)->all();

        // A plain refresh only ever adds segments. If the cluster algorithm now
        // wants to DROP a linked live, that is a composition change a human should
        // review — flag it, don't erase attributed GMV automatically.
        $dropped = array_values(array_diff($currentIds, $recordIds));
        if ($dropped !== []) {
            $findings[] = [
                'type' => 'shrink',
                'session_id' => $session->id,
                'would_drop' => $dropped,
            ];
            $stats['drift_flagged']++;

            return;
        }

        if ($this->anyLinkedElsewhere($records, $session)) {
            return;
        }

        $added = array_values(array_diff($recordIds, $currentIds));
        $newGmv = round($records->sum(fn (ActualLiveRecord $r) => max(0.0, (float) $r->live_attributed_gmv_myr)), 2);
        $currentGmv = round((float) $session->gmv_amount, 2);

        if ($added === [] && abs($newGmv - $currentGmv) < 0.005) {
            return;
        }

        $this->verify($session, $records);
        $stats['gmv_updated']++;
        if ($added !== []) {
            $stats['segments_added'] += count($added);
        }
    }

    /**
     * Detect and (when clearly safe) repair a session whose primary live no
     * longer sits in the slot it is linked to — e.g. the schedule was edited
     * after auto-verify locked it. Auto-heal only fires when the correct slot is
     * genuinely empty and pending and nothing is payroll-locked; anything more
     * ambiguous is flagged for a human. Returns true when the session was moved.
     *
     * @param  array<string, int>  $stats
     * @param  array<int, array<string, mixed>>  $findings
     */
    private function reconcileDrift(LiveSession $session, array &$stats, array &$findings): bool
    {
        $primary = $session->matchedActualLiveRecord;
        $account = $session->liveAccount;
        if ($primary === null || $account === null || $primary->launched_time === null) {
            return false;
        }

        $klStart = CarbonImmutable::parse($primary->launched_time)->setTimezone(self::TIMEZONE);

        // Still sitting in its own slot? Then there is no drift to reconcile.
        $own = $session->liveScheduleAssignment;
        if ($own !== null && $this->assignmentOverlapsLive($own, $primary, $klStart)) {
            return false;
        }

        $correct = $this->matchingAssignment($account, $primary, $klStart);
        if ($correct === null) {
            $findings[] = [
                'type' => 'orphaned',
                'session_id' => $session->id,
                'live_id' => $primary->id,
            ];
            $stats['drift_flagged']++;

            return false;
        }

        $target = $this->sessionFor($correct, $klStart);
        if ($target === null || $target->id === $session->id) {
            return false;
        }

        $safe = $correct->live_host_id !== null
            && $target->verification_status === 'pending'
            && ! $this->hasVerificationHistory($target)
            && $target->actualLiveRecords()->doesntExist()
            && ! $this->isPayrollLocked($session)
            && ! $this->isPayrollLocked($target);

        if (! $safe) {
            $findings[] = [
                'type' => 'conflict',
                'session_id' => $session->id,
                'suggested_session_id' => $target->id,
                'live_id' => $primary->id,
            ];
            $stats['drift_flagged']++;

            return false;
        }

        // Move the whole cluster to the correct slot: strip the source session
        // (revert to pending) and re-verify the target with the same records.
        $records = $session->actualLiveRecords()->get();
        DB::transaction(function () use ($session): void {
            $session->actualLiveRecords()->detach();
            $session->update([
                'matched_actual_live_record_id' => null,
                'gmv_amount' => 0,
                'gmv_source' => 'manual',
                'gmv_locked_at' => null,
                'verification_status' => 'pending',
                'verified_by' => null,
                'verified_at' => null,
                'auto_verified' => false,
                'status' => 'scheduled',
            ]);
        });
        $this->verify($target, $records);

        $findings[] = [
            'type' => 'moved',
            'session_id' => $session->id,
            'target_session_id' => $target->id,
            'live_id' => $primary->id,
        ];
        $stats['drift_fixed']++;

        return true;
    }

    /**
     * Whether the given schedule slot's time window overlaps the live's window
     * (both reduced to KL minutes-of-day). Shared by the matcher and drift check.
     */
    private function assignmentOverlapsLive(LiveScheduleAssignment $assignment, ActualLiveRecord $live, CarbonImmutable $klStart): bool
    {
        $slot = $assignment->timeSlot;
        if (! $slot) {
            return false;
        }

        $liveStartMin = ((int) $klStart->format('G')) * 60 + (int) $klStart->format('i');
        $klEnd = $this->liveEndKl($live, $klStart);
        $liveEndMin = ((int) $klEnd->format('G')) * 60 + (int) $klEnd->format('i');
        if ($liveEndMin <= $liveStartMin) {
            $liveEndMin = $liveStartMin + 1;
        }

        $slotStart = $this->toMinutes($slot->start_time);
        $slotEnd = $this->toMinutes($slot->end_time);

        return $liveStartMin < $slotEnd && $liveEndMin > $slotStart;
    }

    /**
     * True once every segment of the cluster ended at least SETTLE_MINUTES ago —
     * i.e. the broadcast is finished and its 24h GMV has begun to accrue, so it
     * is safe to lock (the refresh pass then tracks the growing attribution).
     *
     * @param  Collection<int, ActualLiveRecord>  $records
     */
    private function clusterHasSettled(Collection $records): bool
    {
        $lastEnd = $records->map(fn (ActualLiveRecord $r) => $this->liveEndUtc($r))->max();
        if ($lastEnd === null) {
            return true;
        }

        return $lastEnd->lessThanOrEqualTo(CarbonImmutable::now()->subMinutes(self::SETTLE_MINUTES));
    }

    /**
     * Manually link ONE TikTok live to a chosen schedule slot — the backend of
     * the calendar's drag-and-drop matching. Unlike run(), the PIC has already
     * decided the pairing, so this skips the auto-matcher: it materializes the
     * dated slot + session (creating the session if the host never recapped),
     * then links the live and locks GMV via the same verify() path.
     *
     * If the live is already linked to a DIFFERENT session, it is MOVED here:
     * detached from the old session (which is re-verified against its remaining
     * lives, or reverted to pending if it now has none) before being linked to
     * this one — so a PIC can drag a matched live onto the correct slot.
     *
     * @throws \RuntimeException on a guard violation (no host, payroll locked on
     *                           either the source or the target session).
     */
    public function linkLiveToAssignment(ActualLiveRecord $live, LiveScheduleAssignment $assignment): LiveSession
    {
        if ($assignment->live_host_id === null) {
            throw new \RuntimeException('This slot has no host assigned yet — assign a host before linking.');
        }

        $currentSession = $this->sessionHolding($live);

        $klStart = CarbonImmutable::parse($live->launched_time)->setTimezone(self::TIMEZONE);

        $dated = $assignment->is_template
            ? LiveScheduleAssignment::firstOrCreate(
                [
                    'live_account_id' => $assignment->live_account_id,
                    'time_slot_id' => $assignment->time_slot_id,
                    'schedule_date' => $klStart->toDateString(),
                    'is_template' => false,
                ],
                [
                    'platform_account_id' => $assignment->platform_account_id,
                    'live_host_platform_account_id' => $assignment->live_host_platform_account_id,
                    'live_host_id' => $assignment->live_host_id,
                    'day_of_week' => (int) $klStart->format('w'),
                    'status' => 'scheduled',
                    'created_by' => $assignment->created_by,
                ]
            )
            : $assignment;

        $session = $dated->liveSession()->first();

        if ($session === null) {
            $scheduledStart = $klStart;
            $slot = $dated->timeSlot()->first();
            if ($slot && $slot->start_time) {
                $scheduledStart = CarbonImmutable::parse(
                    $klStart->toDateString().' '.$slot->start_time,
                    self::TIMEZONE
                );
            }

            $session = LiveSession::create([
                'platform_account_id' => $dated->platform_account_id,
                'live_host_platform_account_id' => $dated->live_host_platform_account_id,
                'live_account_id' => $dated->live_account_id,
                'live_schedule_assignment_id' => $dated->id,
                'live_host_id' => $dated->live_host_id,
                'title' => 'Live',
                'scheduled_start_at' => $scheduledStart,
                'status' => 'scheduled',
            ]);
        }

        if ($this->isPayrollLocked($session)) {
            throw new \RuntimeException('Payroll is locked for this period — linking is no longer allowed.');
        }

        // Re-link: peel the live off its previous session first (recomputing that
        // session's GMV, or reverting it to pending if it is left with no lives).
        if ($currentSession !== null && $currentSession->id !== $session->id) {
            if ($this->isPayrollLocked($currentSession)) {
                throw new \RuntimeException('This live is on a payroll-locked session — unlink it there first.');
            }
            $this->detachAndRecompute($currentSession, $live);
        }

        // A session can hold many lives (a split broadcast: went live, stopped,
        // resumed — same host). Accumulate onto whatever is already linked so a
        // second drop ADDS a live and re-sums GMV rather than replacing.
        $records = $session->actualLiveRecords()->get()
            ->reject(fn (ActualLiveRecord $r) => $r->id === $live->id)
            ->push($live)
            ->values();

        $this->verify($session, $records, false);

        return $session->refresh();
    }

    /**
     * Break the link between a TikTok live and its session — the "cut" button on
     * the calendar. The live returns to the unmatched pool; the session is
     * re-settled (re-verified against any remaining lives, or reverted to
     * pending if it now has none).
     *
     * @throws \RuntimeException when the session sits in a locked payroll period.
     */
    public function unlinkLive(ActualLiveRecord $live): void
    {
        $session = $this->sessionHolding($live);
        if ($session === null) {
            return;
        }

        if ($this->isPayrollLocked($session)) {
            throw new \RuntimeException('Payroll is locked for this period — unlinking is no longer allowed.');
        }

        $this->detachAndRecompute($session, $live);
    }

    /**
     * The session a live is currently linked to (via the pivot or the legacy
     * denormalized pointer), or null.
     */
    private function sessionHolding(ActualLiveRecord $live): ?LiveSession
    {
        $sessionId = DB::table('live_session_actual_live_record')
            ->where('actual_live_record_id', $live->id)
            ->value('live_session_id')
            ?? LiveSession::query()
                ->where('matched_actual_live_record_id', $live->id)
                ->value('id');

        return $sessionId ? LiveSession::find($sessionId) : null;
    }

    /**
     * Remove one live from a session and re-settle it: re-verify against the
     * remaining lives, or — if none remain — revert the session to pending so it
     * no longer claims verified GMV it can't back up.
     */
    private function detachAndRecompute(LiveSession $session, ActualLiveRecord $live): void
    {
        $remaining = $session->actualLiveRecords()->get()
            ->reject(fn (ActualLiveRecord $r) => $r->id === $live->id)
            ->values();

        if ($remaining->isEmpty()) {
            DB::transaction(function () use ($session) {
                $session->actualLiveRecords()->detach();
                $session->update([
                    'matched_actual_live_record_id' => null,
                    'gmv_amount' => 0,
                    'gmv_source' => 'manual',
                    'gmv_locked_at' => null,
                    'verification_status' => 'pending',
                    'verified_by' => null,
                    'verified_at' => null,
                    'auto_verified' => false,
                    'status' => 'scheduled',
                ]);
            });

            return;
        }

        $this->verify($session, $remaining, false);
    }

    /**
     * @return Collection<int, ActualLiveRecord>
     */
    private function unmatchedLives(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return ActualLiveRecord::query()
            ->whereBetween('launched_time', [$from, $to])
            ->whereNotIn('id', fn ($q) => $q->select('actual_live_record_id')->from('live_session_actual_live_record'))
            ->whereNotIn('id', fn ($q) => $q->select('matched_actual_live_record_id')->from('live_sessions')->whereNotNull('matched_actual_live_record_id'))
            ->orderBy('launched_time')
            ->get();
    }

    /**
     * @param  Collection<int, ActualLiveRecord>  $lives
     * @return array{byCreator: array<string, LiveAccount>, byHandle: array<string, LiveAccount>}
     */
    private function accountIndex(Collection $lives): array
    {
        $creatorIds = $lives->pluck('creator_platform_user_id')
            ->filter()->map(fn ($v) => trim((string) $v))->unique()->values();
        $handles = $lives->pluck('creator_handle')
            ->map(fn ($h) => LiveAccount::normalizeHandle($h))->filter()->unique()->values();

        $accounts = LiveAccount::query()
            ->where(function ($q) use ($creatorIds, $handles) {
                if ($creatorIds->isNotEmpty()) {
                    $q->orWhereIn('creator_user_id', $creatorIds->all());
                }
                if ($handles->isNotEmpty()) {
                    $q->orWhereIn('normalized_handle', $handles->all());
                }
            })
            ->get();

        $byCreator = [];
        $byHandle = [];
        foreach ($accounts as $a) {
            if ($a->creator_user_id !== null) {
                $byCreator[trim((string) $a->creator_user_id)] = $a;
            }
            if ($a->normalized_handle !== null) {
                $byHandle[$a->normalized_handle] = $a;
            }
        }

        return ['byCreator' => $byCreator, 'byHandle' => $byHandle];
    }

    /**
     * @param  array{byCreator: array<string, LiveAccount>, byHandle: array<string, LiveAccount>}  $index
     */
    private function resolveAccount(ActualLiveRecord $live, array $index): ?LiveAccount
    {
        $creatorId = $live->creator_platform_user_id !== null ? trim((string) $live->creator_platform_user_id) : null;
        if ($creatorId !== null && $creatorId !== '' && isset($index['byCreator'][$creatorId])) {
            return $index['byCreator'][$creatorId];
        }

        $handle = LiveAccount::normalizeHandle($live->creator_handle);

        return $handle !== null ? ($index['byHandle'][$handle] ?? null) : null;
    }

    /**
     * The hosted schedule slot (dated preferred over template) on this account
     * and day whose time window overlaps the live.
     */
    private function matchingAssignment(LiveAccount $account, ActualLiveRecord $live, CarbonImmutable $klStart): ?LiveScheduleAssignment
    {
        $date = $klStart->toDateString();
        $dow = (int) $klStart->format('w');

        return LiveScheduleAssignment::query()
            ->where('live_account_id', $account->id)
            ->whereNotNull('live_host_id')
            ->where(function ($q) use ($date, $dow) {
                $q->where(fn ($d) => $d->where('is_template', false)->whereDate('schedule_date', $date))
                    ->orWhere(fn ($t) => $t->where('is_template', true)->where('day_of_week', $dow));
            })
            ->with('timeSlot')
            ->get()
            ->filter(fn (LiveScheduleAssignment $a) => $this->assignmentOverlapsLive($a, $live, $klStart))
            ->sortBy(fn (LiveScheduleAssignment $a) => $a->is_template ? 1 : 0)
            ->first();
    }

    /**
     * The LiveSession for the matched slot. A dated slot already materialized one
     * via the observer; a template match materializes a dated slot for this date
     * (respecting any manual dated slot already present).
     */
    private function sessionFor(LiveScheduleAssignment $assignment, CarbonImmutable $klStart): ?LiveSession
    {
        if (! $assignment->is_template) {
            return $assignment->liveSession()->first();
        }

        $dated = LiveScheduleAssignment::firstOrCreate(
            [
                'live_account_id' => $assignment->live_account_id,
                'time_slot_id' => $assignment->time_slot_id,
                'schedule_date' => $klStart->toDateString(),
                'is_template' => false,
            ],
            [
                'platform_account_id' => $assignment->platform_account_id,
                'live_host_platform_account_id' => $assignment->live_host_platform_account_id,
                'live_host_id' => $assignment->live_host_id,
                'day_of_week' => (int) $klStart->format('w'),
                'status' => 'scheduled',
                'created_by' => $assignment->created_by,
            ]
        );

        return $dated->liveSession()->first();
    }

    /**
     * @return Collection<int, ActualLiveRecord>
     */
    private function clusterFor(LiveSession $session): Collection
    {
        $candidates = $this->candidateFinder->forSession($session);
        if ($candidates->isEmpty()) {
            return collect();
        }

        $ids = $this->candidateFinder->suggestedClusterIds($candidates, $session);

        return $candidates->whereIn('id', $ids)->values();
    }

    /**
     * @param  Collection<int, ActualLiveRecord>  $records
     */
    private function anyLinkedElsewhere(Collection $records, LiveSession $session): bool
    {
        $ids = $records->pluck('id')->all();

        return DB::table('live_session_actual_live_record')
            ->whereIn('actual_live_record_id', $ids)
            ->where('live_session_id', '!=', $session->id)
            ->exists()
            || LiveSession::query()
                ->whereIn('matched_actual_live_record_id', $ids)
                ->where('id', '!=', $session->id)
                ->exists();
    }

    private function hasVerificationHistory(LiveSession $session): bool
    {
        return LiveSessionVerificationEvent::query()
            ->where('live_session_id', $session->id)
            ->exists();
    }

    private function isPayrollLocked(LiveSession $session): bool
    {
        if ($session->actual_end_at === null) {
            return false;
        }

        return LiveHostPayrollRun::query()
            ->where('status', 'locked')
            ->where('period_start', '<=', $session->actual_end_at)
            ->where('period_end', '>=', $session->actual_end_at)
            ->exists();
    }

    /**
     * @param  Collection<int, ActualLiveRecord>  $records
     */
    private function verify(LiveSession $session, Collection $records, bool $auto = true): void
    {
        DB::transaction(function () use ($session, $records, $auto) {
            $primary = $records->sortBy('launched_time')->first();
            $summedGmv = $records->sum(fn (ActualLiveRecord $r) => max(0.0, (float) $r->live_attributed_gmv_myr));
            $lastEnd = $records->map(fn (ActualLiveRecord $r) => $this->liveEndUtc($r))->max();

            $session->actualLiveRecords()->sync(
                $records->mapWithKeys(fn (ActualLiveRecord $r) => [
                    $r->id => [
                        'is_primary' => $r->id === $primary->id,
                        'live_attributed_gmv_myr' => max(0.0, (float) $r->live_attributed_gmv_myr),
                        'linked_by' => null,
                        'linked_at' => now(),
                    ],
                ])->all()
            );

            $session->update([
                'matched_actual_live_record_id' => $primary->id,
                'gmv_amount' => $summedGmv,
                'gmv_source' => 'tiktok_actual',
                'gmv_locked_at' => now(),
                'verification_status' => 'verified',
                'verified_by' => null,
                'verified_at' => now(),
                'verification_notes' => $auto ? 'Auto-verified from schedule.' : 'Linked from the calendar (drag & drop).',
                'auto_verified' => $auto,
                'status' => 'ended',
                'actual_start_at' => $primary->launched_time,
                'actual_end_at' => $lastEnd,
            ]);

            LiveAnalytics::updateOrCreate(
                ['live_session_id' => $session->id],
                [
                    'viewers_peak' => (int) $records->max('viewers'),
                    'viewers_avg' => (int) $records->max('viewers'),
                    'total_likes' => (int) $records->sum('likes'),
                    'total_comments' => (int) $records->sum('comments'),
                    'total_shares' => (int) $records->sum('shares'),
                    'duration_minutes' => (int) round($records->sum('duration_seconds') / 60),
                ]
            );
        });
    }

    private function liveEndKl(ActualLiveRecord $live, CarbonImmutable $klStart): CarbonImmutable
    {
        if ($live->ended_time) {
            return CarbonImmutable::parse($live->ended_time)->setTimezone(self::TIMEZONE);
        }
        if ($live->duration_seconds) {
            return $klStart->addSeconds((int) $live->duration_seconds);
        }

        return $klStart->addHour();
    }

    private function liveEndUtc(ActualLiveRecord $r): CarbonImmutable
    {
        if ($r->ended_time) {
            return CarbonImmutable::parse($r->ended_time);
        }
        $start = CarbonImmutable::parse($r->launched_time);
        if ($r->duration_seconds) {
            return $start->addSeconds((int) $r->duration_seconds);
        }

        return $start->addHour();
    }

    private function toMinutes(?string $time): int
    {
        if (! $time) {
            return 0;
        }
        [$h, $m] = array_map('intval', explode(':', substr($time, 0, 5)));

        return $h * 60 + $m;
    }
}
