<?php

namespace App\Http\Controllers\LiveHostPocket;

use App\Http\Controllers\Controller;
use App\Models\LiveScheduleAssignment;
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

        $schedules = LiveScheduleAssignment::query()
            ->with(['timeSlot', 'platformAccount.platform'])
            ->where('live_host_id', $host->id)
            ->where('status', '!=', 'cancelled')
            ->orderBy('day_of_week')
            ->get()
            ->map(fn (LiveScheduleAssignment $assignment): array => [
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
            ])
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
        ]);
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
