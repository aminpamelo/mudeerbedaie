<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreAdminClaimRequestRequest;
use App\Models\ClaimApprover;
use App\Models\ClaimRequest;
use App\Models\ClaimType;
use App\Models\ClaimTypeVehicleRate;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\Hr\ClaimApproved;
use App\Notifications\Hr\ClaimMarkedPaid;
use App\Notifications\Hr\ClaimRejected;
use App\Notifications\Hr\ClaimSubmitted;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrClaimRequestController extends Controller
{
    /**
     * List all claim requests with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ClaimRequest::query()
            ->with(['employee.department', 'claimType', 'vehicleRate']);

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

        $perPage = (int) $request->get('per_page', 15);
        $perPage = max(1, min($perPage, 200));

        $requests = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json($requests);
    }

    /**
     * Mark all approved claims for a given employee as paid in one bulk transfer.
     */
    public function payAllByEmployee(Request $request, Employee $employee): JsonResponse
    {
        $validated = $request->validate([
            'paid_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $reference = $validated['paid_reference'] ?? null;

        $approvedClaims = ClaimRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->get();

        if ($approvedClaims->isEmpty()) {
            return response()->json([
                'count' => 0,
                'total' => 0,
                'employee_id' => $employee->id,
                'message' => 'No approved claims to pay for this employee.',
            ]);
        }

        $total = (float) $approvedClaims->sum(function (ClaimRequest $claim) {
            return (float) ($claim->approved_amount ?? $claim->amount);
        });

        DB::transaction(function () use ($approvedClaims, $reference) {
            foreach ($approvedClaims as $claim) {
                $claim->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'paid_reference' => $reference,
                ]);
            }
        });

        $employee->load('user');
        if ($employee->user) {
            foreach ($approvedClaims as $claim) {
                $claim->load('claimType');
                $employee->user->notify(new ClaimMarkedPaid($claim->fresh()));
            }
        }

        return response()->json([
            'count' => $approvedClaims->count(),
            'total' => $total,
            'employee_id' => $employee->id,
            'message' => 'All approved claims marked as paid.',
        ]);
    }

    /**
     * Create a claim request on behalf of an employee (admin only).
     *
     * Saves with status = pending and dispatches the standard ClaimSubmitted
     * notifications to the employee's claim approvers and admin users
     * (excluding the creating admin).
     */
    public function store(StoreAdminClaimRequestRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $actingUserId = $request->user()->id;

        return DB::transaction(function () use ($request, $validated, $actingUserId) {
            $employee = Employee::findOrFail($validated['employee_id']);
            $claimType = ClaimType::findOrFail($validated['claim_type_id']);

            $amount = $validated['amount'] ?? null;
            if ($claimType->is_mileage_type) {
                $vehicleRate = ClaimTypeVehicleRate::where('id', $validated['vehicle_rate_id'])
                    ->where('claim_type_id', $claimType->id)
                    ->where('is_active', true)
                    ->firstOrFail();

                $amount = round($validated['distance_km'] * $vehicleRate->rate_per_km, 2);
            }

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

            $warning = null;
            if ($claimType->monthly_limit && ($monthlyUsed + $amount) > $claimType->monthly_limit) {
                $warning = 'This claim exceeds the monthly limit of RM'.number_format($claimType->monthly_limit, 2).'.';
            } elseif ($claimType->yearly_limit && ($yearlyUsed + $amount) > $claimType->yearly_limit) {
                $warning = 'This claim exceeds the yearly limit of RM'.number_format($claimType->yearly_limit, 2).'.';
            }

            $receiptPath = null;
            if ($request->hasFile('receipt')) {
                $receiptPath = $request->file('receipt')->store("claim-receipts/{$employee->id}", 'public');
            }

            $claim = ClaimRequest::create([
                'claim_number' => ClaimRequest::generateClaimNumber(),
                'employee_id' => $employee->id,
                'claim_type_id' => $claimType->id,
                'amount' => $amount,
                'claim_date' => $validated['claim_date'],
                'description' => $validated['description'],
                'receipt_path' => $receiptPath,
                'status' => 'pending',
                'submitted_at' => now(),
                'vehicle_rate_id' => $validated['vehicle_rate_id'] ?? null,
                'distance_km' => $validated['distance_km'] ?? null,
                'origin' => $validated['origin'] ?? null,
                'destination' => $validated['destination'] ?? null,
                'trip_purpose' => $validated['trip_purpose'] ?? null,
            ]);

            $claim->load('employee.department', 'claimType', 'vehicleRate');

            $notifiedUserIds = [$actingUserId];

            $approvers = ClaimApprover::where('employee_id', $employee->id)
                ->active()
                ->with('approver.user')
                ->get();

            foreach ($approvers as $claimApprover) {
                $approverUser = $claimApprover->approver?->user;
                if ($approverUser && ! in_array($approverUser->id, $notifiedUserIds, true)) {
                    $approverUser->notify(new ClaimSubmitted($claim));
                    $notifiedUserIds[] = $approverUser->id;
                }
            }

            $admins = User::where('role', 'admin')
                ->whereNotIn('id', $notifiedUserIds)
                ->get();
            foreach ($admins as $admin) {
                $admin->notify(new ClaimSubmitted($claim));
            }

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
     * Show a single claim request.
     */
    public function show(ClaimRequest $claimRequest): JsonResponse
    {
        $claimRequest->load(['employee.department', 'claimType', 'vehicleRate']);

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

        $approver = Employee::where('user_id', $request->user()->id)->first();

        $claimRequest->update([
            'status' => 'approved',
            'approved_amount' => $validated['approved_amount'],
            'approved_by' => $approver?->id,
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
