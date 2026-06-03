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
            label: 'HR',
            accent: 'amber',
            status: $status,
            href: '/hr',
            metrics: [
                ['label' => 'Active staff', 'value' => (string) $headcount, 'hint' => $onLeaveToday > 0 ? $onLeaveToday.' on leave today' : null],
                ['label' => 'Attendance today', 'value' => $attendanceToday['rate'] === null ? '—' : $attendanceToday['rate'].'%', 'tone' => $this->attendanceTone($attendanceToday['rate'])],
                ['label' => 'Late today', 'value' => (string) $attendanceToday['late'], 'tone' => $attendanceToday['late'] > 0 ? 'warning' : 'muted'],
                ['label' => 'Pending approvals', 'value' => (string) ($pendingLeave + $pendingClaims), 'hint' => 'leave + claims'],
            ],
            trend: $this->dailyTrend($period),
            alerts: $this->alerts($pendingLeave, $pendingClaims, $attendanceToday),
            extra: [
                'headcount' => $headcount,
                'onLeaveToday' => $onLeaveToday,
                'attendanceRateToday' => $attendanceToday['rate'] ?? 0,
            ],
        );
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
                'message' => $pendingLeave.' leave '.($pendingLeave === 1 ? 'request' : 'requests').' awaiting approval',
                'href' => '/hr',
            ];
        }

        if ($pendingClaims > 0) {
            $alerts[] = [
                'severity' => 'info',
                'message' => $pendingClaims.' expense '.($pendingClaims === 1 ? 'claim' : 'claims').' awaiting approval',
                'href' => '/hr',
            ];
        }

        if ($attendance['absent'] > 0 && $attendance['rate'] !== null && $attendance['rate'] < 90) {
            $alerts[] = [
                'severity' => 'warning',
                'message' => $attendance['absent'].' staff absent today',
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
