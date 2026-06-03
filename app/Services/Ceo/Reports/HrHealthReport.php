<?php

namespace App\Services\Ceo\Reports;

use App\Models\AttendanceLog;
use App\Models\ClaimRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\Ceo\CeoPeriod;
use App\Services\Ceo\DepartmentHealth;
use Carbon\CarbonImmutable;

/**
 * Operational health of the workforce: who is in today, is attendance healthy,
 * and what HR approvals are backing up (leave, claims). Headcount is the
 * denominator everything else is read against.
 */
class HrHealthReport
{
    private const INACTIVE_STATUSES = ['resigned', 'terminated', 'inactive'];

    private const PRESENT_STATUSES = ['present', 'late', 'wfh'];

    public function run(CeoPeriod $period): DepartmentHealth
    {
        $today = CarbonImmutable::now()->startOfDay();

        $headcount = Employee::query()->whereNotIn('status', self::INACTIVE_STATUSES)->count();

        $attendanceToday = $this->todayAttendance($today);
        $onLeaveToday = $this->onLeaveToday($today);

        $pendingLeave = LeaveRequest::query()->where('status', 'pending')->count();
        $pendingClaims = ClaimRequest::query()->where('status', 'pending')->count();

        $status = $this->status($attendanceToday['rate'], $pendingLeave);

        return new DepartmentHealth(
            key: 'hr',
            label: __('ceo.departments.hr'),
            accent: 'amber',
            status: $status,
            href: '/hr',
            metrics: [
                ['label' => __('ceo.metrics.active_staff'), 'value' => (string) $headcount, 'hint' => $onLeaveToday > 0 ? __('ceo.hints.on_leave_today', ['count' => $onLeaveToday]) : null],
                ['label' => __('ceo.metrics.attendance_today'), 'value' => $attendanceToday['rate'] === null ? '—' : $attendanceToday['rate'].'%', 'tone' => $this->attendanceTone($attendanceToday['rate'])],
                ['label' => __('ceo.metrics.late_today'), 'value' => (string) $attendanceToday['late'], 'tone' => $attendanceToday['late'] > 0 ? 'warning' : 'muted'],
                ['label' => __('ceo.metrics.pending_approvals'), 'value' => (string) ($pendingLeave + $pendingClaims), 'hint' => __('ceo.hints.leave_claims')],
            ],
            trend: $this->dailyTrend($period),
            alerts: $this->alerts($pendingLeave, $pendingClaims, $attendanceToday),
            extra: [
                'headcount' => $headcount,
                'onLeaveToday' => $onLeaveToday,
                'attendanceRateToday' => $attendanceToday['rate'] ?? 0,
            ],
            gauges: [
                ['label' => __('ceo.metrics.attendance'), 'value' => $attendanceToday['rate'] ?? 0, 'target' => 90, 'suffix' => '%', 'tone' => $this->attendanceTone($attendanceToday['rate'])],
            ],
            bars: [
                ['label' => __('ceo.metrics.pending_approvals'), 'value' => min($pendingLeave + $pendingClaims, 10), 'max' => 10, 'valueLabel' => __('ceo.hints.open_count', ['count' => $pendingLeave + $pendingClaims]), 'tone' => ($pendingLeave + $pendingClaims) > 5 ? 'warning' : 'positive'],
            ],
        );
    }

