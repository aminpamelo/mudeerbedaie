<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\ClaimRequest;
use App\Notifications\Hr\ClaimApproved;
use App\Notifications\Hr\ClaimMarkedPaid;
use App\Notifications\Hr\ClaimRejected;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrClaimRequestController extends Controller
{
    /**
     * List all claim requests with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ClaimRequest::query()
            ->with(['employee.department', 'claimType']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($claimTypeId = $request->get('claim_type_id')) {
            $query->where('claim_type_id', $claimTypeId);
        }

        if ($employeeId = $request->get('employee_id')) {
            $query->where('employee_id', $employeeId);
        }

        if ($dateFrom = $request->get('date_from')) {
            $query->whereDate('claim_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->get('date_to')) {
            $query->whereDate('claim_date', '<=', $dateTo);
        }

        $requests = $query->orderByDesc('created_at')->paginate(15);

        return response()->json($requests);
    }

    /**
     * Show a single claim request.
     */
    public function show(ClaimRequest $claimRequest): JsonResponse
    {
        $claimRequest->load(['employee.department', 'claimType']);

        return response()->json(['data' => $claimRequest]);
    }

    /**
     * Approve a claim request.
     */
    public function approve(Request $request, ClaimRequest $claimRequest): JsonResponse
    {
        if ($claimRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be approved.'], 422);
        }

        $validated = $request->validate([
            'approved_amount' => ['required', 'numeric', 'min:0.01'],
            'remarks' => ['nullable', 'string'],
        ]);

        $claimRequest->update([
            'status' => 'approved',
            'approved_amount' => $validated['approved_amount'],
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        $claimRequest->load('employee.user', 'claimType');
        if ($claimRequest->employee?->user) {
            $claimRequest->employee->user->notify(
                new ClaimApproved($claimRequest)
            );
        }

        return response()->json([
            'data' => $claimRequest->fresh(['employee', 'claimType']),
            'message' => 'Claim request approved successfully.',
        ]);
    }

    /**
     * Reject a claim request.
     */
    public function reject(Request $request, ClaimRequest $claimRequest): JsonResponse
    {
        if ($claimRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be rejected.'], 422);
        }

        $validated = $request->validate([
            'rejected_reason' => ['required', 'string', 'min:5'],
        ]);

        $claimRequest->update([
            'status' => 'rejected',
            'rejected_reason' => $validated['rejected_reason'],
        ]);

        $claimRequest->load('employee.user', 'claimType');
        if ($claimRequest->employee?->user) {
            $claimRequest->employee->user->notify(
                new ClaimRejected($claimRequest)
            );
        }

        return response()->json([
            'data' => $claimRequest->fresh(['employee', 'claimType']),
            'message' => 'Claim request rejected.',
        ]);
    }

    /**
     * Mark a claim as paid.
     */
    public function markPaid(Request $request, ClaimRequest $claimRequest): JsonResponse
    {
        if ($claimRequest->status !== 'approved') {
            return response()->json(['message' => 'Only approved claims can be marked as paid.'], 422);
        }

        $validated = $request->validate([
            'paid_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $claimRequest->update([
            'status' => 'paid',
            'paid_at' => now(),
            'paid_reference' => $validated['paid_reference'] ?? null,
        ]);

        $claimRequest->load('employee.user', 'claimType');
        if ($claimRequest->employee?->user) {
            $claimRequest->employee->user->notify(
                new ClaimMarkedPaid($claimRequest)
            );
        }

        return response()->json([
            'data' => $claimRequest->fresh(['employee', 'claimType']),
            'message' => 'Claim marked as paid.',
        ]);
    }
}
