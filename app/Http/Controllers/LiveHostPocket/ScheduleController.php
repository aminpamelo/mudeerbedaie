<?php

namespace App\Http\Controllers\LiveHostPocket;

use App\Http\Controllers\Controller;
use App\Models\LiveScheduleAssignment;
use App\Models\LiveSession;
use App\Models\SessionReplacementRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Live Host Pocket — Schedule (weekly read-only).
 *
 * Shows the host's `LiveScheduleAssignment` rows (created by the PIC from
 * the Session Slots calendar/table) grouped by day-of-week. Cancelled rows
 * are hidden. Self-assign / unassign is intentionally not exposed here —
 * hosts are nudged to contact their PIC for schedule changes.
 *
 * Time windows come from the related `LiveTimeSlot` (start_time / end_time).
 * Those columns are stored as SQL `time` and reach PHP as either strings
 * ("HH:MM:SS") on MariaDB/MySQL or Carbon-ish on SQLite — both are
 * normalised to "HH:MM".
 */
class ScheduleController extends Controller
{
    private const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    private const DAY_SHORT = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    public function index(Request $request): Response
    {
        $host = $request->user();

        $assignments = LiveScheduleAssignment::query()
            ->with(['timeSlot', 'platformAccount.platform'])
            ->where('live_host_id', $host->id)
            ->where('status', '!=', 'cancelled')
            ->orderBy('day_of_week')
            ->get();

        $activeRequests = SessionReplacementRequest::query()
            ->whereIn('live_schedule_assignment_id', $assignments->pluck('id'))
            ->whereIn('status', [
                SessionReplacementRequest::STATUS_PENDING,
                SessionReplacementRequest::STATUS_ASSIGNED,
            ])
            ->with('replacementHost:id,name')
            ->get()
            ->groupBy('live_schedule_assignment_id');

        $schedules = $assignments
            ->map(function (LiveScheduleAssignment $assignment) use ($activeRequests): array {
                $request = $activeRequests->get($assignment->id)?->first();

                return [
                    'id' => $assignment->id,
                    'dayOfWeek' => (int) $assignment->day_of_week,
                    'dayName' => self::DAY_NAMES[$assignment->day_of_week] ?? 'Unknown',
                    'dayShort' => self::DAY_SHORT[$assignment->day_of_week] ?? '—',
                    'startTime' => $this->formatTime($assignment->timeSlot?->start_time),
                    'endTime' => $this->formatTime($assignment->timeSlot?->end_time),
                    'platformAccount' => $assignment->platformAccount?->name,
                    'platformType' => $assignment->platformAccount?->platform?->slug,
                    'isRecurring' => (bool) $assignment->is_template,
                    'remarks' => $assignment->remarks,
                    'replacementRequest' => $request ? [
                        'id' => $request->id,
                        'status' => $request->status,
                        'scope' => $request->scope,
                        'targetDate' => $request->target_date?->toDateString(),
                        'replacementHostName' => $request->replacementHost?->name,
                        'reasonCategory' => $request->reason_category,
                        'requestedAt' => $request->requested_at?->toIso8601String(),
                    ] : null,
                ];
            })
            ->sortBy([
                ['dayOfWeek', 'asc'],
                ['startTime', 'asc'],
            ])
            ->values();

        $grouped = collect(range(0, 6))->map(fn (int $day): array => [
            'dayOfWeek' => $day,
            'dayName' => self::DAY_NAMES[$day],
            'dayShort' => self::DAY_SHORT[$day],
            'schedules' => $schedules->where('dayOfWeek', $day)->values()->all(),
        ])->all();

        return Inertia::render('Schedule', [
            'days' => $grouped,
            'totalSlots' => $schedules->count(),
            'pendingRecaps' => $this->pendingRecapsFor($host->id),
        ]);
    }

    /**
     * Past sessions still owed work by the host:
     *
     *   - status='ended' AND zero attachments  → "PERLU UPLOAD"
     *   - status='scheduled' AND start_at past AND can be recapped
     *     → "REKAP TERTUNDA" (host needs to confirm went-live + upload, OR
     *       mark "didn't go live")
     *
     * @return array<int, array<string, mixed>>
     */
    private function pendingRecapsFor(int $hostId): array
    {
        return LiveSession::query()
            ->with(['platformAccount.platform'])
            ->withCount('attachments')
            ->where('live_host_id', $hostId)
            ->where(function ($q): void {
                $q->where('status', 'ended')
                    ->orWhere(fn ($q) => $q->where('status', 'scheduled')
                        ->where('scheduled_start_at', '<', now()));
            })
            ->orderByDesc('scheduled_start_at')
            ->limit(20)
            ->get()
            ->filter(function (LiveSession $session): bool {
                if ($session->status === 'ended') {
                    return ((int) ($session->attachments_count ?? 0)) === 0;
                }

                return $session->canRecap();
            })
            ->values()
            ->map(fn (LiveSession $session): array => [
                'id' => $session->id,
                'title' => $session->title,
                'status' => $session->status,
                'platformAccount' => $session->platformAccount?->name,
                'platformType' => $session->platformAccount?->platform?->slug,
                'scheduledStartAt' => $session->scheduled_start_at?->toIso8601String(),
                'actualStartAt' => $session->actual_start_at?->toIso8601String(),
                'actualEndAt' => $session->actual_end_at?->toIso8601String(),
                'durationMinutes' => $session->duration_minutes,
                'needsUpload' => $session->status === 'ended',
            ])
            ->all();
    }

    private function formatTime(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof Carbon) {
            return $value->format('H:i');
        }

        return substr((string) $value, 0, 5);
    }
}
