<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreLeaveEntitlementRequest;
use App\Models\LeaveEntitlement;
use Illuminate\Http\JsonResponse;

class HrLeaveEntitlementController extends Controller
{
    /**
     * List all entitlements grouped by leave type.
     */
    public function index(): JsonResponse
    {
        $entitlements = LeaveEntitlement::query()
            ->with('leaveType')
            ->orderBy('leave_type_id')
            ->orderBy('min_service_months')
            ->get()
            ->groupBy('leave_type_id');

        return response()->json(['data' => $entitlements]);
    }

    /**
     * Create a new leave entitlement.
     */
    public function store(StoreLeaveEntitlementRequest $request): JsonResponse
    {
        $entitlement = LeaveEntitlement::create($request->validated());

        $entitlement->load('leaveType');

        return response()->json([
            'data' => $entitlement,
            'message' => 'Leave entitlement created successfully.',
        ], 201);
    }

    /**
     * Update a leave entitlement.
     */
    public function update(StoreLeaveEntitlementRequest $request, LeaveEntitlement $leaveEntitlement): JsonResponse
    {
        $leaveEntitlement->update($request->validated());

        return response()->json([
            'data' => $leaveEntitlement->fresh('leaveType'),
            'message' => 'Leave entitlement updated successfully.',
        ]);
    }

    /**
     * Delete a leave entitlement.
     */
    public function destroy(LeaveEntitlement $leaveEntitlement): JsonResponse
    {
        $leaveEntitlement->delete();

        return response()->json(['message' => 'Leave entitlement deleted successfully.']);
    }

    /**
     * Recalculate all employees' balances based on entitlement rules for a given year.
     */
    public function recalculate(): JsonResponse
    {
        return response()->json([
            'message' => 'Use the leave balance initialize endpoint to recalculate balances.',
        ]);
    }
}
