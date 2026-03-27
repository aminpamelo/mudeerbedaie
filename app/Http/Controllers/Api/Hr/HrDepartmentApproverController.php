<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreDepartmentApproverRequest;
use App\Models\DepartmentApprover;
use Illuminate\Http\JsonResponse;

class HrDepartmentApproverController extends Controller
{
    /**
     * List all approvers grouped by department.
     */
    public function index(): JsonResponse
    {
        $approvers = DepartmentApprover::query()
            ->with(['department', 'approver'])
            ->orderBy('department_id')
            ->get()
            ->groupBy('department_id');

        return response()->json(['data' => $approvers]);
    }

    /**
     * Create a new department approver.
     */
    public function store(StoreDepartmentApproverRequest $request): JsonResponse
    {
        $approver = DepartmentApprover::create($request->validated());

        $approver->load(['department', 'approver']);

        return response()->json([
            'data' => $approver,
            'message' => 'Department approver created successfully.',
        ], 201);
    }

    /**
     * Update a department approver.
     */
    public function update(StoreDepartmentApproverRequest $request, DepartmentApprover $departmentApprover): JsonResponse
    {
        $departmentApprover->update($request->validated());

        return response()->json([
            'data' => $departmentApprover->fresh(['department', 'approver']),
            'message' => 'Department approver updated successfully.',
        ]);
    }

    /**
     * Delete a department approver.
     */
    public function destroy(DepartmentApprover $departmentApprover): JsonResponse
    {
        $departmentApprover->delete();

        return response()->json(['message' => 'Department approver deleted successfully.']);
    }
}
