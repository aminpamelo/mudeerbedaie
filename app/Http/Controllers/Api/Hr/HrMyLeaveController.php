<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\ApplyLeaveRequest;
use App\Models\AttendanceLog;
use App\Models\EmployeeSchedule;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class HrMyLeaveController extends Controller
{
    /**
     * My leave balances for the current year.
     */
    public function balances(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $year = $request->get('year', now()->year);

        $balances = LeaveBalance::query()
            ->with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->get();

        return response()->json(['data' => $balances]);
    }

    /**
     * My leave requests.
     */
    public function requests(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $leaveRequests = LeaveRequest::query()
            ->with('leaveType')
            ->where('employee_id', $employee->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json($leaveRequests);
    }

    /**
     * Apply for leave.
     */
    public function apply(ApplyLeaveRequest $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $validated = $request->validated();

        return DB::transaction(function () use ($request, $employee, $validated) {
            $leaveType = LeaveType::findOrFail($validated['leave_type_id']);

            if ($leaveType->gender_restriction && $leaveType->gender_restriction !== $employee->gender) {
                return response()->json([
                    'message' => 'This leave type is not available for your gender.',
                ], 422);
            }

            $year = Carbon::parse($validated['start_date'])->year;

            $balance = LeaveBalance::query()
                ->where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveType->id)
                ->where('year', $year)
                ->first();

            $workingDays = $this->calculateWorkingDays(
                $employee,
                $validated['start_date'],
                $validated['end_date']
            );

            $totalDays = $validated['is_half_day'] ?? false ? 0.5 : $workingDays;

            if ($balance && $totalDays > $balance->available_days) {
                return response()->json([
                    'message' => 'Insufficient leave balance. Available: '.$balance->available_days.' days.',
                ], 422);
            }

            $attachmentPath = null;
            if ($request->hasFile('attachment')) {
                $attachmentPath = $request->file('attachment')->store("leave-attachments/{$employee->id}", 'public');
            }

            $leaveRequest = LeaveRequest::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $leaveType->id,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'total_days' => $totalDays,
                'is_half_day' => $validated['is_half_day'] ?? false,
                'half_day_period' => $validated['half_day_period'] ?? null,
                'reason' => $validated['reason'],
                'attachment_path' => $attachmentPath,
                'status' => 'pending',
            ]);

            if ($balance) {
                $balance->update([
                    'pending_days' => $balance->pending_days + $totalDays,
                    'available_days' => $balance->available_days - $totalDays,
                ]);
            }

            // Notify department approvers and admin users
            $leaveRequest->load('employee.department', 'leaveType');
            $notifiedUserIds = [];

            $approvers = \App\Models\DepartmentApprover::forDepartment(
                $leaveRequest->employee->department_id
            )->forType('leave')->with('approver.user')->get();

            foreach ($approvers as $deptApprover) {
                if ($deptApprover->approver?->user) {
                    $deptApprover->approver->user->notify(
                        new \App\Notifications\Hr\LeaveRequestSubmitted($leaveRequest)
                    );
                    $notifiedUserIds[] = $deptApprover->approver->user->id;
                }
            }

            // Also notify admin users who weren't already notified as approvers
            $admins = \App\Models\User::where('role', 'admin')
                ->whereNotIn('id', $notifiedUserIds)
                ->get();

            foreach ($admins as $admin) {
                $admin->notify(
                    new \App\Notifications\Hr\LeaveRequestSubmitted($leaveRequest)
                );
            }

            return response()->json([
                'data' => $leaveRequest,
                'message' => 'Leave request submitted successfully.',
            ], 201);
        });
    }

    /**
     * Cancel a leave request.
     */
    public function cancel(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        if ($leaveRequest->employee_id !== $employee->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $year = Carbon::parse($leaveRequest->start_date)->year;

        if ($leaveRequest->status === 'pending') {
            return DB::transaction(function () use ($leaveRequest, $year) {
                $leaveRequest->update(['status' => 'cancelled']);

                $balance = LeaveBalance::query()
                    ->where('employee_id', $leaveRequest->employee_id)
                    ->where('leave_type_id', $leaveRequest->leave_type_id)
                    ->where('year', $year)
                    ->first();

                if ($balance) {
                    $balance->update([
                        'pending_days' => $balance->pending_days - $leaveRequest->total_days,
                        'available_days' => $balance->available_days + $leaveRequest->total_days,
                    ]);
                }

                // Notify approvers and admins about cancellation
                $leaveRequest->load('employee.department', 'leaveType');
                $notifiedUserIds = [];

                $approvers = \App\Models\DepartmentApprover::forDepartment(
                    $leaveRequest->employee->department_id
                )->forType('leave')->with('approver.user')->get();

                foreach ($approvers as $deptApprover) {
                    if ($deptApprover->approver?->user) {
                        $deptApprover->approver->user->notify(
                            new \App\Notifications\Hr\LeaveRequestCancelled($leaveRequest)
                        );
                        $notifiedUserIds[] = $deptApprover->approver->user->id;
                    }
                }

                $admins = \App\Models\User::where('role', 'admin')
                    ->whereNotIn('id', $notifiedUserIds)
                    ->get();
                foreach ($admins as $admin) {
                    $admin->notify(new \App\Notifications\Hr\LeaveRequestCancelled($leaveRequest));
                }

                return response()->json([
                    'data' => $leaveRequest->fresh('leaveType'),
                    'message' => 'Leave request cancelled successfully.',
                ]);
            });
        }

        if ($leaveRequest->status === 'approved') {
            $startDate = Carbon::parse($leaveRequest->start_date);

            if ($startDate->lte(Carbon::today())) {
                return response()->json([
                    'message' => 'Cannot cancel approved leave that has already started or passed.',
                ], 422);
            }

            return DB::transaction(function () use ($leaveRequest, $year) {
                $leaveRequest->update(['status' => 'cancelled']);

                $balance = LeaveBalance::query()
                    ->where('employee_id', $leaveRequest->employee_id)
                    ->where('leave_type_id', $leaveRequest->leave_type_id)
                    ->where('year', $year)
                    ->first();

                if ($balance) {
                    $balance->update([
                        'used_days' => $balance->used_days - $leaveRequest->total_days,
                        'available_days' => $balance->available_days + $leaveRequest->total_days,
                    ]);
                }

                AttendanceLog::query()
                    ->where('employee_id', $leaveRequest->employee_id)
                    ->where('status', 'on_leave')
                    ->whereDate('date', '>=', $leaveRequest->start_date)
                    ->whereDate('date', '<=', $leaveRequest->end_date)
                    ->delete();

                if ($leaveRequest->is_replacement_leave && $leaveRequest->replacement_hours_deducted) {
                    $otRequests = OvertimeRequest::query()
                        ->where('employee_id', $leaveRequest->employee_id)
                        ->where('status', 'completed')
                        ->where('replacement_hours_used', '>', 0)
                        ->orderByDesc('created_at')
                        ->get();

                    $hoursToRestore = $leaveRequest->replacement_hours_deducted;

                    foreach ($otRequests as $otRequest) {
                        if ($hoursToRestore <= 0) {
                            break;
                        }
                        $restore = min($otRequest->replacement_hours_used, $hoursToRestore);
                        $otRequest->decrement('replacement_hours_used', $restore);
                        $hoursToRestore -= $restore;
                    }
                }

                // Notify approvers and admins about cancellation
                $leaveRequest->load('employee.department', 'leaveType');
                $notifiedUserIds = [];

                $approvers = \App\Models\DepartmentApprover::forDepartment(
                    $leaveRequest->employee->department_id
                )->forType('leave')->with('approver.user')->get();

                foreach ($approvers as $deptApprover) {
                    if ($deptApprover->approver?->user) {
                        $deptApprover->approver->user->notify(
                            new \App\Notifications\Hr\LeaveRequestCancelled($leaveRequest)
                        );
                        $notifiedUserIds[] = $deptApprover->approver->user->id;
                    }
                }

                $admins = \App\Models\User::where('role', 'admin')
                    ->whereNotIn('id', $notifiedUserIds)
                    ->get();
                foreach ($admins as $admin) {
                    $admin->notify(new \App\Notifications\Hr\LeaveRequestCancelled($leaveRequest));
                }

                return response()->json([
                    'data' => $leaveRequest->fresh('leaveType'),
                    'message' => 'Approved leave cancelled successfully.',
                ]);
            });
        }

        return response()->json([
            'message' => 'This leave request cannot be cancelled.',
        ], 422);
    }

    /**
     * Calculate working days between two dates (excluding weekends and holidays).
     */
    public function calculateDays(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $employee = $request->user()->employee;

        $workingDays = $this->calculateWorkingDays(
            $employee,
            $validated['start_date'],
            $validated['end_date']
        );

        return response()->json([
            'data' => ['working_days' => $workingDays],
        ]);
    }

    /**
     * Calculate the number of working days between two dates for an employee.
     */
    private function calculateWorkingDays($employee, string $startDate, string $endDate): int
    {
        $schedule = null;

        if ($employee) {
            $schedule = EmployeeSchedule::query()
                ->where('employee_id', $employee->id)
                ->active()
                ->with('workSchedule')
                ->first();
        }

        $workingDayNumbers = $schedule && $schedule->workSchedule
            ? ($schedule->workSchedule->working_days ?? [1, 2, 3, 4, 5])
            : [1, 2, 3, 4, 5];

        $holidays = Holiday::query()
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->toArray();

        $current = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $count = 0;

        while ($current->lte($end)) {
            $dayOfWeek = $current->dayOfWeekIso;

            if (in_array($dayOfWeek, $workingDayNumbers) && ! in_array($current->toDateString(), $holidays)) {
                $count++;
            }

            $current->addDay();
        }

        return $count;
    }
}
