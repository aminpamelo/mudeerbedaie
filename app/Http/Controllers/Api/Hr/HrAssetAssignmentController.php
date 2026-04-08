<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreAssetAssignmentRequest;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\User;
use App\Notifications\Hr\AssetAssigned;
use App\Notifications\Hr\AssetReturnConfirmed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrAssetAssignmentController extends Controller
{
    /**
     * List all asset assignments with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AssetAssignment::query()
            ->with(['asset.category', 'employee', 'assignedBy']);

        if ($employeeId = $request->get('employee_id')) {
            $query->where('employee_id', $employeeId);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($categoryId = $request->get('category_id')) {
            $query->whereHas('asset', fn ($q) => $q->where('asset_category_id', $categoryId));
        }

        $assignments = $query->orderByDesc('assigned_date')->paginate(15);

        return response()->json($assignments);
    }

    /**
     * Assign an asset to an employee.
     */
    public function store(StoreAssetAssignmentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated) {
            $asset = Asset::findOrFail($validated['asset_id']);

            if ($asset->status !== 'available') {
                return response()->json([
                    'message' => 'Asset is not available for assignment.',
                ], 422);
            }

            $assignment = AssetAssignment::create([
                'asset_id' => $validated['asset_id'],
                'employee_id' => $validated['employee_id'],
                'assigned_by' => $validated['assigned_by'],
                'assigned_date' => $validated['assigned_date'],
                'expected_return_date' => $validated['expected_return_date'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => 'active',
            ]);

            $asset->update(['status' => 'assigned']);

            $assignment->load(['asset.category', 'employee.user', 'assignedBy']);

            if ($assignment->employee?->user) {
                $assignment->employee->user->notify(
                    new AssetAssigned($assignment)
                );
            }

            return response()->json([
                'data' => $assignment,
                'message' => 'Asset assigned successfully.',
            ], 201);
        });
    }

    /**
     * Process the return of an assigned asset.
     */
    public function returnAsset(Request $request, AssetAssignment $assetAssignment): JsonResponse
    {
        if ($assetAssignment->status !== 'active') {
            return response()->json(['message' => 'This assignment is not active.'], 422);
        }

        $validated = $request->validate([
            'returned_condition' => ['required', 'in:new,good,fair,poor,damaged'],
            'return_notes' => ['nullable', 'string'],
        ]);

        $assetAssignment->processReturn(
            $validated['returned_condition'],
            $validated['return_notes'] ?? null
        );

        $assetAssignment->load('employee', 'asset');
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new AssetReturnConfirmed($assetAssignment));
        }

        return response()->json([
            'data' => $assetAssignment->fresh(['asset.category', 'employee']),
            'message' => 'Asset returned successfully.',
        ]);
    }
}
