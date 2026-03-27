<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use Illuminate\Http\JsonResponse;
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
     * Monthly attendance counts for the last 12 months.
     */
    public function trends(): JsonResponse
    {
        $trends = [];
        $now = Carbon::now();

        for ($i = 11; $i >= 0; $i--) {
            $date = $now->copy()->subMonths($i);
            $monthStart = $date->copy()->startOfMonth();
            $monthEnd = $date->copy()->endOfMonth();

            $counts = AttendanceLog::query()
                ->whereDate('date', '>=', $monthStart)
                ->whereDate('date', '<=', $monthEnd)
                ->select(
                    DB::raw("SUM(CASE WHEN status IN ('present', 'wfh') THEN 1 ELSE 0 END) as present"),
                    DB::raw("SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late"),
                    DB::raw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent"),
                    DB::raw("SUM(CASE WHEN status = 'on_leave' THEN 1 ELSE 0 END) as on_leave")
                )
                ->first();

            $trends[] = [
                'month' => $date->format('Y-m'),
                'label' => $date->format('M Y'),
                'present' => (int) ($counts->present ?? 0),
                'late' => (int) ($counts->late ?? 0),
                'absent' => (int) ($counts->absent ?? 0),
                'on_leave' => (int) ($counts->on_leave ?? 0),
            ];
        }

        return response()->json(['data' => $trends]);
    }

    /**
     * Attendance rate by department.
     */
    public function department(): JsonResponse
    {
        $thirtyDaysAgo = Carbon::today()->subDays(30);

        $departments = AttendanceLog::query()
            ->join('employees', 'attendance_logs.employee_id', '=', 'employees.id')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->whereDate('attendance_logs.date', '>=', $thirtyDaysAgo)
            ->select(
                'departments.id as department_id',
                'departments.name as department_name',
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
     * Employee ranking by on-time percentage.
     */
    public function punctuality(): JsonResponse
    {
        $thirtyDaysAgo = Carbon::today()->subDays(30);

        $ranking = AttendanceLog::query()
            ->whereDate('date', '>=', $thirtyDaysAgo)
            ->select(
                'employee_id',
                DB::raw('COUNT(*) as total_days'),
                DB::raw("SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as on_time_days"),
                DB::raw("SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days")
            )
            ->groupBy('employee_id')
            ->with('employee.department')
            ->get()
            ->map(function ($row) {
                $row->on_time_percentage = $row->total_days > 0
                    ? round(($row->on_time_days / $row->total_days) * 100, 1)
                    : 0;

                return $row;
            })
            ->sortByDesc('on_time_percentage')
            ->values();

        return response()->json(['data' => $ranking]);
    }

    /**
     * Overtime summary by department with replacement hours.
     */
    public function overtime(): JsonResponse
    {
        $summary = OvertimeRequest::query()
            ->join('employees', 'overtime_requests.employee_id', '=', 'employees.id')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->where('overtime_requests.status', 'completed')
            ->select(
                'departments.id as department_id',
                'departments.name as department_name',
                DB::raw('COUNT(*) as total_ot_requests'),
                DB::raw('SUM(overtime_requests.actual_hours) as total_actual_hours'),
                DB::raw('SUM(overtime_requests.replacement_hours_earned) as total_replacement_earned'),
                DB::raw('SUM(overtime_requests.replacement_hours_used) as total_replacement_used')
            )
            ->groupBy('departments.id', 'departments.name')
            ->get();

        return response()->json(['data' => $summary]);
    }
}
