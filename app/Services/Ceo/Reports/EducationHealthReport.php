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
            label: __('ceo.departments.education'),
            accent: 'sky',
            status: $status,
            href: '/admin/classes',
            metrics: [
                ['label' => __('ceo.metrics.sessions_today'), 'value' => $completedToday.' / '.$scheduledToday, 'hint' => __('ceo.hints.completed_scheduled')],
                ['label' => __('ceo.metrics.attendance'), 'value' => $attendance === null ? '—' : $attendance.'%', 'hint' => mb_strtolower($period->label()), 'tone' => $this->attendanceTone($attendance)],
                ['label' => __('ceo.metrics.no_shows'), 'value' => (string) $noShows, 'tone' => $noShows > 0 ? 'warning' : 'muted'],
                ['label' => __('ceo.metrics.new_enrollments'), 'value' => (string) $newEnrollments, 'hint' => mb_strtolower($period->label())],
            ],
            trend: $this->dailyTrend($period),
            alerts: $this->alerts($noShows, $cancelled),
            extra: [
                'sessionsCompletedToday' => $completedToday,
                'sessionsScheduledToday' => $scheduledToday,
                'attendanceRate' => $attendance ?? 0,
            ],
            gauges: [
                ['label' => __('ceo.metrics.attendance'), 'value' => $attendance ?? 0, 'target' => 80, 'suffix' => '%', 'tone' => $this->attendanceTone($attendance)],
            ],
            bars: [
                ['label' => __('ceo.metrics.sessions_today'), 'value' => $completedToday, 'max' => max($scheduledToday, 1), 'valueLabel' => $completedToday.' / '.$scheduledToday, 'tone' => 'info'],
            ],
        );
    }

    /**
     * Rich drill-in payload for the dedicated Education detail page.
     *
     * @return array<string, mixed>
     */
    public function detail(CeoPeriod $period): array
    {
        $health = $this->run($period);
        $from = $period->from->toDateString();
        $to = $period->to->toDateString();

        $statusCounts = ClassSession::query()
            ->whereBetween('session_date', [$from, $to])
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $attendanceSplit = DB::table('class_attendance')
            ->join('class_sessions', 'class_sessions.id', '=', 'class_attendance.session_id')
            ->whereBetween('class_sessions.session_date', [$from, $to])
            ->selectRaw('class_attendance.status, COUNT(*) as c')
            ->groupBy('class_attendance.status')
            ->pluck('c', 'status');

        $newEnrollments = (int) Enrollment::query()->whereBetween('enrollment_date', [$from, $to])->count();
        $activeEnrollments = (int) Enrollment::query()->where('status', 'enrolled')->count();

        $topCourses = Enrollment::query()
            ->join('courses', 'courses.id', '=', 'enrollments.course_id')
            ->whereBetween('enrollments.enrollment_date', [$from, $to])
            ->groupBy('enrollments.course_id', 'courses.name')
            ->selectRaw('courses.name as name, COUNT(*) as enrolments')
            ->orderByDesc('enrolments')
            ->limit(6)
            ->get();

        return [
            'key' => $health->key,
            'label' => $health->label,
            'accent' => $health->accent,
            'status' => $health->status,
            'moduleHref' => '/admin/classes',
            'moduleLabel' => __('ceo.modules.education'),
            'gauges' => $health->gauges,
            'alerts' => $health->alerts,
            'kpis' => [
                ['label' => __('ceo.metrics.sessions_today'), 'value' => $health->extra['sessionsCompletedToday'].' / '.$health->extra['sessionsScheduledToday'], 'hint' => __('ceo.hints.completed_scheduled')],
                ['label' => __('ceo.metrics.attendance'), 'value' => $health->extra['attendanceRate'] > 0 ? $health->extra['attendanceRate'].'%' : '—', 'hint' => mb_strtolower($period->label())],
                ['label' => __('ceo.metrics.no_shows'), 'value' => (string) ((int) ($statusCounts['no_show'] ?? 0)), 'tone' => ($statusCounts['no_show'] ?? 0) > 0 ? 'warning' : 'muted'],
                ['label' => __('ceo.metrics.cancelled'), 'value' => (string) ((int) ($statusCounts['cancelled'] ?? 0))],
                ['label' => __('ceo.metrics.new_enrollments'), 'value' => (string) $newEnrollments, 'hint' => mb_strtolower($period->label())],
                ['label' => __('ceo.metrics.active_enrollments'), 'value' => (string) $activeEnrollments],
            ],
            'sections' => [
                [
                    'type' => 'chart',
                    'title' => __('ceo.sections.completed_sessions'),
                    'subtitle' => mb_strtolower($period->label()),
                    'data' => $health->trend,
                ],
                [
                    'type' => 'breakdown',
                    'title' => __('ceo.sections.sessions_by_status'),
                    'segments' => [
                        ['label' => __('ceo.segments.completed'), 'value' => (int) ($statusCounts['completed'] ?? 0), 'tone' => 'positive'],
                        ['label' => __('ceo.segments.scheduled'), 'value' => (int) ($statusCounts['scheduled'] ?? 0), 'tone' => 'info'],
                        ['label' => __('ceo.segments.no_show'), 'value' => (int) ($statusCounts['no_show'] ?? 0), 'tone' => 'warning'],
                        ['label' => __('ceo.segments.cancelled'), 'value' => (int) ($statusCounts['cancelled'] ?? 0), 'tone' => 'negative'],
                    ],
                ],
                [
                    'type' => 'breakdown',
                    'title' => __('ceo.sections.attendance_split'),
                    'segments' => [
                        ['label' => __('ceo.segments.present'), 'value' => (int) ($attendanceSplit['present'] ?? 0), 'tone' => 'positive'],
                        ['label' => __('ceo.segments.late'), 'value' => (int) ($attendanceSplit['late'] ?? 0), 'tone' => 'warning'],
                        ['label' => __('ceo.segments.absent'), 'value' => (int) ($attendanceSplit['absent'] ?? 0), 'tone' => 'negative'],
                    ],
                ],
                [
                    'type' => 'list',
                    'title' => __('ceo.sections.top_courses'),
                    'subtitle' => __('ceo.subtitles.by_new_enrollments'),
                    'columns' => [
                        ['key' => 'name', 'label' => __('ceo.columns.course')],
                        ['key' => 'enrolments', 'label' => __('ceo.columns.enrolments'), 'align' => 'right'],
                    ],
                    'rows' => $topCourses->map(fn ($r) => [
                        'name' => (string) $r->name,
                        'enrolments' => (int) $r->enrolments,
                    ])->all(),
                ],
            ],
        ];
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
                'message' => trans_choice('ceo.alerts.no_shows', $noShows, ['count' => $noShows]),
                'href' => '/admin/classes',
            ];
        }

        if ($cancelled > 3) {
            $alerts[] = [
                'severity' => 'info',
                'message' => trans_choice('ceo.alerts.classes_cancelled', $cancelled, ['count' => $cancelled]),
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
