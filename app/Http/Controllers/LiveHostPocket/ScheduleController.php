<?php

namespace App\Http\Controllers\LiveHostPocket;

use App\Http\Controllers\Controller;
use App\Models\LiveScheduleAssignment;
use App\Models\LiveSession;
use App\Models\LiveSessionAttachment;
use App\Models\SessionReplacementRequest;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
 * The view is week-aware: `?week=YYYY-MM-DD` selects the Sunday-anchored
 * week to display. Templates apply to every week; specific-date assignments
 * appear only on the week containing their `schedule_date`.
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

    private const MONTH_SHORT_MS = ['Jan', 'Feb', 'Mac', 'Apr', 'Mei', 'Jun', 'Jul', 'Ogo', 'Sep', 'Okt', 'Nov', 'Dis'];

    public function index(Request $request): Response
    {
        $host = $request->user();

        $weekStart = $this->resolveWeekStart($request->query('week'));
        $weekEnd = $weekStart->endOfWeek(CarbonImmutable::SATURDAY);
        $today = CarbonImmutable::today();
        $currentWeekStart = $today->startOfWeek(CarbonImmutable::SUNDAY);

        $weekDates = collect(range(0, 6))->mapWithKeys(
            fn (int $offset): array => [$offset => $weekStart->addDays($offset)]
        );

        $assignments = LiveScheduleAssignment::query()
            ->with(['timeSlot', 'platformAccount.platform'])
            ->where('live_host_id', $host->id)
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($weekStart, $weekEnd): void {
                // Using `whereDate` (not `whereBetween` on the raw column) so the
                // comparison is date-only — SQLite stores `date` columns as
                // "YYYY-MM-DD 00:00:00", which a raw string compare against
                // "YYYY-MM-DD" silently excludes via the trailing space.
                $q->where('is_template', true)
                    ->orWhere(function ($q) use ($weekStart, $weekEnd): void {
                        $q->whereNotNull('schedule_date')
                            ->whereDate('schedule_date', '>=', $weekStart->toDateString())
                            ->whereDate('schedule_date', '<=', $weekEnd->toDateString());
                    });
            })
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

        $sessionsBySlot = $this->sessionsBySlotKey(
            $host->id,
            $assignments->pluck('id')->all(),
            $weekStart,
            $weekEnd,
        );

        $schedules = $assignments
            ->map(function (LiveScheduleAssignment $assignment) use ($activeRequests, $sessionsBySlot, $weekDates): array {
                $dayOfWeek = (int) $assignment->day_of_week;
                $slotDate = $weekDates->get($dayOfWeek);
                $slotDateString = $slotDate?->toDateString();

                $request = ($activeRequests->get($assignment->id) ?? collect())
                    ->first(function (SessionReplacementRequest $req) use ($slotDateString): bool {
                        if ($req->scope === SessionReplacementRequest::SCOPE_PERMANENT) {
                            return true;
                        }

                        return $req->target_date?->toDateString() === $slotDateString;
                    });

                $slotSession = $slotDateString
                    ? ($sessionsBySlot[$assignment->id.'|'.$slotDateString] ?? null)
                    : null;
                $recapAction = $this->buildRecapAction($slotSession);

                return [
                    'id' => $assignment->id,
                    'dayOfWeek' => $dayOfWeek,
                    'dayName' => self::DAY_NAMES[$dayOfWeek] ?? 'Unknown',
                    'dayShort' => self::DAY_SHORT[$dayOfWeek] ?? '—',
                    'date' => $slotDateString,
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
                    'recapAction' => $recapAction,
                ];
            })
            ->sortBy([
                ['dayOfWeek', 'asc'],
                ['startTime', 'asc'],
            ])
            ->values();

        $grouped = $weekDates->map(function (CarbonImmutable $date, int $day) use ($schedules, $today): array {
            return [
                'dayOfWeek' => $day,
                'dayName' => self::DAY_NAMES[$day],
                'dayShort' => self::DAY_SHORT[$day],
                'date' => $date->toDateString(),
                'isPast' => $date->lt($today),
                'isToday' => $date->isSameDay($today),
                'schedules' => $schedules->where('dayOfWeek', $day)->values()->all(),
            ];
        })->values()->all();

        return Inertia::render('Schedule', [
            'days' => $grouped,
            'totalSlots' => $schedules->count(),
            'pendingRecaps' => $this->pendingRecapsFor($host->id),
            'week' => [
                'start' => $weekStart->toDateString(),
                'end' => $weekEnd->toDateString(),
                'label' => $this->weekLabel($weekStart, $weekEnd),
                'prev' => $weekStart->subWeek()->toDateString(),
                'next' => $weekStart->addWeek()->toDateString(),
                'today' => $currentWeekStart->toDateString(),
                'isCurrent' => $weekStart->equalTo($currentWeekStart),
            ],
        ]);
    }

    /**
     * Build a map of `"{assignmentId}|{Y-m-d}"` => LiveSession for every
     * session linked to the visible week's assignments. Keying by date as
     * well as assignment is what lets each slot card render the recap
     * indicator for the session that actually belongs to *that* date —
     * critical for recurring assignments that span multiple weeks.
     *
     * @param  array<int, int>  $assignmentIds
     * @return array<string, \App\Models\LiveSession>
     */
    private function sessionsBySlotKey(int $hostId, array $assignmentIds, CarbonImmutable $weekStart, CarbonImmutable $weekEnd): array
    {
        if ($assignmentIds === []) {
            return [];
        }

        return LiveSession::query()
            ->where('live_host_id', $hostId)
            ->whereIn('live_schedule_assignment_id', $assignmentIds)
            ->whereDate('scheduled_start_at', '>=', $weekStart->toDateString())
            ->whereDate('scheduled_start_at', '<=', $weekEnd->toDateString())
            ->with(['attachments'])
            ->withCount('attachments')
            ->get()
            ->keyBy(fn (LiveSession $s): string => $s->live_schedule_assignment_id.'|'.$s->scheduled_start_at?->toDateString())
            ->all();
    }

    /**
     * Translate a slot's session into a recapAction DTO consumed by
     * Schedule.jsx `RecapActionLink` — or null when nothing is worth
     * showing yet (e.g. the session is still upcoming).
     *
     * Three terminal states:
     *   - 'needs_upload'  : status=ended, no attachments  → "PERLU UPLOAD"
     *   - 'pending_recap' : status=scheduled, past start  → "REKAP TERTUNDA"
     *   - 'submitted'     : status=ended, has attachment  → "BUKTI DIHANTAR"
     *
     * `needsUpload` is preserved as a derived boolean so existing client and
     * test code that only inspected that flag keeps working.
     *
     * @return array<string, mixed>|null
     */
    private function buildRecapAction(?LiveSession $session): ?array
    {
        if (! $session) {
            return null;
        }

        $attachmentsCount = (int) ($session->attachments_count ?? 0);

        $state = match (true) {
            $session->status === 'ended' && $attachmentsCount > 0 => 'submitted',
            $session->status === 'ended' && $attachmentsCount === 0 => 'needs_upload',
            $session->status === 'scheduled' && $session->canRecap() => 'pending_recap',
            default => null,
        };

        if (! $state) {
            return null;
        }

        return [
            'sessionId' => (int) $session->id,
            'state' => $state,
            'needsUpload' => $state === 'needs_upload',
            'submitted' => $state === 'submitted',
            'session' => $this->recapSessionDto($session),
            'attachments' => $session->attachments
                ->map(fn (LiveSessionAttachment $a): array => $this->recapAttachmentDto($a))
                ->values()
                ->all(),
        ];
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

    private function resolveWeekStart(?string $value): CarbonImmutable
    {
        if ($value !== null && $value !== '') {
            try {
                return CarbonImmutable::parse($value)->startOfWeek(CarbonImmutable::SUNDAY);
            } catch (\Throwable) {
                // Fall through to default.
            }
        }

        return CarbonImmutable::today()->startOfWeek(CarbonImmutable::SUNDAY);
    }

    private function weekLabel(CarbonImmutable $start, CarbonImmutable $end): string
    {
        $startMonth = self::MONTH_SHORT_MS[$start->month - 1];
        $endMonth = self::MONTH_SHORT_MS[$end->month - 1];

        if ($start->year !== $end->year) {
            return "{$start->day} {$startMonth} {$start->year} – {$end->day} {$endMonth} {$end->year}";
        }

        if ($start->month === $end->month) {
            return "{$start->day} – {$end->day} {$endMonth} {$end->year}";
        }

        return "{$start->day} {$startMonth} – {$end->day} {$endMonth} {$end->year}";
    }

    /**
     * Compact session DTO consumed by the in-page recap modal. Mirrors the
     * shape returned by {@see SessionDetailController::sessionDto} so the
     * modal can render the same form without an extra round-trip.
     *
     * @return array<string, mixed>
     */
    private function recapSessionDto(LiveSession $session): array
    {
        return [
            'id' => $session->id,
            'title' => $session->title,
            'status' => $session->status,
            'remarks' => $session->remarks,
            'scheduledStartAt' => $session->scheduled_start_at?->toIso8601String(),
            'actualStartAt' => $session->actual_start_at?->toIso8601String(),
            'actualEndAt' => $session->actual_end_at?->toIso8601String(),
            'durationMinutes' => $session->duration_minutes,
            'canRecap' => $session->canRecap(),
            'missedReasonCode' => $session->missed_reason_code,
            'missedReasonNote' => $session->missed_reason_note,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function recapAttachmentDto(LiveSessionAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'fileName' => $attachment->file_name,
            'fileType' => $attachment->file_type,
            'fileSize' => (int) $attachment->file_size,
            'fileUrl' => Storage::url($attachment->file_path),
            'attachmentType' => $attachment->attachment_type,
        ];
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
