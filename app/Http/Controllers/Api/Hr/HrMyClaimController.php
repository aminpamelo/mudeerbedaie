<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreClaimRequestRequest;
use App\Models\ClaimApprover;
use App\Models\ClaimRequest;
use App\Models\ClaimType;
use App\Notifications\Hr\ClaimSubmitted;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrMyClaimController extends Controller
{
    /**
     * My claim usage limits per claim type.
     */
    public function limits(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $claimTypes = ClaimType::where('is_active', true)->get();

        $limits = $claimTypes->map(function ($type) use ($employee) {
            $monthlyUsed = ClaimRequest::query()
                ->where('employee_id', $employee->id)
                ->where('claim_type_id', $type->id)
                ->whereIn('status', ['pending', 'approved', 'paid'])
                ->whereYear('claim_date', now()->year)
                ->whereMonth('claim_date', now()->month)
                ->sum('amount');

            return [
                'claim_type_id' => $type->id,
                'name' => $type->name,
                'monthly_limit' => $type->monthly_limit ? (float) $type->monthly_limit : null,
                'yearly_limit' => $type->yearly_limit ? (float) $type->yearly_limit : null,
                'used_this_month' => (float) $monthlyUsed,
            ];
        });

        return response()->json(['data' => $limits]);
    }

    /**
     * List the current employee's claim requests.
     */
    public function index(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $query = ClaimRequest::query()
            ->with('claimType')
            ->where('employee_id', $employee->id);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $claims = $query->orderByDesc('created_at')->paginate(15);

        return response()->json($claims);
    }

    /**
     * Show a single claim request (must belong to current employee).
     */
    public function show(Request $request, ClaimRequest $claimRequest): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee || $claimRequest->employee_id !== $employee->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $claimRequest->load('claimType');

        return response()->json(['data' => $claimRequest]);
    }

    /**
     * Submit a new claim request.
     */
    public function store(StoreClaimRequestRequest $request): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee) {
            return response()->json(['message' => 'Employee record not found.'], 404);
        }

        $validated = $request->validated();

        return DB::transaction(function () use ($request, $employee, $validated) {
            $claimType = ClaimType::findOrFail($validated['claim_type_id']);

            $warning = null;

            $monthlyUsed = ClaimRequest::query()
                ->where('employee_id', $employee->id)
                ->where('claim_type_id', $claimType->id)
                ->whereIn('status', ['pending', 'approved', 'paid'])
                ->whereYear('claim_date', now()->year)
                ->whereMonth('claim_date', now()->month)
                ->sum('amount');

            $yearlyUsed = ClaimRequest::query()
                ->where('employee_id', $employee->id)
                ->where('claim_type_id', $claimType->id)
                ->whereIn('status', ['pending', 'approved', 'paid'])
                ->whereYear('claim_date', now()->year)
                ->sum('amount');

            if ($claimType->monthly_limit && ($monthlyUsed + $validated['amount']) > $claimType->monthly_limit) {
                $warning = 'This claim exceeds the monthly limit of RM'.number_format($claimType->monthly_limit, 2).'.';
            } elseif ($claimType->yearly_limit && ($yearlyUsed + $validated['amount']) > $claimType->yearly_limit) {
                $warning = 'This claim exceeds the yearly limit of RM'.number_format($claimType->yearly_limit, 2).'.';
            }

            $receiptPath = null;
            if ($request->hasFile('receipt')) {
                $receiptPath = $request->file('receipt')->store("claim-receipts/{$employee->id}", 'public');
            }

            $claim = ClaimRequest::create([
                'claim_number' => ClaimRequest::generateClaimNumber(),
                'employee_id' => $employee->id,
                'claim_type_id' => $validated['claim_type_id'],
                'amount' => $validated['amount'],
                'claim_date' => $validated['claim_date'],
                'description' => $validated['description'],
                'receipt_path' => $receiptPath,
                'status' => 'draft',
            ]);

            $claim->load('claimType');

            $response = [
                'data' => $claim,
                'message' => 'Claim request created successfully.',
            ];

            if ($warning) {
                $response['warning'] = $warning;
            }

            return response()->json($response, 201);
        });
    }

    /**
     * Update a draft claim request.
     */
    public function update(StoreClaimRequestRequest $request, ClaimRequest $claimRequest): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee || $claimRequest->employee_id !== $employee->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($claimRequest->status !== 'draft') {
            return response()->json(['message' => 'Only draft claims can be updated.'], 422);
        }

        $validated = $request->validated();

        if ($request->hasFile('receipt')) {
            $validated['receipt_path'] = $request->file('receipt')->store("claim-receipts/{$employee->id}", 'public');
        }

        unset($validated['receipt']);

        $claimRequest->update($validated);

        return response()->json([
            'data' => $claimRequest->fresh('claimType'),
            'message' => 'Claim request updated successfully.',
        ]);
    }

    /**
     * Submit a draft claim for approval.
     */
    public function submit(Request $request, ClaimRequest $claimRequest): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee || $claimRequest->employee_id !== $employee->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($claimRequest->status !== 'draft') {
            return response()->json(['message' => 'Only draft claims can be submitted.'], 422);
        }

        $claimRequest->update([
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        // Notify claim approvers and admin users
        $claimRequest->load('employee', 'claimType');
        $notifiedUserIds = [];

        $approvers = ClaimApprover::where('employee_id', $employee->id)
            ->active()
            ->with('approver.user')
            ->get();

        foreach ($approvers as $claimApprover) {
            if ($claimApprover->approver?->user) {
                $claimApprover->approver->user->notify(
                    new ClaimSubmitted($claimRequest)
                );
                $notifiedUserIds[] = $claimApprover->approver->user->id;
            }
        }

        // Also notify admin users who weren't already notified as approvers
        $admins = \App\Models\User::where('role', 'admin')
            ->whereNotIn('id', $notifiedUserIds)
            ->get();
        foreach ($admins as $admin) {
            $admin->notify(new ClaimSubmitted($claimRequest));
        }

        return response()->json([
            'data' => $claimRequest->fresh('claimType'),
            'message' => 'Claim request submitted for approval.',
        ]);
    }

    /**
     * Delete a draft claim request.
     */
    public function destroy(Request $request, ClaimRequest $claimRequest): JsonResponse
    {
        $employee = $request->user()->employee;

        if (! $employee || $claimRequest->employee_id !== $employee->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($claimRequest->status !== 'draft') {
            return response()->json(['message' => 'Only draft claims can be deleted.'], 422);
        }

        $claimRequest->delete();

        return response()->json(['message' => 'Claim request deleted successfully.']);
    }
}
