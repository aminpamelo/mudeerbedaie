<?php

namespace App\Services\Hr;

use App\Models\ApprovalLog;
use App\Models\DepartmentApprover;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Model;

class TierApprovalService
{
    /**
     * Get the max tier configured for a department + approval type.
     */
    public function getMaxTier(int $departmentId, string $approvalType): int
    {
        return DepartmentApprover::where('department_id', $departmentId)
            ->where('approval_type', $approvalType)
            ->max('tier') ?? 1;
    }

    /**
     * Check if the employee is an approver for the given department, type, and tier.
     */
    public function isApproverForTier(int $employeeId, int $departmentId, string $approvalType, int $tier): bool
    {
        return DepartmentApprover::where('approver_employee_id', $employeeId)
            ->where('department_id', $departmentId)
            ->where('approval_type', $approvalType)
            ->where('tier', $tier)
            ->exists();
    }

    /**
     * Get the tier(s) where an employee is assigned as approver for a department + type.
     *
     * @return array<int, int>
     */
    public function getApproverTiers(int $employeeId, int $departmentId, string $approvalType): array
    {
        return DepartmentApprover::where('approver_employee_id', $employeeId)
            ->where('department_id', $departmentId)
            ->where('approval_type', $approvalType)
            ->pluck('tier')
            ->toArray();
    }

    /**
     * Process an approval action. Returns result indicating whether advanced or fully approved.
     *
     * @param  Model  $request  The approvable model (OvertimeRequest, LeaveRequest, etc.)
     * @param  Employee  $approver  The employee performing the action
     * @param  string  $approvalType  The type (overtime, leave, claims, exit_permission)
     * @param  int  $departmentId  The department of the request's employee
     * @param  string|null  $notes  Optional notes
     * @return array{advanced: bool, fully_approved: bool}
     */
    public function approve(Model $request, Employee $approver, string $approvalType, int $departmentId, ?string $notes = null): array
    {
        $currentTier = $request->current_approval_tier;
        $maxTier = $this->getMaxTier($departmentId, $approvalType);

        ApprovalLog::create([
            'approvable_type' => get_class($request),
            'approvable_id' => $request->id,
            'tier' => $currentTier,
            'approver_id' => $approver->id,
            'action' => 'approved',
            'notes' => $notes,
        ]);

        if ($currentTier >= $maxTier) {
            return ['advanced' => false, 'fully_approved' => true];
        }

        $request->update(['current_approval_tier' => $currentTier + 1]);

        return ['advanced' => true, 'fully_approved' => false];
    }

    /**
     * Process a rejection action. Immediately rejects the request.
     *
     * @param  Model  $request  The approvable model
     * @param  Employee  $approver  The employee performing the rejection
     * @param  string|null  $notes  Optional notes
     */
    public function reject(Model $request, Employee $approver, ?string $notes = null): void
    {
        ApprovalLog::create([
            'approvable_type' => get_class($request),
            'approvable_id' => $request->id,
            'tier' => $request->current_approval_tier,
            'approver_id' => $approver->id,
            'action' => 'rejected',
            'notes' => $notes,
        ]);
    }
}
