<?php

namespace App\Services\Ceo\Reports;

use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Services\Ceo\CeoPeriod;
use App\Services\Ceo\DepartmentHealth;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Operational health of the teaching operation: are scheduled classes actually
 * running, are students showing up, and how much is being lost to no-shows and
 * cancellations. New-enrollment volume is secondary context.
 */
class EducationHealthReport
{
    public function run(CeoPeriod $period): DepartmentHealth
    {
        $today = CarbonImmutable::now()->startOfDay();

        $scheduledToday = ClassSession::query()->whereDate('session_date', $today)->count();
        $completedToday = ClassSession::query()
            ->whereDate('session_date', $today)
            ->where('status', 'completed')
            ->count();

        $attendance = $this->attendanceRate($period);
        $noShows = ClassSession::query()
            ->whereBetween('session_date', [$period->from->toDateString(), $period->to->toDateString()])
            ->where('status', 'no_show')
            ->count();
        $cancelled = ClassSession::query()
            ->whereBetween('session_date', [$period->from->toDateString(), $period->to->toDateString()])
            ->where('status', 'cancelled')
            ->count();

        $newEnrollments = Enrollment::query()
            ->whereBetween('enrollment_date', [$period->from->toDateString(), $period->to->toDateString()])
            ->count();

        $status = $this->status($attendance, $noShows, $cancelled);

        return new DepartmentHealth(
            key: 'education',
            label: 'Education',
            accent: 'sky',
            status: $status,
            href: '/admin/classes',
            metrics: [
                ['label' => 'Sessions today', 'value' => $completedToday.' / '.$scheduledToday, 'hint' => 'completed / scheduled'],
                ['label' => 'Attendance', 'value' => $attendance === null ? '—' : $attendance.'%', 'hint' => strtolower($period->label()), 'tone' => $this->attendanceTone($attendance)],
                ['label' => 'No-shows', 'value' => (string) $noShows, 'tone' => $noShows > 0 ? 'warning' : 'muted'],
                ['label' => 'New enrollments', 'value' => (string) $newEnrollments, 'hint' => strtolower($period->label())],
            ],
            trend: $this->dailyTrend($period),
            alerts: $this->alerts($noShows, $cancelled),
            extra: [
                'sessionsCompletedToday' => $completedToday,
                'sessionsScheduledToday' => $scheduledToday,
                'attendanceRate' => $attendance ?? 0,
            ],
        );
    }

    /**
     * Share of attended (present or late) student slots across the period.
     * Returns null when there were no attendance records to rate.
     */
    private function attendanceRate(CeoPeriod $period): ?int
    {
        $row = DB::table('class_attendance')
            ->join('class_sessions', 'class_sessions.id', '=', 'class_attendance.session_id')
            ->whereBetween('class_sessions.session_date', [$period->from->toDateString(), $period->to->toDateString()])
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN class_attendance.status IN ('present', 'late') THEN 1 ELSE 0 END) as attended
            ")
            ->first();

        $total = (int) ($row->total ?? 0);

        if ($total === 0) {
            return null;
        }

        return (int) round((int) $row->attended / $total * 100);
    }

    private function status(?int $attendance, int $noShows, int $cancelled): string
    {
        if ($attendance !== null && $attendance < 60) {
            return DepartmentHealth::RED;
        }

        if (($attendance !== null && $attendance < 80) || $noShows > 0 || $cancelled > 3) {
            return DepartmentHealth::AMBER;
        }

        return DepartmentHealth::GREEN;
    }

    private function attendanceTone(?int $attendance): string
    {
        return match (true) {
            $attendance === null => 'muted',
            $attendance >= 80 => 'positive',
            $attendance >= 60 => 'warning',
            default => 'negative',
        };
    }

    /**
     * @return array<int, array{severity: string, message: string, href?: string}>
     */
    private function alerts(int $noShows, int $cancelled): array
    {
        $alerts = [];

        if ($noShows > 0) {
            $alerts[] = [
                'severity' => 'warning',
                'message' => $noShows.' class '.($noShows === 1 ? 'session' : 'sessions').' marked no-show',
                'href' => '/admin/classes',
            ];
        }

        if ($cancelled > 3) {
            $alerts[] = [
                'severity' => 'info',
                'message' => $cancelled.' classes cancelled this period',
                'href' => '/admin/classes',
            ];
        }

        return $alerts;
    }

    /**
     * @return array<int, int>
     */
    private function dailyTrend(CeoPeriod $period): array
    {
        $rows = ClassSession::query()
            ->whereBetween('session_date', [$period->from->toDateString(), $period->to->toDateString()])
            ->where('status', 'completed')
            ->selectRaw('DATE(session_date) as day, COUNT(*) as c')
            ->groupBy('day')
            ->pluck('c', 'day');

        return TrendFiller::daily($period, $rows);
    }
}
