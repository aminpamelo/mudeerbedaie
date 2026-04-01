<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\ClaimApprover;
use App\Models\ClaimRequest;
use App\Models\DepartmentApprover;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrMyApprovalController extends Controller
{
    private function getDeptIds(int $employeeId, string $type): array
    {
        return DepartmentApprover::where('approver_employee_id', $employeeId)
            ->where('approval_type', $type)
            ->pluck('department_id')
            ->toArray();
    }

    private function getEmployee(Request $request): ?Employee
    {
        return $request->user()->employee;
    }

    public function summary(Request $request): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $otDepts = $this->getDeptIds($employee->id, 'overtime');
        $leaveDepts = $this->getDeptIds($employee->id, 'leave');
        $claimDepts = $this->getDeptIds($employee->id, 'claims');
        $isIndividualClaims = ClaimApprover::where('approver_id', $employee->id)
            ->where('is_active', true)
            ->exists();

        $otPending = empty($otDepts) ? 0
            : OvertimeRequest::whereHas('employee', fn ($q) => $q->whereIn('department_id', $otDepts))
                ->where('status', 'pending')
                ->count();

        $leavePending = empty($leaveDepts) ? 0
            : LeaveRequest::whereHas('employee', fn ($q) => $q->whereIn('department_id', $leaveDepts))
                ->where('status', 'pending')
                ->count();

        $claimPending = 0;
        $isClaimsAssigned = ! empty($claimDepts) || $isIndividualClaims;

        if ($isClaimsAssigned) {
            $claimQuery = ClaimRequest::where('status', 'pending');

            if (! empty($claimDepts) && $isIndividualClaims) {
                $claimQuery->where(function ($q) use ($claimDepts, $employee) {
                    $q->whereHas('employee', fn ($sq) => $sq->whereIn('department_id', $claimDepts))
                        ->orWhereHas('employee.claimApprovers', fn ($sq) => $sq->where('approver_id', $employee->id)->where('is_active', true));
                });
            } elseif (! empty($claimDepts)) {
                $claimQuery->whereHas('employee', fn ($q) => $q->whereIn('department_id', $claimDepts));
            } else {
                $claimQuery->whereHas('employee.claimApprovers', fn ($q) => $q->where('approver_id', $employee->id)->where('is_active', true));
            }

            $claimPending = $claimQuery->count();
        }

        $isApprover = ! empty($otDepts) || ! empty($leaveDepts) || $isClaimsAssigned;

        return response()->json([
            'isApprover' => $isApprover,
            'overtime' => ['pending' => $otPending, 'isAssigned' => ! empty($otDepts)],
            'leave' => ['pending' => $leavePending, 'isAssigned' => ! empty($leaveDepts)],
            'claims' => ['pending' => $claimPending, 'isAssigned' => $isClaimsAssigned],
        ]);
    }

    // ── Overtime ─────────────────────────────────────────────────────────────

    public function overtime(Request $request): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $deptIds = $this->getDeptIds($employee->id, 'overtime');

        if (empty($deptIds)) {
            return response()->json(['data' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1]);
        }

        $query = OvertimeRequest::with([
            'employee:id,name,position_id,department_id',
            'employee.position:id,name',
            'employee.department:id,name',
        ])->whereHas('employee', fn ($q) => $q->whereIn('department_id', $deptIds));

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        return response()->json($query->orderByDesc('created_at')->paginate(20));
    }

    public function approveOvertime(Request $request, OvertimeRequest $overtimeRequest): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $deptIds = $this->getDeptIds($employee->id, 'overtime');

        if (! in_array($overtimeRequest->employee->department_id, $deptIds)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($overtimeRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be approved.'], 422);
        }

        $overtimeRequest->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        if ($overtimeRequest->employee->user) {
            $overtimeRequest->employee->user->notify(
                new \App\Notifications\Hr\OvertimeRequestDecision($overtimeRequest)
            );
        }

        return response()->json($overtimeRequest->fresh(['employee.position', 'employee.department']));
    }

    public function rejectOvertime(Request $request, OvertimeRequest $overtimeRequest): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $deptIds = $this->getDeptIds($employee->id, 'overtime');

        if (! in_array($overtimeRequest->employee->department_id, $deptIds)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($overtimeRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be rejected.'], 422);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5'],
        ]);

        $overtimeRequest->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        if ($overtimeRequest->employee->user) {
            $overtimeRequest->employee->user->notify(
                new \App\Notifications\Hr\OvertimeRequestDecision($overtimeRequest)
            );
        }

        return response()->json($overtimeRequest->fresh(['employee.position', 'employee.department']));
    }

    // ── Leave ─────────────────────────────────────────────────────────────────

    public function leave(Request $request): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $deptIds = $this->getDeptIds($employee->id, 'leave');

        if (empty($deptIds)) {
            return response()->json(['data' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1]);
        }

        $query = LeaveRequest::with([
            'employee:id,name,position_id,department_id',
            'employee.position:id,name',
            'employee.department:id,name',
            'leaveType:id,name,color',
        ])->whereHas('employee', fn ($q) => $q->whereIn('department_id', $deptIds));

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        return response()->json($query->orderByDesc('created_at')->paginate(20));
    }

    public function approveLeave(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $deptIds = $this->getDeptIds($employee->id, 'leave');

        if (! in_array($leaveRequest->employee->department_id, $deptIds)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($leaveRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be approved.'], 422);
        }

        $controller = app(\App\Http\Controllers\Api\Hr\HrLeaveRequestController::class);

        return $controller->approve($request, $leaveRequest);
    }

    public function rejectLeave(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $deptIds = $this->getDeptIds($employee->id, 'leave');

        if (! in_array($leaveRequest->employee->department_id, $deptIds)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($leaveRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be rejected.'], 422);
        }

        $controller = app(\App\Http\Controllers\Api\Hr\HrLeaveRequestController::class);

        return $controller->reject($request, $leaveRequest);
    }

    // ── Claims ────────────────────────────────────────────────────────────────

    public function claims(Request $request): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $deptIds = $this->getDeptIds($employee->id, 'claims');
        $isIndividualClaims = ClaimApprover::where('approver_id', $employee->id)
            ->where('is_active', true)
            ->exists();

        if (empty($deptIds) && ! $isIndividualClaims) {
            return response()->json(['data' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1]);
        }

        $query = ClaimRequest::with([
            'employee:id,name,position_id,department_id',
            'employee.position:id,name',
            'employee.department:id,name',
            'claimType:id,name',
        ]);

        if (! empty($deptIds) && $isIndividualClaims) {
            $query->where(function ($q) use ($deptIds, $employee) {
                $q->whereHas('employee', fn ($sq) => $sq->whereIn('department_id', $deptIds))
                    ->orWhereHas('employee.claimApprovers', fn ($sq) => $sq->where('approver_id', $employee->id)->where('is_active', true));
            });
        } elseif (! empty($deptIds)) {
            $query->whereHas('employee', fn ($q) => $q->whereIn('department_id', $deptIds));
        } else {
            $query->whereHas('employee.claimApprovers', fn ($q) => $q->where('approver_id', $employee->id)->where('is_active', true));
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        return response()->json($query->orderByDesc('created_at')->paginate(20));
    }

    public function approveClaim(Request $request, ClaimRequest $claimRequest): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $deptIds = $this->getDeptIds($employee->id, 'claims');
        $isIndividualApprover = ClaimApprover::where('approver_id', $employee->id)
            ->where('employee_id', $claimRequest->employee_id)
            ->where('is_active', true)
            ->exists();

        $inDept = ! empty($deptIds) && in_array($claimRequest->employee->department_id, $deptIds);

        if (! $inDept && ! $isIndividualApprover) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($claimRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be approved.'], 422);
        }

        $controller = app(\App\Http\Controllers\Api\Hr\HrClaimRequestController::class);

        return $controller->approve($request, $claimRequest);
    }

    public function rejectClaim(Request $request, ClaimRequest $claimRequest): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $deptIds = $this->getDeptIds($employee->id, 'claims');
        $isIndividualApprover = ClaimApprover::where('approver_id', $employee->id)
            ->where('employee_id', $claimRequest->employee_id)
            ->where('is_active', true)
            ->exists();

        $inDept = ! empty($deptIds) && in_array($claimRequest->employee->department_id, $deptIds);

        if (! $inDept && ! $isIndividualApprover) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($claimRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be rejected.'], 422);
        }

        $controller = app(\App\Http\Controllers\Api\Hr\HrClaimRequestController::class);

        return $controller->reject($request, $claimRequest);
    }
}
