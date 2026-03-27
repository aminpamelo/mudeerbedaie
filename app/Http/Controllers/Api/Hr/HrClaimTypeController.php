<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreClaimTypeRequest;
use App\Models\ClaimType;
use Illuminate\Http\JsonResponse;

class HrClaimTypeController extends Controller
{
    /**
     * List all claim types.
     */
    public function index(): JsonResponse
    {
        $claimTypes = ClaimType::query()
            ->ordered()
            ->get();

        return response()->json(['data' => $claimTypes]);
    }

    /**
     * Create a new claim type.
     */
    public function store(StoreClaimTypeRequest $request): JsonResponse
    {
        $claimType = ClaimType::create($request->validated());

        return response()->json([
            'data' => $claimType,
            'message' => 'Claim type created successfully.',
        ], 201);
    }

    /**
     * Update a claim type.
     */
    public function update(StoreClaimTypeRequest $request, ClaimType $type): JsonResponse
    {
        $type->update($request->validated());

        return response()->json([
            'data' => $type->fresh(),
            'message' => 'Claim type updated successfully.',
        ]);
    }

    /**
     * Delete a claim type.
     */
    public function destroy(ClaimType $type): JsonResponse
    {
        if ($type->claimRequests()->exists()) {
            return response()->json([
                'message' => 'Cannot delete claim type that has existing claim requests.',
            ], 422);
        }

        $type->delete();

        return response()->json(['message' => 'Claim type deleted successfully.']);
    }
}
