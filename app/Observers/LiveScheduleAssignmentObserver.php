<?php

namespace App\Observers;

use App\Models\LiveScheduleAssignment;
use App\Models\LiveSession;
use Carbon\Carbon;

/**
 * Bridges LiveScheduleAssignment (admin calendar) → LiveSession (host dashboard).
 *
 * The two models live in separate subsystems that were never linked. When an
 * admin assigns a dated slot (schedule_date set, is_template=false) we upsert a
 * matching LiveSession so the host's /live-host dashboard picks it up. Template
 * (recurring) assignments are ignored — only dated ones materialise.
 */
class LiveScheduleAssignmentObserver
{
    public function saved(LiveScheduleAssignment $assignment): void
    {
        if (! $this->shouldMaterialize($assignment)) {
            $this->removeLinkedSessionIfScheduled($assignment);

            return;
        }

        $assignment->loadMissing('timeSlot');

        if (! $assignment->timeSlot) {
            return;
        }

        $scheduledStartAt = $this->computeScheduledStartAt($assignment);
        $durationMinutes = $this->computeDurationMinutes($assignment);

        LiveSession::updateOrCreate(
            ['live_schedule_assignment_id' => $assignment->id],
            [
                'platform_account_id' => $assignment->platform_account_id,
                'live_host_platform_account_id' => $assignment->live_host_platform_account_id,
                'live_host_id' => $assignment->live_host_id,
                'title' => $this->sessionTitle($assignment),
                'status' => $this->mapStatus($assignment->status),
                'scheduled_start_at' => $scheduledStartAt,
                'duration_minutes' => $durationMinutes,
            ]
        );
    }

    /**
     * Fires before the SQL delete so the FK column on live_sessions still
     * references this row — if we waited for `deleted()` the `nullOnDelete`
     * cascade would already have wiped the link.
     */
    public function deleting(LiveScheduleAssignment $assignment): void
    {
        LiveSession::query()
            ->where('live_schedule_assignment_id', $assignment->id)
            ->where('status', 'scheduled')
            ->delete();
    }

    private function shouldMaterialize(LiveScheduleAssignment $assignment): bool
    {
        return ! $assignment->is_template && $assignment->schedule_date !== null;
    }

    private function removeLinkedSessionIfScheduled(LiveScheduleAssignment $assignment): void
    {
        LiveSession::query()
            ->where('live_schedule_assignment_id', $assignment->id)
            ->where('status', 'scheduled')
            ->delete();
    }

    private function computeScheduledStartAt(LiveScheduleAssignment $assignment): Carbon
    {
        $date = Carbon::parse($assignment->schedule_date)->format('Y-m-d');
        $time = substr((string) $assignment->timeSlot->start_time, 0, 8);

        return Carbon::parse("{$date} {$time}");
    }

    private function computeDurationMinutes(LiveScheduleAssignment $assignment): int
    {
        $start = Carbon::parse($assignment->timeSlot->start_time);
        $end = Carbon::parse($assignment->timeSlot->end_time);

        return (int) $start->diffInMinutes($end);
    }

    private function sessionTitle(LiveScheduleAssignment $assignment): string
    {
        $assignment->loadMissing('platformAccount');

        return $assignment->platformAccount?->name
            ? "Live · {$assignment->platformAccount->name}"
            : 'Live session';
    }

    /**
     * LiveScheduleAssignment uses more granular statuses than LiveSession.
     * Map them onto the 4 states the host dashboard understands. 'confirmed'
     * and 'scheduled' both present as upcoming; 'in_progress' → live; the rest
     * pass through directly.
     */
    private function mapStatus(?string $status): string
    {
        return match ($status) {
            'in_progress' => 'live',
            'completed' => 'ended',
            'cancelled' => 'cancelled',
            default => 'scheduled',
        };
    }
}