    /**
     * Rich drill-in payload for the dedicated HR detail page.
     *
     * @return array<string, mixed>
     */
    public function detail(CeoPeriod $period): array
    {
        $health = $this->run($period);
        $today = CarbonImmutable::now()->startOfDay();

        $todayStatus = AttendanceLog::query()
            ->whereDate('date', $today)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $pendingLeave = (int) LeaveRequest::query()->where('status', 'pending')->count();
        $pendingClaims = (int) ClaimRequest::query()->where('status', 'pending')->count();
        $onLeaveToday = $this->onLeaveToday($today);
        $lateToday = (int) ($todayStatus['late'] ?? 0);

        $byDepartment = Employee::query()
            ->join('departments', 'departments.id', '=', 'employees.department_id')
            ->whereNotIn('employees.status', self::INACTIVE_STATUSES)
            ->groupBy('employees.department_id', 'departments.name')
            ->selectRaw('departments.name as name, COUNT(*) as headcount')
            ->orderByDesc('headcount')
            ->limit(6)
            ->get();

        $pendingApprovals = LeaveRequest::query()
            ->join('employees', 'employees.id', '=', 'leave_requests.employee_id')
            ->where('leave_requests.status', 'pending')
            ->selectRaw("employees.full_name as name, 'leave' as kind, leave_requests.start_date as on_date")
            ->limit(10)
            ->get()
            ->concat(
                ClaimRequest::query()
                    ->join('employees', 'employees.id', '=', 'claim_requests.employee_id')
                    ->where('claim_requests.status', 'pending')
                    ->selectRaw("employees.full_name as name, 'claim' as kind, claim_requests.claim_date as on_date")
                    ->limit(10)
                    ->get()
            )
            ->take(8);

        return [
            'key' => $health->key,
            'label' => $health->label,
            'accent' => $health->accent,
            'status' => $health->status,
            'moduleHref' => '/hr',
            'moduleLabel' => __('ceo.modules.hr'),
            'gauges' => $health->gauges,
            'alerts' => $health->alerts,
            'kpis' => [
                ['label' => __('ceo.metrics.active_staff'), 'value' => (string) $health->extra['headcount']],
                ['label' => __('ceo.metrics.attendance_today'), 'value' => $health->extra['attendanceRateToday'] > 0 ? $health->extra['attendanceRateToday'].'%' : '—'],
                ['label' => __('ceo.metrics.late_today'), 'value' => (string) $lateToday, 'tone' => $lateToday > 0 ? 'warning' : 'muted'],
                ['label' => __('ceo.metrics.on_leave_today'), 'value' => (string) $onLeaveToday],
                ['label' => __('ceo.metrics.pending_leave'), 'value' => (string) $pendingLeave, 'tone' => $pendingLeave > 5 ? 'warning' : 'muted'],
                ['label' => __('ceo.metrics.pending_claims'), 'value' => (string) $pendingClaims, 'tone' => $pendingClaims > 0 ? 'warning' : 'muted'],
            ],
            'sections' => [
                [
                    'type' => 'chart',
                    'title' => __('ceo.sections.staff_present'),
                    'subtitle' => mb_strtolower($period->label()),
                    'data' => $health->trend,
                ],
                [
                    'type' => 'breakdown',
                    'title' => __('ceo.sections.attendance_today'),
                    'segments' => [
                        ['label' => __('ceo.segments.present'), 'value' => (int) ($todayStatus['present'] ?? 0) + (int) ($todayStatus['wfh'] ?? 0), 'tone' => 'positive'],
                        ['label' => __('ceo.segments.late'), 'value' => (int) ($todayStatus['late'] ?? 0), 'tone' => 'warning'],
                        ['label' => __('ceo.segments.absent'), 'value' => (int) ($todayStatus['absent'] ?? 0), 'tone' => 'negative'],
                        ['label' => __('ceo.segments.on_leave'), 'value' => (int) ($todayStatus['on_leave'] ?? 0), 'tone' => 'info'],
                    ],
                ],
                [
                    'type' => 'list',
                    'title' => __('ceo.sections.headcount_by_department'),
                    'subtitle' => __('ceo.subtitles.active_staff'),
                    'columns' => [
                        ['key' => 'name', 'label' => __('ceo.columns.department')],
                        ['key' => 'headcount', 'label' => __('ceo.columns.staff'), 'align' => 'right'],
                    ],
                    'rows' => $byDepartment->map(fn ($r) => [
                        'name' => (string) $r->name,
                        'headcount' => (int) $r->headcount,
                    ])->all(),
                ],
                [
                    'type' => 'list',
                    'title' => __('ceo.sections.pending_approvals'),
                    'subtitle' => __('ceo.subtitles.leave_claims'),
                    'columns' => [
                        ['key' => 'name', 'label' => __('ceo.columns.employee')],
                        ['key' => 'kind', 'label' => __('ceo.columns.type')],
                        ['key' => 'on_date', 'label' => __('ceo.columns.date'), 'align' => 'right'],
                    ],
                    'rows' => $pendingApprovals->map(fn ($r) => [
                        'name' => (string) $r->name,
                        'kind' => __('ceo.kinds.'.$r->kind),
                        'on_date' => $r->on_date ? substr((string) $r->on_date, 0, 10) : '—',
                    ])->values()->all(),
                ],
            ],
        ];
    }

