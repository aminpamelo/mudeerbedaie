<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\ClaimApprover;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrClaimApproverController extends Controller
{
    /**
     * List all claim approvers.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ClaimApprover::query()
            ->with(['employee', 'approver']);

        if ($employeeId = $request->get('employee_id')) {
            $query->where('employee_id', $employeeId);
        }

        if ($request->boolean('active_only', false)) {
            $query->active();
        }

        $approvers = $query->get();

        return response()->json(['data' => $approvers]);
    }

    /**
     * Assign a claim approver.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'approver_id' => ['required', 'exists:employees,id', 'different:employee_id'],
            'is_active' => ['boolean'],
        ]);

        $approver = ClaimApprover::create($validated);
        $approver->load(['employee', 'approver']);

        return response()->json([
            'data' => $approver,
            'message' => 'Claim approver assigned successfully.',
        ], 201);
    }

    /**
     * Remove a claim approver assignment.
     */
    public function destroy(ClaimApprover $claimApprover): JsonResponse
    {
        $claimApprover->delete();

        return response()->json(['message' => 'Claim approver removed successfully.']);
    }
}
