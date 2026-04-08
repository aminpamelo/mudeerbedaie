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
            ->orderBy('tier')
            ->get();

        $grouped = $approvers->groupBy('department_id')->map(function ($items, $departmentId) {
            $first = $items->first();

            $groupByType = function ($type) use ($items) {
                return $items->where('approval_type', $type)
                    ->groupBy('tier')
                    ->map(fn ($tierItems) => $tierItems->map(fn ($a) => $a->approver)->filter()->values())
                    ->toArray();
            };

            return [
                'id' => $departmentId,
                'department_id' => (int) $departmentId,
                'department' => $first->department,
                'ot_approvers' => $groupByType('overtime'),
                'leave_approvers' => $groupByType('leave'),
                'claims_approvers' => $groupByType('claims'),
                'exit_permission_approvers' => $groupByType('exit_permission'),
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
            'ot_approvers' => ['array'],
            'ot_approvers.*.tier' => ['required', 'integer', 'min:1'],
            'ot_approvers.*.employee_ids' => ['required', 'array'],
            'ot_approvers.*.employee_ids.*' => ['exists:employees,id'],
            'leave_approvers' => ['array'],
            'leave_approvers.*.tier' => ['required', 'integer', 'min:1'],
            'leave_approvers.*.employee_ids' => ['required', 'array'],
            'leave_approvers.*.employee_ids.*' => ['exists:employees,id'],
            'claims_approvers' => ['array'],
            'claims_approvers.*.tier' => ['required', 'integer', 'min:1'],
            'claims_approvers.*.employee_ids' => ['required', 'array'],
            'claims_approvers.*.employee_ids.*' => ['exists:employees,id'],
            'exit_permission_approvers' => ['array'],
            'exit_permission_approvers.*.tier' => ['required', 'integer', 'min:1'],
            'exit_permission_approvers.*.employee_ids' => ['required', 'array'],
            'exit_permission_approvers.*.employee_ids.*' => ['exists:employees,id'],
        ]);

        return DB::transaction(function () use ($validated) {
            $departmentId = $validated['department_id'];

            // Remove existing approvers for this department
            DepartmentApprover::where('department_id', $departmentId)->delete();

            // Insert new tiered approvers
            $this->insertTieredApprovers($departmentId, 'overtime', $validated['ot_approvers'] ?? []);
            $this->insertTieredApprovers($departmentId, 'leave', $validated['leave_approvers'] ?? []);
            $this->insertTieredApprovers($departmentId, 'claims', $validated['claims_approvers'] ?? []);
            $this->insertTieredApprovers($departmentId, 'exit_permission', $validated['exit_permission_approvers'] ?? []);

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
            'ot_approvers' => ['array'],
            'ot_approvers.*.tier' => ['required', 'integer', 'min:1'],
            'ot_approvers.*.employee_ids' => ['required', 'array'],
            'ot_approvers.*.employee_ids.*' => ['exists:employees,id'],
            'leave_approvers' => ['array'],
            'leave_approvers.*.tier' => ['required', 'integer', 'min:1'],
            'leave_approvers.*.employee_ids' => ['required', 'array'],
            'leave_approvers.*.employee_ids.*' => ['exists:employees,id'],
            'claims_approvers' => ['array'],
            'claims_approvers.*.tier' => ['required', 'integer', 'min:1'],
            'claims_approvers.*.employee_ids' => ['required', 'array'],
            'claims_approvers.*.employee_ids.*' => ['exists:employees,id'],
            'exit_permission_approvers' => ['array'],
            'exit_permission_approvers.*.tier' => ['required', 'integer', 'min:1'],
            'exit_permission_approvers.*.employee_ids' => ['required', 'array'],
            'exit_permission_approvers.*.employee_ids.*' => ['exists:employees,id'],
        ]);

        return DB::transaction(function () use ($validated) {
            $departmentId = $validated['department_id'];

            // Remove existing approvers for this department
            DepartmentApprover::where('department_id', $departmentId)->delete();

            // Insert new tiered approvers
            $this->insertTieredApprovers($departmentId, 'overtime', $validated['ot_approvers'] ?? []);
            $this->insertTieredApprovers($departmentId, 'leave', $validated['leave_approvers'] ?? []);
            $this->insertTieredApprovers($departmentId, 'claims', $validated['claims_approvers'] ?? []);
            $this->insertTieredApprovers($departmentId, 'exit_permission', $validated['exit_permission_approvers'] ?? []);

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
     * Insert tiered approver records for a department and type.
     */
    private function insertTieredApprovers(int $departmentId, string $type, array $tiers): void
    {
        foreach ($tiers as $tierData) {
            $tier = $tierData['tier'];
            foreach ($tierData['employee_ids'] as $employeeId) {
                DepartmentApprover::create([
                    'department_id' => $departmentId,
                    'approver_employee_id' => $employeeId,
                    'approval_type' => $type,
                    'tier' => $tier,
                ]);
            }
        }
    }
}