    /**
     * @return array{rate: int|null, late: int, absent: int}
     */
    private function todayAttendance(CarbonImmutable $today): array
    {
        $counts = AttendanceLog::query()
            ->whereDate('date', $today)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $total = (int) $counts->sum();

        if ($total === 0) {
            return ['rate' => null, 'late' => 0, 'absent' => 0];
        }

        $present = collect(self::PRESENT_STATUSES)->sum(fn ($s) => (int) ($counts[$s] ?? 0));

        return [
            'rate' => (int) round($present / $total * 100),
            'late' => (int) ($counts['late'] ?? 0),
            'absent' => (int) ($counts['absent'] ?? 0),
        ];
    }

    private function onLeaveToday(CarbonImmutable $today): int
    {
        return LeaveRequest::query()
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->distinct('employee_id')
            ->count('employee_id');
    }

    private function status(?int $attendanceRate, int $pendingLeave): string
    {
        if ($attendanceRate !== null && $attendanceRate < 70) {
            return DepartmentHealth::RED;
        }

        if (($attendanceRate !== null && $attendanceRate < 90) || $pendingLeave > 5) {
            return DepartmentHealth::AMBER;
        }

        return DepartmentHealth::GREEN;
    }

    private function attendanceTone(?int $rate): string
    {
        return match (true) {
            $rate === null => 'muted',
            $rate >= 90 => 'positive',
            $rate >= 70 => 'warning',
            default => 'negative',
        };
    }

    /**
     * @param  array{rate: int|null, late: int, absent: int}  $attendance
     * @return array<int, array{severity: string, message: string, href?: string}>
     */
    private function alerts(int $pendingLeave, int $pendingClaims, array $attendance): array
    {
        $alerts = [];

        if ($pendingLeave > 0) {
            $alerts[] = [
                'severity' => $pendingLeave > 5 ? 'warning' : 'info',
                'message' => trans_choice('ceo.alerts.leave_pending', $pendingLeave, ['count' => $pendingLeave]),
                'href' => '/hr',
            ];
        }

        if ($pendingClaims > 0) {
            $alerts[] = [
                'severity' => 'info',
                'message' => trans_choice('ceo.alerts.claims_pending', $pendingClaims, ['count' => $pendingClaims]),
                'href' => '/hr',
            ];
        }

        if ($attendance['absent'] > 0 && $attendance['rate'] !== null && $attendance['rate'] < 90) {
            $alerts[] = [
                'severity' => 'warning',
                'message' => trans_choice('ceo.alerts.staff_absent', $attendance['absent'], ['count' => $attendance['absent']]),
                'href' => '/hr',
            ];
        }

        return $alerts;
    }

    /**
     * @return array<int, int>
     */
    private function dailyTrend(CeoPeriod $period): array
    {
        $rows = AttendanceLog::query()
            ->whereBetween('date', [$period->from->toDateString(), $period->to->toDateString()])
            ->whereIn('status', self::PRESENT_STATUSES)
            ->selectRaw('DATE(date) as day, COUNT(*) as c')
            ->groupBy('day')
            ->pluck('c', 'day');

        return TrendFiller::daily($period, $rows);
    }
}
