<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreBenefitTypeRequest;
use App\Models\BenefitType;
use Illuminate\Http\JsonResponse;

class HrBenefitTypeController extends Controller
{
    /**
     * List all benefit types.
     */
    public function index(): JsonResponse
    {
        $benefitTypes = BenefitType::query()
            ->ordered()
            ->get();

        return response()->json(['data' => $benefitTypes]);
    }

    /**
     * Create a new benefit type.
     */
    public function store(StoreBenefitTypeRequest $request): JsonResponse
    {
        $benefitType = BenefitType::create($request->validated());

        return response()->json([
            'data' => $benefitType,
            'message' => 'Benefit type created successfully.',
        ], 201);
    }

    /**
     * Update a benefit type.
     */
    public function update(StoreBenefitTypeRequest $request, BenefitType $type): JsonResponse
    {
        $type->update($request->validated());

        return response()->json([
            'data' => $type->fresh(),
            'message' => 'Benefit type updated successfully.',
        ]);
    }

    /**
     * Delete a benefit type.
     */
    public function destroy(BenefitType $type): JsonResponse
    {
        if ($type->employeeBenefits()->exists()) {
            return response()->json([
                'message' => 'Cannot delete benefit type that has existing employee benefits.',
            ], 422);
        }

        $type->delete();

        return response()->json(['message' => 'Benefit type deleted successfully.']);
    }
}
