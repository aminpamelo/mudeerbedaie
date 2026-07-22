<?php

declare(strict_types=1);

namespace App\Services\LiveHost;

use App\Models\ActualLiveRecord;
use App\Models\LiveSession;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ActualLiveRecordCandidateFinder
{
    private const TIMEZONE = 'Asia/Kuala_Lumpur';

    private const MAX_CANDIDATES = 20;

    /**
     * Segments launched within this gap of the previous one's end are treated as
     * one physical live that blipped and reconnected (TikTok reports each as a
     * separate record). Used to pre-select the whole split as the suggestion.
     */
    private const CLUSTER_GAP_SECONDS = 20 * 60;

    /**
     * A blip-split cluster fills roughly one scheduled slot, so cap its total
     * span (first launch → last end). This stops long back-to-back lives whose
     * ends happen to abut the next start (tiny gap) from chaining into one giant
     * "cluster" across the whole day.
     */
    private const CLUSTER_MAX_SPAN_SECONDS = 3 * 3600;

    /**
     * Return candidate ActualLiveRecord rows for the given LiveSession, filtered to
     * same shop + same creator account + same calendar day (KL timezone),
     * excluding records already linked to other sessions, sorted by time
     * proximity to the session's scheduled_start_at.
     *
     * Identity comes from the session's live account (the punca kuasa): its
     * numeric Creator ID and/or normalized handle. We match actual records on
     * either, because CSV imports frequently lack the numeric id and carry only
     * the handle. Legacy sessions fall back to the old creator-identity pivot.
     */
    public function forSession(LiveSession $session): Collection
    {
        $account = $session->liveAccount;
        $creatorId = $account?->creator_user_id
            ?? $session->liveHostPlatformAccount?->creator_platform_user_id;
        $normalizedHandle = $account?->normalized_handle;

        if (($creatorId === null && $normalizedHandle === null) || $session->scheduled_start_at === null) {
            return collect();
        }

        $scheduledKl = $session->scheduled_start_at->copy()->setTimezone(self::TIMEZONE);
        $dayStart = $scheduledKl->copy()->startOfDay();
        $dayEnd = $scheduledKl->copy()->endOfDay();

        return ActualLiveRecord::query()
            ->where('platform_account_id', $session->platform_account_id)
            ->where(function ($q) use ($creatorId, $normalizedHandle) {
                if ($creatorId !== null) {
                    $q->orWhere('creator_platform_user_id', $creatorId);
                }
                if ($normalizedHandle !== null) {
                    $q->orWhereRaw('LOWER(TRIM(creator_handle)) = ?', [$normalizedHandle]);
                }
            })
            ->whereBetween('launched_time', [$dayStart, $dayEnd])
            ->whereNotIn('id', function ($q) use ($session) {
                // Hide records already attributed to OTHER sessions, but keep this
                // session's own linked records selectable (multi-record split lives).
                $q->select('actual_live_record_id')
                    ->from('live_session_actual_live_record')
                    ->where('live_session_id', '!=', $session->id);
            })
            // Belt-and-suspenders against the retained primary pointer (kept in
            // sync with the pivot): also exclude records primary-linked elsewhere.
            ->whereNotIn('id', function ($q) use ($session) {
                $q->select('matched_actual_live_record_id')
                    ->from('live_sessions')
                    ->whereNotNull('matched_actual_live_record_id')
                    ->where('id', '!=', $session->id);
            })
            ->get()
            ->sortBy(fn (ActualLiveRecord $r) => abs(
                $r->launched_time->diffInSeconds($session->scheduled_start_at, true)
            ))
            ->take(self::MAX_CANDIDATES)
            ->values();
    }

    /**
     * Given a session's candidate records, return the ids of the contiguous
     * cluster nearest the scheduled slot — i.e. the segments of one physical
     * live that blipped and reconnected. The verify modal pre-selects these so
     * a split live is linked as a set with a single click.
     *
     * @param  Collection<int, ActualLiveRecord>  $candidates
     * @return array<int, int>
     */
    public function suggestedClusterIds(Collection $candidates, LiveSession $session): array
    {
        if ($candidates->isEmpty() || $session->scheduled_start_at === null) {
            return [];
        }

        $sorted = $candidates
            ->filter(fn (ActualLiveRecord $r) => $r->launched_time !== null)
            ->sortBy(fn (ActualLiveRecord $r) => $r->launched_time->getTimestamp())
            ->values();

        /** @var array<int, array<int, ActualLiveRecord>> $runs */
        $runs = [];
        $current = [];
        $prevEnd = null;
        $runStart = null;

        $endOf = fn (ActualLiveRecord $r) => $r->ended_time
            ?? $r->launched_time->copy()->addSeconds((int) ($r->duration_seconds ?? 0));

        foreach ($sorted as $record) {
            $start = $record->launched_time;

            // Gap = this segment's start minus the previous segment's end. Carbon's
            // $a->diffInSeconds($b, false) returns ($b - $a), so
            // $prevEnd->diffInSeconds($start) is (start - prevEnd): positive when
            // there is a real gap between the two segments.
            $gapTooBig = $prevEnd !== null
                && $prevEnd->diffInSeconds($start, false) > self::CLUSTER_GAP_SECONDS;

            // Span cap: adding this segment must not stretch the run past one slot.
            $spanTooBig = $runStart !== null
                && $runStart->diffInSeconds($endOf($record), false) > self::CLUSTER_MAX_SPAN_SECONDS;

            if ($gapTooBig || $spanTooBig) {
                $runs[] = $current;
                $current = [];
                $runStart = null;
            }

            if ($runStart === null) {
                $runStart = $start;
            }
            $current[] = $record;
            $prevEnd = $endOf($record);
        }

        if ($current !== []) {
            $runs[] = $current;
        }

        $runs = array_values(array_filter($runs, fn (array $run) => $run !== []));
        if ($runs === []) {
            return [];
        }

        $middleDistance = fn (array $run) => abs(
            $run[intdiv(count($run), 2)]->launched_time->diffInSeconds($session->scheduled_start_at, true)
        );

        // Constrain the suggestion to THIS slot's time window when one is defined,
        // rather than picking whatever run sits nearest the scheduled start. When
        // several hosts share one creator account across a day they all see the
        // same day-wide candidate pool; proximity alone lets a 6:30 slot swallow a
        // 7:30 live that belongs to the next slot. Window overlap keeps each live
        // in the slot whose window contains it — mirroring the live→slot matcher.
        // A run outside the window is deliberately NOT suggested (return nothing)
        // instead of being claimed by proximity, so we never mis-attribute GMV.
        [$windowStart, $windowEnd] = $this->slotWindow($session);

        if ($windowStart !== null && $windowEnd !== null) {
            $overlapOf = function (array $run) use ($windowStart, $windowEnd, $endOf): float {
                $runStart = $run[0]->launched_time;
                $runEnd = $endOf($run[count($run) - 1]);
                // A still-running live has no end/duration yet, so its run is a
                // zero-length instant. Bump it to at least 1s (as the live→slot
                // matcher does) so it still counts as touching the window instead
                // of scoring zero overlap and being dropped.
                if ($runEnd->lessThanOrEqualTo($runStart)) {
                    $runEnd = $runStart->copy()->addSecond();
                }
                $overlapStart = $runStart->greaterThan($windowStart) ? $runStart : $windowStart;
                $overlapEnd = $runEnd->lessThan($windowEnd) ? $runEnd : $windowEnd;

                // Carbon: $a->diffInSeconds($b, false) === ($b - $a), so this is
                // (overlapEnd - overlapStart): positive only on a real overlap.
                return max(0.0, (float) $overlapStart->diffInSeconds($overlapEnd, false));
            };

            $overlapping = array_values(array_filter($runs, fn (array $run) => $overlapOf($run) > 0));
            if ($overlapping === []) {
                return [];
            }

            // Stable sorts, least-significant key first: tie-break on proximity,
            // then the dominant key — greatest overlap with the slot window.
            $best = collect($overlapping)
                ->sortBy($middleDistance)
                ->sortByDesc(fn (array $run) => $overlapOf($run))
                ->first();

            return collect($best)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        // No slot window (legacy/manually-created session): pick the run whose
        // middle segment launches closest to the scheduled slot.
        $best = collect($runs)->sortBy($middleDistance)->first() ?? [];

        return collect($best)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * The session's slot as an absolute [start, end] instant pair, derived from
     * scheduled_start_at (the materialized slot start) plus the slot's configured
     * duration. Returns [null, null] when the session has no resolvable slot
     * window, so the caller can fall back to proximity matching.
     *
     * @return array{0: ?CarbonInterface, 1: ?CarbonInterface}
     */
    private function slotWindow(LiveSession $session): array
    {
        $slot = $session->liveScheduleAssignment?->timeSlot;
        if ($slot === null || $slot->start_time === null || $slot->end_time === null || $session->scheduled_start_at === null) {
            return [null, null];
        }

        $start = $this->toSeconds($slot->start_time);
        $end = $this->toSeconds($slot->end_time);
        $durationSeconds = $end > $start ? $end - $start : $end + 86400 - $start;

        $windowStart = $session->scheduled_start_at->copy();

        return [$windowStart, $windowStart->copy()->addSeconds($durationSeconds)];
    }

    private function toSeconds(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', substr($time, 0, 5)));

        return $h * 3600 + $m * 60;
    }
}
