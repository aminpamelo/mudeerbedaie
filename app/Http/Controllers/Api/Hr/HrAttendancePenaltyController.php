<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendancePenalty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrAttendancePenaltyController extends Controller
{
    /**
     * List penalties with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AttendancePenalty::query()
            ->with(['employee.department', 'attendanceLog']);

        if ($employeeId = $request->get('employee_id')) {
            $query->where('employee_id', $employeeId);
        }

        if ($month = $request->get('month')) {
            $query->where('month', $month);
        }

        if ($year = $request->get('year')) {
            $query->where('year', $year);
        }

        if ($penaltyType = $request->get('penalty_type')) {
            $query->where('penalty_type', $penaltyType);
        }

        $penalties = $query->orderByDesc('created_at')->paginate(15);

        return response()->json($penalties);
    }

    /**
     * Employees with 3+ late arrivals in the current month.
     */
    public function flagged(): JsonResponse
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;

        $flagged = AttendancePenalty::query()
            ->select('employee_id', DB::raw('COUNT(*) as late_count'), DB::raw('SUM(penalty_minutes) as total_late_minutes'))
            ->where('penalty_type', 'late_arrival')
            ->where('month', $currentMonth)
            ->where('year', $currentYear)
            ->groupBy('employee_id')
            ->having('late_count', '>=', 3)
            ->with('employee.department')
            ->get();

        return response()->json(['data' => $flagged]);
    }

    /**
     * Monthly penalty summary grouped by department.
     */
    public function summary(Request $request): JsonResponse
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $summary = AttendancePenalty::query()
            ->join('employees', 'attendance_penalties.employee_id', '=', 'employees.id')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->where('attendance_penalties.month', $month)
            ->where('attendance_penalties.year', $year)
            ->select(
                'departments.id as department_id',
                'departments.name as department_name',
                DB::raw('COUNT(*) as total_penalties'),
                DB::raw("SUM(CASE WHEN attendance_penalties.penalty_type = 'late_arrival' THEN 1 ELSE 0 END) as late_arrivals"),
                DB::raw("SUM(CASE WHEN attendance_penalties.penalty_type = 'early_departure' THEN 1 ELSE 0 END) as early_departures"),
                DB::raw("SUM(CASE WHEN attendance_penalties.penalty_type = 'absent_without_leave' THEN 1 ELSE 0 END) as absences"),
                DB::raw('SUM(attendance_penalties.penalty_minutes) as total_penalty_minutes')
            )
            ->groupBy('departments.id', 'departments.name')
            ->get();

        return response()->json([
            'data' => $summary,
            'month' => $month,
            'year' => $year,
        ]);
    }
}
