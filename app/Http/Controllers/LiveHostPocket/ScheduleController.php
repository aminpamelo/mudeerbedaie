<?php

namespace App\Http\Controllers\LiveHostPocket;

use App\Http\Controllers\Controller;
use App\Models\LiveSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Live Host Pocket — Schedule (weekly read-only).
 *
 * Shows the host's active `LiveSchedule` slots grouped by day-of-week.
 * Self-assign / unassign (claim slots) is NOT wired in Batch 3 — that flow
 * lives in the legacy Volt `schedule.blade.php` and is scoped for a later
 * batch. The UI nudges the host to contact their PIC if they need to
 * change slots.
 *
 * `live_schedules.start_time` and `end_time` are stored as SQL `time`
 * columns without any cast on the model, so they arrive as strings
 * ("HH:MM:SS") on MariaDB/MySQL but Carbon-ish on SQLite. We normalise
 * both branches to `HH:MM`.
 */
class ScheduleController extends Controller
{
    private const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    private const DAY_SHORT = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    public function index(Request $request): Response
    {
        $host = $request->user();

        $schedules = LiveSchedule::query()
            ->with(['platformAccount.platform'])
            ->where('live_host_id', $host->id)
            ->where('is_active', true)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->map(fn (LiveSchedule $schedule): array => [
                'id' => $schedule->id,
                'dayOfWeek' => (int) $schedule->day_of_week,
                'dayName' => self::DAY_NAMES[$schedule->day_of_week] ?? 'Unknown',
                'dayShort' => self::DAY_SHORT[$schedule->day_of_week] ?? '—',
                'startTime' => $this->formatTime($schedule->start_time),
                'endTime' => $this->formatTime($schedule->end_time),
                'platformAccount' => $schedule->platformAccount?->name,
                'platformType' => $schedule->platformAccount?->platform?->slug,
                'isRecurring' => (bool) $schedule->is_recurring,
                'remarks' => $schedule->remarks,
            ]);

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
        if ($value instanceof Carbon) {
            return $value->format('H:i');
        }

        return substr((string) $value, 0, 5);
    }
}
