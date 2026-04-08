<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\ClaimApprover;
use App\Models\ClaimRequest;
use App\Models\DepartmentApprover;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OfficeExitPermission;
use App\Models\OvertimeClaimRequest;
use App\Models\OvertimeRequest;
use App\Services\Hr\TierApprovalService;
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

    /**
     * Get department IDs grouped with their tier(s) for the given approver and type.
     *
     * @return array<int, array<int, int>> [dept_id => [tier1, tier2], ...]
     */
    private function getDeptIdsForTier(int $employeeId, string $type): array
    {
        return DepartmentApprover::where('approver_employee_id', $employeeId)
            ->where('approval_type', $type)
            ->get(['department_id', 'tier'])
            ->groupBy('department_id')
            ->map(fn ($items) => $items->pluck('tier')->toArray())
            ->toArray();
    }

    private function getEmployee(Request $request): ?Employee
    {
        return $request->user()->employee;
    }

    /**
     * Apply tier-aware filtering to a query builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array<int, array<int, int>>  $deptTiers
     */
    private function applyTierFilter($query, array $deptTiers): void
    {
        $query->where(function ($query) use ($deptTiers) {
            foreach ($deptTiers as $deptId => $tiers) {
                $query->orWhere(function ($q) use ($deptId, $tiers) {
                    $q->whereHas('employee', fn ($sq) => $sq->where('department_id', $deptId))
                        ->whereIn('current_approval_tier', $tiers);
                });
            }
        });
    }

    public function summary(Request $request): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        // Overtime (requests + claims)
        $otDeptTiers = $this->getDeptIdsForTier($employee->id, 'overtime');
        $otDepts = array_keys($otDeptTiers);

        $otRequestPending = empty($otDepts) ? 0
            : OvertimeRequest::whereHas('employee', fn ($q) => $q->whereIn('department_id', $otDepts))
                ->where('status', 'pending')
                ->where(function ($query) use ($otDeptTiers) {
                    foreach ($otDeptTiers as $deptId => $tiers) {
                        $query->orWhere(function ($q) use ($deptId, $tiers) {
                            $q->whereHas('employee', fn ($sq) => $sq->where('department_id', $deptId))
                                ->whereIn('current_approval_tier', $tiers);
                        });
                    }
                })
                ->count();

        $otClaimPending = empty($otDepts) ? 0
            : OvertimeClaimRequest::whereHas('employee', fn ($q) => $q->whereIn('department_id', $otDepts))
                ->where('status', 'pending')
                ->where(function ($query) use ($otDeptTiers) {
                    foreach ($otDeptTiers as $deptId => $tiers) {
                        $query->orWhere(function ($q) use ($deptId, $tiers) {
                            $q->whereHas('employee', fn ($sq) => $sq->where('department_id', $deptId))
                                ->whereIn('current_approval_tier', $tiers);
                        });
                    }
                })
                ->count();

        $otPending = $otRequestPending + $otClaimPending;

        // Leave
        $leaveDeptTiers = $this->getDeptIdsForTier($employee->id, 'leave');
        $leaveDepts = array_keys($leaveDeptTiers);

        $leavePending = empty($leaveDepts) ? 0
            : LeaveRequest::whereHas('employee', fn ($q) => $q->whereIn('department_id', $leaveDepts))
                ->where('status', 'pending')
                ->where(function ($query) use ($leaveDeptTiers) {
                    foreach ($leaveDeptTiers as $deptId => $tiers) {
                        $query->orWhere(function ($q) use ($deptId, $tiers) {
                            $q->whereHas('employee', fn ($sq) => $sq->where('department_id', $deptId))
                                ->whereIn('current_approval_tier', $tiers);
                        });
                    }
                })
                ->count();

        // Claims (dept-based + individual)
        $claimDeptTiers = $this->getDeptIdsForTier($employee->id, 'claims');
        $claimDepts = array_keys($claimDeptTiers);
        $isIndividualClaims = ClaimApprover::where('approver_id', $employee->id)
            ->where('is_active', true)
            ->exists();

        $claimPending = 0;
        $isClaimsAssigned = ! empty($claimDepts) || $isIndividualClaims;

        if ($isClaimsAssigned) {
            $claimQuery = ClaimRequest::where('status', 'pending');

            if (! empty($claimDepts) && $isIndividualClaims) {
                $claimQuery->where(function ($q) use ($claimDeptTiers, $employee) {
                    $q->where(function ($deptQ) use ($claimDeptTiers) {
                        foreach ($claimDeptTiers as $deptId => $tiers) {
                            $deptQ->orWhere(function ($sq) use ($deptId, $tiers) {
                                $sq->whereHas('employee', fn ($esq) => $esq->where('department_id', $deptId))
                                    ->whereIn('current_approval_tier', $tiers);
                            });
                        }
                    })->orWhereHas('employee.claimApprovers', fn ($sq) => $sq->where('approver_id', $employee->id)->where('is_active', true));
                });
            } elseif (! empty($claimDepts)) {
                $claimQuery->where(function ($q) use ($claimDeptTiers) {
                    foreach ($claimDeptTiers as $deptId => $tiers) {
                        $q->orWhere(function ($sq) use ($deptId, $tiers) {
                            $sq->whereHas('employee', fn ($esq) => $esq->where('department_id', $deptId))
                                ->whereIn('current_approval_tier', $tiers);
                        });
                    }
                });
            } else {
                $claimQuery->whereHas('employee.claimApprovers', fn ($q) => $q->where('approver_id', $employee->id)->where('is_active', true));
            }

            $claimPending = $claimQuery->count();
        }

        // Exit permissions
        $exitDeptTiers = $this->getDeptIdsForTier($employee->id, 'exit_permission');
        $exitDepts = array_keys($exitDeptTiers);

        $exitPending = empty($exitDepts) ? 0
            : OfficeExitPermission::whereHas('employee', fn ($q) => $q->whereIn('department_id', $exitDepts))
                ->where('status', 'pending')
                ->where(function ($query) use ($exitDeptTiers) {
                    foreach ($exitDeptTiers as $deptId => $tiers) {
                        $query->orWhere(function ($q) use ($deptId, $tiers) {
                            $q->whereHas('employee', fn ($sq) => $sq->where('department_id', $deptId))
                                ->whereIn('current_approval_tier', $tiers);
                        });
                    }
                })
                ->count();

        $isApprover = ! empty($otDepts) || ! empty($leaveDepts) || $isClaimsAssigned || ! empty($exitDepts);

        return response()->json([
            'isApprover' => $isApprover,
            'overtime' => ['pending' => $otPending, 'isAssigned' => ! empty($otDepts)],
            'leave' => ['pending' => $leavePending, 'isAssigned' => ! empty($leaveDepts)],
            'claims' => ['pending' => $claimPending, 'isAssigned' => $isClaimsAssigned],
            'exit_permission' => ['pending' => $exitPending, 'isAssigned' => ! empty($exitDepts)],
        ]);
    }

    // ── Overtime ─────────────────────────────────────────────────────────────

    public function overtime(Request $request): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $deptTiers = $this->getDeptIdsForTier($employee->id, 'overtime');
        $deptIds = array_keys($deptTiers);

        if (empty($deptIds)) {
            return response()->json(['data' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1]);
        }

        $query = OvertimeRequest::with([
            'employee:id,full_name,position_id,department_id',
            'employee.position:id,title',
            'employee.department:id,name',
        ])->whereHas('employee', fn ($q) => $q->whereIn('department_id', $deptIds));

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Only apply tier filter for pending requests (show history without tier filter)
        if (! $request->filled('status') || $request->status === 'pending') {
            $this->applyTierFilter($query, $deptTiers);
        }

        return response()->json($query->orderByDesc('created_at')->paginate(20));
    }

    public function approveOvertime(Request $request, OvertimeRequest $overtimeRequest): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $service = app(TierApprovalService::class);
        $deptId = $overtimeRequest->employee->department_id;
        $currentTier = $overtimeRequest->current_approval_tier;

        if (! $service->isApproverForTier($employee->id, $deptId, 'overtime', $currentTier)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($overtimeRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be approved.'], 422);
        }

        $result = $service->approve($overtimeRequest, $employee, 'overtime', $deptId);

        if ($result['fully_approved']) {
            $overtimeRequest->update([
                'status' => 'completed',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'actual_hours' => $overtimeRequest->estimated_hours,
                'replacement_hours_earned' => $overtimeRequest->estimated_hours,
            ]);

            if ($overtimeRequest->employee->user) {
                $overtimeRequest->employee->user->notify(
                    new \App\Notifications\Hr\OvertimeRequestDecision($overtimeRequest, 'approved')
                );
            }
        }

        return response()->json($overtimeRequest->fresh([
            'employee:id,full_name,position_id,department_id',
            'employee.position:id,title',
            'employee.department:id,name',
        ]));
    }

    public function rejectOvertime(Request $request, OvertimeRequest $overtimeRequest): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $service = app(TierApprovalService::class);
        $deptId = $overtimeRequest->employee->department_id;
        $currentTier = $overtimeRequest->current_approval_tier;

        if (! $service->isApproverForTier($employee->id, $deptId, 'overtime', $currentTier)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($overtimeRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be rejected.'], 422);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5'],
        ]);

        $service->reject($overtimeRequest, $employee, $validated['rejection_reason']);

        $overtimeRequest->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        if ($overtimeRequest->employee->user) {
            $overtimeRequest->employee->user->notify(
                new \App\Notifications\Hr\OvertimeRequestDecision($overtimeRequest, 'rejected')
            );
        }

        return response()->json($overtimeRequest->fresh([
            'employee:id,full_name,position_id,department_id',
            'employee.position:id,title',
            'employee.department:id,name',
        ]));
    }

    // ── Overtime Claims ───────────────────────────────────────────────────────

    public function overtimeClaims(Request $request): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $deptTiers = $this->getDeptIdsForTier($employee->id, 'overtime');
        $deptIds = array_keys($deptTiers);

        if (empty($deptIds)) {
            return response()->json(['data' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1]);
        }

        $query = OvertimeClaimRequest::with([
            'employee:id,full_name,position_id,department_id',
            'employee.position:id,title',
            'employee.department:id,name',
        ])->whereHas('employee', fn ($q) => $q->whereIn('department_id', $deptIds));

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Only apply tier filter for pending requests
        if (! $request->filled('status') || $request->status === 'pending') {
            $this->applyTierFilter($query, $deptTiers);
        }

        return response()->json($query->orderByDesc('created_at')->paginate(20));
    }

    public function approveOvertimeClaim(Request $request, OvertimeClaimRequest $overtimeClaimRequest): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $service = app(TierApprovalService::class);
        $deptId = $overtimeClaimRequest->employee->department_id;
        $currentTier = $overtimeClaimRequest->current_approval_tier;

        if (! $service->isApproverForTier($employee->id, $deptId, 'overtime', $currentTier)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($overtimeClaimRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending claims can be approved.'], 422);
        }

        $result = $service->approve($overtimeClaimRequest, $employee, 'overtime', $deptId);

        if ($result['fully_approved']) {
            $overtimeClaimRequest->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            // Link attendance record if it exists for that date
            $attendanceLog = AttendanceLog::where('employee_id', $overtimeClaimRequest->employee_id)
                ->where('date', $overtimeClaimRequest->claim_date)
                ->first();

            if ($attendanceLog) {
                $newLateMinutes = max(0, (int) $attendanceLog->late_minutes - $overtimeClaimRequest->duration_minutes);
                $newStatus = ($newLateMinutes === 0 && $attendanceLog->status === 'late') ? 'present' : $attendanceLog->status;

                $attendanceLog->update([
                    'ot_claim_id' => $overtimeClaimRequest->id,
                    'late_minutes' => $newLateMinutes,
                    'status' => $newStatus,
                ]);

                $overtimeClaimRequest->update(['attendance_id' => $attendanceLog->id]);
            }

            // Notify employee
            if ($overtimeClaimRequest->employee->user) {
                $overtimeClaimRequest->employee->user->notify(
                    new \App\Notifications\Hr\OvertimeClaimDecision($overtimeClaimRequest, 'approved')
                );
            }
        }

        return response()->json($overtimeClaimRequest->fresh([
            'employee:id,full_name,position_id,department_id',
            'employee.position:id,title',
            'employee.department:id,name',
        ]));
    }

    public function rejectOvertimeClaim(Request $request, OvertimeClaimRequest $overtimeClaimRequest): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $service = app(TierApprovalService::class);
        $deptId = $overtimeClaimRequest->employee->department_id;
        $currentTier = $overtimeClaimRequest->current_approval_tier;

        if (! $service->isApproverForTier($employee->id, $deptId, 'overtime', $currentTier)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($overtimeClaimRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending claims can be rejected.'], 422);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5'],
        ]);

        $service->reject($overtimeClaimRequest, $employee, $validated['rejection_reason']);

        $overtimeClaimRequest->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        if ($overtimeClaimRequest->employee->user) {
            $overtimeClaimRequest->employee->user->notify(
                new \App\Notifications\Hr\OvertimeClaimDecision($overtimeClaimRequest, 'rejected')
            );
        }

        return response()->json($overtimeClaimRequest->fresh([
            'employee:id,full_name,position_id,department_id',
            'employee.position:id,title',
            'employee.department:id,name',
        ]));
    }

    // ── Leave ─────────────────────────────────────────────────────────────────

    public function leave(Request $request): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $deptTiers = $this->getDeptIdsForTier($employee->id, 'leave');
        $deptIds = array_keys($deptTiers);

        if (empty($deptIds)) {
            return response()->json(['data' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1]);
        }

        $query = LeaveRequest::with([
            'employee:id,full_name,position_id,department_id',
            'employee.position:id,title',
            'employee.department:id,name',
            'leaveType:id,name,color',
        ])->whereHas('employee', fn ($q) => $q->whereIn('department_id', $deptIds));

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Only apply tier filter for pending requests
        if (! $request->filled('status') || $request->status === 'pending') {
            $this->applyTierFilter($query, $deptTiers);
        }

        return response()->json($query->orderByDesc('created_at')->paginate(20));
    }

    public function approveLeave(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $service = app(TierApprovalService::class);
        $deptId = $leaveRequest->employee->department_id;
        $currentTier = $leaveRequest->current_approval_tier;

        if (! $service->isApproverForTier($employee->id, $deptId, 'leave', $currentTier)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($leaveRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be approved.'], 422);
        }

        $result = $service->approve($leaveRequest, $employee, 'leave', $deptId);

        if ($result['fully_approved']) {
            $controller = app(\App\Http\Controllers\Api\Hr\HrLeaveRequestController::class);
            $controller->approve($request, $leaveRequest);
        }

        return response()->json($leaveRequest->fresh([
            'employee:id,full_name,position_id,department_id',
            'employee.position:id,title',
            'employee.department:id,name',
            'leaveType:id,name',
        ]));
    }

    public function rejectLeave(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $service = app(TierApprovalService::class);
        $deptId = $leaveRequest->employee->department_id;
        $currentTier = $leaveRequest->current_approval_tier;

        if (! $service->isApproverForTier($employee->id, $deptId, 'leave', $currentTier)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($leaveRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be rejected.'], 422);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5'],
        ]);

        $service->reject($leaveRequest, $employee, $validated['rejection_reason']);

        $controller = app(\App\Http\Controllers\Api\Hr\HrLeaveRequestController::class);
        $controller->reject($request, $leaveRequest);

        return response()->json($leaveRequest->fresh([
            'employee:id,full_name,position_id,department_id',
            'employee.position:id,title',
            'employee.department:id,name',
            'leaveType:id,name',
        ]));
    }

    // ── Claims ────────────────────────────────────────────────────────────────

    public function claims(Request $request): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $deptTiers = $this->getDeptIdsForTier($employee->id, 'claims');
        $deptIds = array_keys($deptTiers);
        $isIndividualClaims = ClaimApprover::where('approver_id', $employee->id)
            ->where('is_active', true)
            ->exists();

        if (empty($deptIds) && ! $isIndividualClaims) {
            return response()->json(['data' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1]);
        }

        $query = ClaimRequest::with([
            'employee:id,full_name,position_id,department_id',
            'employee.position:id,title',
            'employee.department:id,name',
            'claimType:id,name',
        ]);

        $isPendingFilter = ! $request->filled('status') || $request->status === 'pending';

        if (! empty($deptIds) && $isIndividualClaims) {
            $query->where(function ($q) use ($deptIds, $deptTiers, $employee, $isPendingFilter) {
                $q->where(function ($deptQ) use ($deptIds, $deptTiers, $isPendingFilter) {
                    $deptQ->whereHas('employee', fn ($sq) => $sq->whereIn('department_id', $deptIds));
                    if ($isPendingFilter) {
                        $deptQ->where(function ($tierQ) use ($deptTiers) {
                            foreach ($deptTiers as $deptId => $tiers) {
                                $tierQ->orWhere(function ($sq) use ($deptId, $tiers) {
                                    $sq->whereHas('employee', fn ($esq) => $esq->where('department_id', $deptId))
                                        ->whereIn('current_approval_tier', $tiers);
                                });
                            }
                        });
                    }
                })->orWhereHas('employee.claimApprovers', fn ($sq) => $sq->where('approver_id', $employee->id)->where('is_active', true));
            });
        } elseif (! empty($deptIds)) {
            $query->whereHas('employee', fn ($q) => $q->whereIn('department_id', $deptIds));
            if ($isPendingFilter) {
                $this->applyTierFilter($query, $deptTiers);
            }
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

        $service = app(TierApprovalService::class);
        $deptId = $claimRequest->employee->department_id;
        $currentTier = $claimRequest->current_approval_tier;

        // Check individual approver (bypasses tier system)
        $isIndividualApprover = ClaimApprover::where('approver_id', $employee->id)
            ->where('employee_id', $claimRequest->employee_id)
            ->where('is_active', true)
            ->exists();

        $deptIds = $this->getDeptIds($employee->id, 'claims');
        $inDept = ! empty($deptIds) && in_array($deptId, $deptIds);

        if (! $inDept && ! $isIndividualApprover) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // For department-based approvers, check tier authorization
        if ($inDept && ! $isIndividualApprover) {
            if (! $service->isApproverForTier($employee->id, $deptId, 'claims', $currentTier)) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
        }

        if ($claimRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be approved.'], 422);
        }

        if ($isIndividualApprover) {
            // Individual approvers bypass tier system — approve directly
            $controller = app(\App\Http\Controllers\Api\Hr\HrClaimRequestController::class);
            $controller->approve($request, $claimRequest);
        } else {
            // Department-based: use tier approval
            $result = $service->approve($claimRequest, $employee, 'claims', $deptId);

            if ($result['fully_approved']) {
                $controller = app(\App\Http\Controllers\Api\Hr\HrClaimRequestController::class);
                $controller->approve($request, $claimRequest);
            }
        }

        return response()->json($claimRequest->fresh([
            'employee:id,full_name,position_id,department_id',
            'employee.position:id,title',
            'employee.department:id,name',
            'claimType:id,name',
        ]));
    }

    public function rejectClaim(Request $request, ClaimRequest $claimRequest): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $service = app(TierApprovalService::class);
        $deptId = $claimRequest->employee->department_id;
        $currentTier = $claimRequest->current_approval_tier;

        // Check individual approver (bypasses tier system)
        $isIndividualApprover = ClaimApprover::where('approver_id', $employee->id)
            ->where('employee_id', $claimRequest->employee_id)
            ->where('is_active', true)
            ->exists();

        $deptIds = $this->getDeptIds($employee->id, 'claims');
        $inDept = ! empty($deptIds) && in_array($deptId, $deptIds);

        if (! $inDept && ! $isIndividualApprover) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // For department-based approvers, check tier authorization
        if ($inDept && ! $isIndividualApprover) {
            if (! $service->isApproverForTier($employee->id, $deptId, 'claims', $currentTier)) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
        }

        if ($claimRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be rejected.'], 422);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5'],
        ]);

        if (! $isIndividualApprover) {
            $service->reject($claimRequest, $employee, $validated['rejection_reason']);
        }

        $controller = app(\App\Http\Controllers\Api\Hr\HrClaimRequestController::class);
        $controller->reject($request, $claimRequest);

        return response()->json($claimRequest->fresh([
            'employee:id,full_name,position_id,department_id',
            'employee.position:id,title',
            'employee.department:id,name',
            'claimType:id,name',
        ]));
    }

    // ── Exit Permissions ──────────────────────────────────────────────────────

    public function exitPermissions(Request $request): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $deptTiers = $this->getDeptIdsForTier($employee->id, 'exit_permission');
        $deptIds = array_keys($deptTiers);

        if (empty($deptIds)) {
            return response()->json(['data' => []]);
        }

        $query = OfficeExitPermission::whereHas('employee', fn ($q) => $q->whereIn('department_id', $deptIds))
            ->with(['employee.department', 'approver'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Only apply tier filter for pending requests
        if (! $request->filled('status') || $request->status === 'pending') {
            $this->applyTierFilter($query, $deptTiers);
        }

        return response()->json(['data' => $query->paginate(20)]);
    }

    public function approveExitPermission(Request $request, OfficeExitPermission $officeExitPermission): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $officeExitPermission->load('employee');

        $service = app(TierApprovalService::class);
        $deptId = $officeExitPermission->employee->department_id;
        $currentTier = $officeExitPermission->current_approval_tier;

        if (! $service->isApproverForTier($employee->id, $deptId, 'exit_permission', $currentTier)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if (! $officeExitPermission->isPending()) {
            return response()->json(['message' => 'Only pending requests can be approved.'], 422);
        }

        $result = $service->approve($officeExitPermission, $employee, 'exit_permission', $deptId);

        if ($result['fully_approved']) {
            return app(HrOfficeExitPermissionController::class)->approve($request, $officeExitPermission);
        }

        return response()->json($officeExitPermission->fresh([
            'employee.department',
            'approver',
        ]));
    }

    public function rejectExitPermission(Request $request, OfficeExitPermission $officeExitPermission): JsonResponse
    {
        $employee = $this->getEmployee($request);

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $officeExitPermission->load('employee');

        $service = app(TierApprovalService::class);
        $deptId = $officeExitPermission->employee->department_id;
        $currentTier = $officeExitPermission->current_approval_tier;

        if (! $service->isApproverForTier($employee->id, $deptId, 'exit_permission', $currentTier)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if (! $officeExitPermission->isPending()) {
            return response()->json(['message' => 'Only pending requests can be rejected.'], 422);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5'],
        ]);

        $service->reject($officeExitPermission, $employee, $validated['rejection_reason']);

        return app(HrOfficeExitPermissionController::class)->reject($request, $officeExitPermission);
    }
}
