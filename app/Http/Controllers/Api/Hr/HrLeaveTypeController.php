<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreLeaveTypeRequest;
use App\Models\LeaveType;
use Illuminate\Http\JsonResponse;

class HrLeaveTypeController extends Controller
{
    /**
     * List all leave types.
     */
    public function index(): JsonResponse
    {
        $leaveTypes = LeaveType::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $leaveTypes]);
    }

    /**
     * Create a new leave type.
     */
    public function store(StoreLeaveTypeRequest $request): JsonResponse
    {
        $leaveType = LeaveType::create($request->validated());

        return response()->json([
            'data' => $leaveType,
            'message' => 'Leave type created successfully.',
        ], 201);
    }

    /**
     * Show a single leave type.
     */
    public function show(LeaveType $leaveType): JsonResponse
    {
        return response()->json(['data' => $leaveType]);
    }

    /**
     * Update a leave type.
     */
    public function update(StoreLeaveTypeRequest $request, LeaveType $leaveType): JsonResponse
    {
        $leaveType->update($request->validated());

        return response()->json([
            'data' => $leaveType->fresh(),
            'message' => 'Leave type updated successfully.',
        ]);
    }

    /**
     * Delete a leave type. Prevent deletion of system leave types.
     */
    public function destroy(LeaveType $leaveType): JsonResponse
    {
        if ($leaveType->is_system) {
            return response()->json([
                'message' => 'System leave types cannot be deleted.',
            ], 422);
        }

        $leaveType->delete();

        return response()->json(['message' => 'Leave type deleted successfully.']);
    }
}
