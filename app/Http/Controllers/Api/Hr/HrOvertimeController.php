<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\OvertimeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrOvertimeController extends Controller
{
    /**
     * List all overtime requests with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = OvertimeRequest::query()
            ->with(['employee.department']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($departmentId = $request->get('department_id')) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId));
        }

        if ($dateFrom = $request->get('date_from')) {
            $query->whereDate('requested_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->get('date_to')) {
            $query->whereDate('requested_date', '<=', $dateTo);
        }

        $requests = $query->orderByDesc('requested_date')->paginate(15);

        return response()->json($requests);
    }

    /**
     * Show a single overtime request with employee.
     */
    public function show(OvertimeRequest $overtimeRequest): JsonResponse
    {
        $overtimeRequest->load('employee.department');

        return response()->json(['data' => $overtimeRequest]);
    }

    /**
     * Approve an overtime request.
     */
    public function approve(Request $request, OvertimeRequest $overtimeRequest): JsonResponse
    {
        if ($overtimeRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be approved.'], 422);
        }

        $overtimeRequest->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json([
            'data' => $overtimeRequest->fresh('employee'),
            'message' => 'Overtime request approved successfully.',
        ]);
    }

    /**
     * Reject an overtime request.
     */
    public function reject(Request $request, OvertimeRequest $overtimeRequest): JsonResponse
    {
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

        return response()->json([
            'data' => $overtimeRequest->fresh('employee'),
            'message' => 'Overtime request rejected.',
        ]);
    }

    /**
     * Complete an overtime request with actual hours.
     */
    public function complete(Request $request, OvertimeRequest $overtimeRequest): JsonResponse
    {
        if ($overtimeRequest->status !== 'approved') {
            return response()->json(['message' => 'Only approved requests can be completed.'], 422);
        }

        $validated = $request->validate([
            'actual_hours' => ['required', 'numeric', 'min:0.5', 'max:24'],
        ]);

        $overtimeRequest->update([
            'status' => 'completed',
            'actual_hours' => $validated['actual_hours'],
            'replacement_hours_earned' => $validated['actual_hours'],
        ]);

        return response()->json([
            'data' => $overtimeRequest->fresh('employee'),
            'message' => 'Overtime request completed successfully.',
        ]);
    }
}
