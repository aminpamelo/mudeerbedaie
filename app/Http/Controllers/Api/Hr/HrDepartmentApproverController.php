<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DepartmentApprover;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrDepartmentApproverController extends Controller
{
    /**
     * List all approver configurations grouped by department.
     */
    public function index(): JsonResponse
    {
        $approvers = DepartmentApprover::query()
            ->with(['department', 'approver'])
            ->orderBy('department_id')
            ->get();

        $grouped = $approvers->groupBy('department_id')->map(function ($items, $departmentId) {
            $first = $items->first();

            return [
                'id' => $departmentId,
                'department_id' => (int) $departmentId,
                'department' => $first->department,
                'ot_approvers' => $items->where('approval_type', 'overtime')->map(fn ($a) => $a->approver)->filter()->values(),
                'leave_approvers' => $items->where('approval_type', 'leave')->map(fn ($a) => $a->approver)->filter()->values(),
                'claims_approvers' => $items->where('approval_type', 'claims')->map(fn ($a) => $a->approver)->filter()->values(),
            ];
        })->values();

        return response()->json(['data' => $grouped]);
    }

    /**
     * Create or update department approver configuration.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'ot_approver_ids' => ['array'],
            'ot_approver_ids.*' => ['exists:employees,id'],
            'leave_approver_ids' => ['array'],
            'leave_approver_ids.*' => ['exists:employees,id'],
            'claims_approver_ids' => ['array'],
            'claims_approver_ids.*' => ['exists:employees,id'],
        ]);

        return DB::transaction(function () use ($validated) {
            $departmentId = $validated['department_id'];

            // Remove existing approvers for this department
            DepartmentApprover::where('department_id', $departmentId)->delete();

            // Insert new approvers
            $this->insertApprovers($departmentId, 'overtime', $validated['ot_approver_ids'] ?? []);
            $this->insertApprovers($departmentId, 'leave', $validated['leave_approver_ids'] ?? []);
            $this->insertApprovers($departmentId, 'claims', $validated['claims_approver_ids'] ?? []);

            return response()->json([
                'message' => 'Department approver configuration saved successfully.',
            ], 201);
        });
    }

    /**
     * Update department approver configuration.
     */
    public function update(Request $request, string $departmentApprover): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'ot_approver_ids' => ['array'],
            'ot_approver_ids.*' => ['exists:employees,id'],
            'leave_approver_ids' => ['array'],
            'leave_approver_ids.*' => ['exists:employees,id'],
            'claims_approver_ids' => ['array'],
            'claims_approver_ids.*' => ['exists:employees,id'],
        ]);

        return DB::transaction(function () use ($validated) {
            $departmentId = $validated['department_id'];

            // Remove existing approvers for this department
            DepartmentApprover::where('department_id', $departmentId)->delete();

            // Insert new approvers
            $this->insertApprovers($departmentId, 'overtime', $validated['ot_approver_ids'] ?? []);
            $this->insertApprovers($departmentId, 'leave', $validated['leave_approver_ids'] ?? []);
            $this->insertApprovers($departmentId, 'claims', $validated['claims_approver_ids'] ?? []);

            return response()->json([
                'message' => 'Department approver configuration updated successfully.',
            ]);
        });
    }

    /**
     * Delete all approver configurations for a department.
     */
    public function destroy(string $departmentApprover): JsonResponse
    {
        // $departmentApprover is the department_id used as the resource identifier
        DepartmentApprover::where('department_id', $departmentApprover)->delete();

        return response()->json(['message' => 'Department approver configuration deleted successfully.']);
    }

    /**
     * Insert approver records for a department and type.
     */
    private function insertApprovers(int $departmentId, string $type, array $employeeIds): void
    {
        foreach ($employeeIds as $employeeId) {
            DepartmentApprover::create([
                'department_id' => $departmentId,
                'approver_employee_id' => $employeeId,
                'approval_type' => $type,
            ]);
        }
    }
}
