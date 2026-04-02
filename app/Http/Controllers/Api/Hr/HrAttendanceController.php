<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrAttendanceController extends Controller
{
    /**
     * Paginated list of attendance logs with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AttendanceLog::query()
            ->with(['employee.department']);

        if ($dateFrom = $request->get('date_from')) {
            $query->whereDate('date', '>=', $dateFrom);
        }

        if ($dateTo = $request->get('date_to')) {
            $query->whereDate('date', '<=', $dateTo);
        }

        if ($departmentId = $request->get('department_id')) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId));
        }

        if ($employeeId = $request->get('employee_id')) {
            $query->where('employee_id', $employeeId);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $logs = $query->orderByDesc('date')->paginate(15);

        return response()->json($logs);
    }

    /**
     * Get today's attendance for all active employees.
     */
    public function today(): JsonResponse
    {
        $today = Carbon::today();

        $logs = AttendanceLog::query()
            ->with(['employee.department'])
            ->whereDate('date', $today)
            ->get();

        $activeEmployees = Employee::query()
            ->where('status', 'active')
            ->count();

        return response()->json([
            'data' => $logs,
            'total_active_employees' => $activeEmployees,
            'date' => $today->toDateString(),
        ]);
    }

    /**
     * Show a single attendance log with relationships.
     */
    public function show(AttendanceLog $attendanceLog): JsonResponse
    {
        $attendanceLog->load(['employee.department', 'penalties']);

        return response()->json(['data' => $attendanceLog]);
    }

    /**
     * Admin manual adjustment of an attendance log.
     */
    public function update(Request $request, AttendanceLog $attendanceLog): JsonResponse
    {
        $validated = $request->validate([
            'clock_in' => ['nullable', 'date'],
            'clock_out' => ['nullable', 'date'],
            'status' => ['nullable', 'in:present,absent,late,half_day,on_leave,holiday,wfh'],
            'remarks' => ['nullable', 'string'],
        ]);

        if (isset($validated['clock_in']) && isset($validated['clock_out'])) {
            $clockIn = Carbon::parse($validated['clock_in']);
            $clockOut = Carbon::parse($validated['clock_out']);
            $validated['total_work_minutes'] = (int) $clockIn->diffInMinutes($clockOut);
        }

        $attendanceLog->update($validated);

        return response()->json([
            'data' => $attendanceLog->fresh(['employee.department']),
            'message' => 'Attendance log updated successfully.',
        ]);
    }

    /**
     * Monthly attendance view grouped by employee with daily records.
     */
    public function monthly(Request $request): JsonResponse
    {
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);
        $departmentId = $request->get('department_id');
        $search = $request->get('search');

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        $daysInMonth = $startDate->daysInMonth;

        $employeeQuery = Employee::query()
            ->whereNotIn('status', ['terminated', 'resigned'])
            ->with('department');

        if ($departmentId) {
            $employeeQuery->where('department_id', $departmentId);
        }

        if ($search) {
            $employeeQuery->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        $employees = $employeeQuery->orderBy('full_name')->get();
        $employeeIds = $employees->pluck('id');

        $logs = AttendanceLog::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($employeeLogs) => $employeeLogs->keyBy(fn ($log) => Carbon::parse($log->date)->day));

        $data = $employees->map(function (Employee $employee) use ($logs, $daysInMonth) {
            $employeeLogs = $logs->get($employee->id, collect());
            $days = [];

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $log = $employeeLogs->get($day);
                $days[$day] = $log ? [
                    'id' => $log->id,
                    'status' => $log->status,
                    'clock_in' => $log->clock_in,
                    'clock_out' => $log->clock_out,
                    'total_work_minutes' => $log->total_work_minutes,
                    'late_minutes' => $log->late_minutes,
                    'early_leave_minutes' => $log->early_leave_minutes,
                    'is_overtime' => $log->is_overtime,
                    'remarks' => $log->remarks,
                    'clock_in_photo_url' => $log->clock_in_photo_url,
                    'clock_out_photo_url' => $log->clock_out_photo_url,
                ] : null;
            }

            $presentCount = collect($days)->filter(fn ($d) => $d && in_array($d['status'], ['present', 'late', 'wfh', 'half_day']))->count();
            $absentCount = collect($days)->filter(fn ($d) => $d && $d['status'] === 'absent')->count();
            $lateCount = collect($days)->filter(fn ($d) => $d && $d['status'] === 'late')->count();
            $leaveCount = collect($days)->filter(fn ($d) => $d && $d['status'] === 'on_leave')->count();

            return [
                'id' => $employee->id,
                'employee_id' => $employee->employee_id,
                'full_name' => $employee->full_name,
                'department' => $employee->department?->name,
                'days' => $days,
                'summary' => [
                    'present' => $presentCount,
                    'absent' => $absentCount,
                    'late' => $lateCount,
                    'leave' => $leaveCount,
                ],
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'year' => $year,
                'month' => $month,
                'days_in_month' => $daysInMonth,
                'start_of_month' => $startDate->toDateString(),
            ],
        ]);
    }

    /**
     * Export attendance logs as CSV filtered by date range.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = AttendanceLog::query()
            ->with(['employee.department']);

        if ($dateFrom = $request->get('date_from')) {
            $query->whereDate('date', '>=', $dateFrom);
        }

        if ($dateTo = $request->get('date_to')) {
            $query->whereDate('date', '<=', $dateTo);
        }

        $logs = $query->orderByDesc('date')->get();

        return response()->streamDownload(function () use ($logs) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Employee ID', 'Employee Name', 'Department', 'Date',
                'Clock In', 'Clock Out', 'Status', 'Late Minutes',
                'Early Leave Minutes', 'Total Work Minutes', 'Remarks',
            ]);

            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->employee?->employee_id,
                    $log->employee?->full_name,
                    $log->employee?->department?->name,
                    $log->date?->format('Y-m-d'),
                    $log->clock_in,
                    $log->clock_out,
                    $log->status,
                    $log->late_minutes,
                    $log->early_leave_minutes,
                    $log->total_work_minutes,
                    $log->remarks,
                ]);
            }

            fclose($handle);
        }, 'attendance-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
