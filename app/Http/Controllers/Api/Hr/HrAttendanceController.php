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
