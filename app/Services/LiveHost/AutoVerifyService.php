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
        $liveStartMin = ((int) $klStart->format('G')) * 60 + (int) $klStart->format('i');
        $klEnd = $this->liveEndKl($live, $klStart);
        $liveEndMin = ((int) $klEnd->format('G')) * 60 + (int) $klEnd->format('i');
        if ($liveEndMin <= $liveStartMin) {
            $liveEndMin = $liveStartMin + 1;
        }

        return LiveScheduleAssignment::query()
            ->where('live_account_id', $account->id)
            ->whereNotNull('live_host_id')
            ->where(function ($q) use ($date, $dow) {
                $q->where(fn ($d) => $d->where('is_template', false)->whereDate('schedule_date', $date))
                    ->orWhere(fn ($t) => $t->where('is_template', true)->where('day_of_week', $dow));
            })
            ->with('timeSlot')
            ->get()
            ->filter(function (LiveScheduleAssignment $a) use ($liveStartMin, $liveEndMin) {
                if (! $a->timeSlot) {
                    return false;
                }
                $slotStart = $this->toMinutes($a->timeSlot->start_time);
                $slotEnd = $this->toMinutes($a->timeSlot->end_time);

                return $liveStartMin < $slotEnd && $liveEndMin > $slotStart;
            })
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
