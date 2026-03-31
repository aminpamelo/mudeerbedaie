<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreVehicleRateRequest;
use App\Models\ClaimType;
use App\Models\ClaimTypeVehicleRate;
use Illuminate\Http\JsonResponse;

class HrVehicleRateController extends Controller
{
    public function index(ClaimType $type): JsonResponse
    {
        $rates = $type->vehicleRates()
            ->ordered()
            ->get();

        return response()->json(['data' => $rates]);
    }

    public function store(StoreVehicleRateRequest $request, ClaimType $type): JsonResponse
    {
        $rate = $type->vehicleRates()->create($request->validated());

        return response()->json([
            'data' => $rate,
            'message' => 'Vehicle rate created successfully.',
        ], 201);
    }

    public function update(StoreVehicleRateRequest $request, ClaimType $type, ClaimTypeVehicleRate $rate): JsonResponse
    {
        if ($rate->claim_type_id !== $type->id) {
            return response()->json(['message' => 'Vehicle rate does not belong to this claim type.'], 404);
        }

        $rate->update($request->validated());

        return response()->json([
            'data' => $rate->fresh(),
            'message' => 'Vehicle rate updated successfully.',
        ]);
    }

    public function destroy(ClaimType $type, ClaimTypeVehicleRate $rate): JsonResponse
    {
        if ($rate->claim_type_id !== $type->id) {
            return response()->json(['message' => 'Vehicle rate does not belong to this claim type.'], 404);
        }

        $rate->delete();

        return response()->json(['message' => 'Vehicle rate deleted successfully.']);
    }
}
