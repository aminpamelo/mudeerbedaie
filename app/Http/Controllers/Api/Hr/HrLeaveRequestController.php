<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\AttendancePenalty;
use App\Models\EmployeeSchedule;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrLeaveRequestController extends Controller
{
    /**
     * List all leave requests with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = LeaveRequest::query()
            ->with(['employee.department', 'leaveType']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($leaveTypeId = $request->get('leave_type_id')) {
            $query->where('leave_type_id', $leaveTypeId);
        }

        if ($departmentId = $request->get('department_id')) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId));
        }

        if ($employeeId = $request->get('employee_id')) {
            $query->where('employee_id', $employeeId);
        }

        if ($dateFrom = $request->get('date_from')) {
            $query->whereDate('start_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->get('date_to')) {
            $query->whereDate('end_date', '<=', $dateTo);
        }

        $requests = $query->orderByDesc('created_at')->paginate(15);

        return response()->json($requests);
    }

    /**
     * Show a single leave request with relationships.
     */
    public function show(LeaveRequest $leaveRequest): JsonResponse
    {
        $leaveRequest->load(['employee.department', 'leaveType', 'approver']);

        return response()->json(['data' => $leaveRequest]);
    }

    /**
     * Approve a leave request.
     */
    public function approve(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        if ($leaveRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be approved.'], 422);
        }

        return DB::transaction(function () use ($request, $leaveRequest) {
            $leaveRequest->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            $balance = LeaveBalance::query()
                ->where('employee_id', $leaveRequest->employee_id)
                ->where('leave_type_id', $leaveRequest->leave_type_id)
                ->where('year', Carbon::parse($leaveRequest->start_date)->year)
                ->first();

            if ($balance) {
                $balance->update([
                    'used_days' => $balance->used_days + $leaveRequest->total_days,
                    'pending_days' => $balance->pending_days - $leaveRequest->total_days,
                ]);
            }

            if ($leaveRequest->is_replacement_leave && $leaveRequest->replacement_hours_deducted) {
                $otRequests = OvertimeRequest::query()
                    ->where('employee_id', $leaveRequest->employee_id)
                    ->where('status', 'completed')
                    ->whereColumn(
                        DB::raw('replacement_hours_earned - replacement_hours_used'),
                        '>',
                        DB::raw('0')
                    )
                    ->orderBy('created_at')
                    ->get();

                $hoursToDeduct = $leaveRequest->replacement_hours_deducted;

                foreach ($otRequests as $otRequest) {
                    if ($hoursToDeduct <= 0) {
                        break;
                    }
                    $available = $otRequest->replacement_hours_earned - $otRequest->replacement_hours_used;
                    $deduct = min($available, $hoursToDeduct);
                    $otRequest->increment('replacement_hours_used', $deduct);
                    $hoursToDeduct -= $deduct;
                }
            }

            $schedule = EmployeeSchedule::query()
                ->where('employee_id', $leaveRequest->employee_id)
                ->active()
                ->with('workSchedule')
                ->first();

            $workingDays = $schedule && $schedule->workSchedule
                ? ($schedule->workSchedule->working_days ?? [1, 2, 3, 4, 5])
                : [1, 2, 3, 4, 5];

            $startDate = Carbon::parse($leaveRequest->start_date);
            $endDate = Carbon::parse($leaveRequest->end_date);
            $current = $startDate->copy();

            while ($current->lte($endDate)) {
                $dayOfWeek = $current->dayOfWeekIso;

                if (in_array($dayOfWeek, $workingDays)) {
                    $isHoliday = Holiday::query()
                        ->whereDate('date', $current)
                        ->exists();

                    if (! $isHoliday) {
                        $existingLog = AttendanceLog::query()
                            ->where('employee_id', $leaveRequest->employee_id)
                            ->whereDate('date', $current->toDateString())
                            ->first();

                        if (! $existingLog) {
                            AttendanceLog::create([
                                'employee_id' => $leaveRequest->employee_id,
                                'date' => $current->toDateString(),
                                'status' => 'on_leave',
                                'remarks' => 'Leave: '.$leaveRequest->leaveType?->name,
                            ]);
                        } elseif ($existingLog->status === 'absent') {
                            $existingLog->update([
                                'status' => 'on_leave',
                                'remarks' => 'Leave: '.$leaveRequest->leaveType?->name,
                            ]);

                            AttendancePenalty::query()
                                ->where('attendance_log_id', $existingLog->id)
                                ->where('penalty_type', 'absent_without_leave')
                                ->delete();
                        }
                    }
                }

                $current->addDay();
            }

            // Notify the employee that their leave was approved
            $leaveRequest->load('employee.user', 'leaveType');
            if ($leaveRequest->employee->user) {
                $leaveRequest->employee->user->notify(
                    new \App\Notifications\Hr\LeaveRequestApproved($leaveRequest, $request->user())
                );
            }

            return response()->json([
                'data' => $leaveRequest->fresh(['employee', 'leaveType']),
                'message' => 'Leave request approved successfully.',
            ]);
        });
    }

    /**
     * Reject a leave request.
     */
    public function reject(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        if ($leaveRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be rejected.'], 422);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5'],
        ]);

        return DB::transaction(function () use ($request, $leaveRequest, $validated) {
            $leaveRequest->update([
                'status' => 'rejected',
                'rejection_reason' => $validated['rejection_reason'],
            ]);

            $balance = LeaveBalance::query()
                ->where('employee_id', $leaveRequest->employee_id)
                ->where('leave_type_id', $leaveRequest->leave_type_id)
                ->where('year', Carbon::parse($leaveRequest->start_date)->year)
                ->first();

            if ($balance) {
                $balance->update([
                    'pending_days' => $balance->pending_days - $leaveRequest->total_days,
                    'available_days' => $balance->available_days + $leaveRequest->total_days,
                ]);
            }

            // Notify the employee that their leave was rejected
            $leaveRequest->load('employee.user', 'leaveType');
            if ($leaveRequest->employee->user) {
                $leaveRequest->employee->user->notify(
                    new \App\Notifications\Hr\LeaveRequestRejected($leaveRequest, $request->user())
                );
            }

            return response()->json([
                'data' => $leaveRequest->fresh(['employee', 'leaveType']),
                'message' => 'Leave request rejected.',
            ]);
        });
    }

    /**
     * Export leave requests as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = LeaveRequest::query()
            ->with(['employee.department', 'leaveType']);

        if ($dateFrom = $request->get('date_from')) {
            $query->whereDate('start_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->get('date_to')) {
            $query->whereDate('end_date', '<=', $dateTo);
        }

        $requests = $query->orderByDesc('created_at')->get();

        return response()->streamDownload(function () use ($requests) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Employee ID', 'Employee Name', 'Department', 'Leave Type',
                'Start Date', 'End Date', 'Total Days', 'Status', 'Reason',
            ]);

            foreach ($requests as $leaveRequest) {
                fputcsv($handle, [
                    $leaveRequest->employee?->employee_id,
                    $leaveRequest->employee?->full_name,
                    $leaveRequest->employee?->department?->name,
                    $leaveRequest->leaveType?->name,
                    $leaveRequest->start_date,
                    $leaveRequest->end_date,
                    $leaveRequest->total_days,
                    $leaveRequest->status,
                    $leaveRequest->reason,
                ]);
            }

            fclose($handle);
        }, 'leave-requests-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
