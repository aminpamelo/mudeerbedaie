<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class HrLeaveCalendarController extends Controller
{
    /**
     * Return approved leave requests for a given month/year, formatted for calendar display.
     */
    public function index(Request $request): JsonResponse
    {
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
        $endOfMonth = Carbon::create($year, $month, 1)->endOfMonth();

        $query = LeaveRequest::query()
            ->with(['employee.department', 'leaveType'])
            ->where('status', 'approved')
            ->where(function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('start_date', [$startOfMonth, $endOfMonth])
                    ->orWhereBetween('end_date', [$startOfMonth, $endOfMonth])
                    ->orWhere(function ($q2) use ($startOfMonth, $endOfMonth) {
                        $q2->where('start_date', '<=', $startOfMonth)
                            ->where('end_date', '>=', $endOfMonth);
                    });
            });

        if ($departmentId = $request->get('department_id')) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId));
        }

        $leaves = $query->get()->map(fn ($leave) => [
            'id' => $leave->id,
            'employee_name' => $leave->employee?->full_name,
            'department' => $leave->employee?->department?->name,
            'leave_type' => $leave->leaveType?->name,
            'color' => $leave->leaveType?->color,
            'start_date' => $leave->start_date,
            'end_date' => $leave->end_date,
            'total_days' => $leave->total_days,
            'is_half_day' => $leave->is_half_day,
            'half_day_period' => $leave->half_day_period,
        ]);

        return response()->json(['data' => $leaves]);
    }

    /**
     * Count overlapping approved leaves for a date range and department.
     */
    public function overlaps(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'department_id' => ['nullable', 'exists:departments,id'],
        ]);

        $query = LeaveRequest::query()
            ->where('status', 'approved')
            ->where(function ($q) use ($validated) {
                $q->whereBetween('start_date', [$validated['start_date'], $validated['end_date']])
                    ->orWhereBetween('end_date', [$validated['start_date'], $validated['end_date']])
                    ->orWhere(function ($q2) use ($validated) {
                        $q2->where('start_date', '<=', $validated['start_date'])
                            ->where('end_date', '>=', $validated['end_date']);
                    });
            });

        if (! empty($validated['department_id'])) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $validated['department_id']));
        }

        $count = $query->count();

        return response()->json([
            'data' => ['overlap_count' => $count],
        ]);
    }
}
