<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class HrAttendanceAnalyticsController extends Controller
{
    /**
     * Today's attendance overview and 30-day attendance rate.
     */
    public function overview(): JsonResponse
    {
        $today = Carbon::today();
        $thirtyDaysAgo = $today->copy()->subDays(30);

        $todayStats = AttendanceLog::query()
            ->whereDate('date', $today)
            ->select(
                DB::raw("SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present"),
                DB::raw("SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late"),
                DB::raw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent"),
                DB::raw("SUM(CASE WHEN status = 'wfh' THEN 1 ELSE 0 END) as wfh"),
                DB::raw("SUM(CASE WHEN status = 'on_leave' THEN 1 ELSE 0 END) as on_leave")
            )
            ->first();

        $totalActive = Employee::query()->where('status', 'active')->count();

        $thirtyDayLogs = AttendanceLog::query()
            ->whereDate('date', '>=', $thirtyDaysAgo)
            ->whereDate('date', '<=', $today)
            ->count();

        $thirtyDayPresent = AttendanceLog::query()
            ->whereDate('date', '>=', $thirtyDaysAgo)
            ->whereDate('date', '<=', $today)
            ->whereIn('status', ['present', 'late', 'wfh'])
            ->count();

        $attendanceRate = $thirtyDayLogs > 0
            ? round(($thirtyDayPresent / $thirtyDayLogs) * 100, 1)
            : 0;

        return response()->json([
            'data' => [
                'today' => $todayStats,
                'total_active_employees' => $totalActive,
                'thirty_day_attendance_rate' => $attendanceRate,
            ],
        ]);
    }

    /**
     * Monthly attendance rates for the last 12 months.
     */
    public function trends(Request $request): JsonResponse
    {
        $trends = [];
        $now = Carbon::now();

        for ($i = 11; $i >= 0; $i--) {
            $date = $now->copy()->subMonths($i);
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();

            $query = AttendanceLog::query()
                ->whereDate('date', '>=', $monthStart)
                ->whereDate('date', '<=', $monthEnd);

            if ($departmentId = $request->get('department_id')) {
                $query->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId));
            }

            $total = $query->count();

            $presentCount = (clone $query)->whereIn('status', ['present', 'wfh'])->count();
            $lateCount = (clone $query)->where('status', 'late')->count();

            $attendanceRate = $total > 0
                ? round((($presentCount + $lateCount) / $total) * 100, 1)
                : 0;

            $lateRate = $total > 0
                ? round(($lateCount / $total) * 100, 1)
                : 0;

            $trends[] = [
                'month' => $date->format('M Y'),
                'attendance_rate' => $attendanceRate,
                'late_rate' => $lateRate,
            ];
        }

        return response()->json(['data' => $trends]);
    }

    /**
     * Attendance rate by department.
     */
    public function department(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from')
            ? Carbon::parse($request->get('date_from'))
            : Carbon::today()->subDays(30);

        $dateTo = $request->get('date_to')
            ? Carbon::parse($request->get('date_to'))
            : Carbon::today();

        $query = AttendanceLog::query()
            ->join('employees', 'attendance_logs.employee_id', '=', 'employees.id')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->whereDate('attendance_logs.date', '>=', $dateFrom)
            ->whereDate('attendance_logs.date', '<=', $dateTo);

        if ($departmentId = $request->get('department_id')) {
            $query->where('employees.department_id', $departmentId);
        }

        $departments = $query
            ->select(
                'departments.id as department_id',
                'departments.name as name',
                DB::raw('COUNT(*) as total_logs'),
                DB::raw("SUM(CASE WHEN attendance_logs.status IN ('present', 'late', 'wfh') THEN 1 ELSE 0 END) as present_count")
            )
            ->groupBy('departments.id', 'departments.name')
            ->get()
            ->map(function ($dept) {
                $dept->attendance_rate = $dept->total_logs > 0
                    ? round(($dept->present_count / $dept->total_logs) * 100, 1)
                    : 0;

                return $dept;
            });

        return response()->json(['data' => $departments]);
    }

    /**
     * Employee punctuality: top late + ranking.
     */
    public function punctuality(Request $request): JsonResponse
    {
        $dateFrom = $request->get('date_from')
            ? Carbon::parse($request->get('date_from'))
            : Carbon::today()->subDays(30);

        $dateTo = $request->get('date_to')
            ? Carbon::parse($request->get('date_to'))
            : Carbon::today();

        $query = AttendanceLog::query()
            ->whereDate('date', '>=', $dateFrom)
            ->whereDate('date', '<=', $dateTo);

        if ($departmentId = $request->get('department_id')) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId));
        }

        $rows = $query
            ->select(
                'employee_id',
                DB::raw('COUNT(*) as total_days'),
                DB::raw("SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as on_time_days"),
                DB::raw("SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days"),
                DB::raw("SUM(CASE WHEN status = 'late' THEN COALESCE(late_minutes, 0) ELSE 0 END) as total_late_minutes")
            )
            ->groupBy('employee_id')
            ->get();

        $employeeIds = $rows->pluck('employee_id');
        $employees = Employee::whereIn('id', $employeeIds)
            ->with('department:id,name')
            ->get()
            ->keyBy('id');

        // Top late employees
        $topLate = $rows->filter(fn ($r) => $r->late_days > 0)
            ->sortByDesc('late_days')
            ->take(10)
            ->map(function ($row) use ($employees) {
                $emp = $employees->get($row->employee_id);

                return [
                    'employee_id' => $row->employee_id,
                    'full_name' => $emp?->full_name ?? 'Unknown',
                    'department' => $emp?->department?->name ?? '-',
                    'late_count' => (int) $row->late_days,
                    'total_late_minutes' => (int) $row->total_late_minutes,
                ];
            })
            ->values();

        // Punctuality ranking (most punctual first)
        $ranking = $rows->map(function ($row) use ($employees) {
            $emp = $employees->get($row->employee_id);
            $onTimeRate = $row->total_days > 0
                ? round(($row->on_time_days / $row->total_days) * 100, 1)
                : 0;

            return [
                'employee_id' => $row->employee_id,
                'full_name' => $emp?->full_name ?? 'Unknown',
                'department' => $emp?->department?->name ?? '-',
                'on_time_rate' => $onTimeRate,
                'total_days' => (int) $row->total_days,
                'on_time_days' => (int) $row->on_time_days,
            ];
        })
            ->sortByDesc('on_time_rate')
            ->take(10)
            ->values();

        return response()->json([
            'data' => [
                'top_late' => $topLate,
                'ranking' => $ranking,
            ],
        ]);
    }

    /**
     * Overtime summary by department.
     */
    public function overtime(Request $request): JsonResponse
    {
        $query = OvertimeRequest::query()
            ->join('employees', 'overtime_requests.employee_id', '=', 'employees.id')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->where('overtime_requests.status', 'completed');

        if ($dateFrom = $request->get('date_from')) {
            $query->whereDate('overtime_requests.date', '>=', $dateFrom);
        }

        if ($dateTo = $request->get('date_to')) {
            $query->whereDate('overtime_requests.date', '<=', $dateTo);
        }

        if ($departmentId = $request->get('department_id')) {
            $query->where('employees.department_id', $departmentId);
        }

        $summary = $query
            ->select(
                'departments.id as department_id',
                'departments.name as name',
                DB::raw('COUNT(*) as request_count'),
                DB::raw('SUM(overtime_requests.actual_hours) as total_hours'),
                DB::raw('SUM(overtime_requests.replacement_hours_earned) as total_replacement_earned'),
                DB::raw('SUM(overtime_requests.replacement_hours_used) as total_replacement_used')
            )
            ->groupBy('departments.id', 'departments.name')
            ->get();

        return response()->json(['data' => $summary]);
    }
}
