<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreLeaveEntitlementRequest;
use App\Models\LeaveEntitlement;
use App\Models\LeaveType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            ->get();

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
     * Recalculate all employees' balances based on entitlement rules for the current year.
     */
    public function recalculate(Request $request): JsonResponse
    {
        $year = $request->input('year', now()->year);

        $controller = app(HrLeaveBalanceController::class);

        $request->merge(['year' => (int) $year]);

        return $controller->initialize($request);
    }
}
