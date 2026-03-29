<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\TrainingCost;
use App\Models\TrainingProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrTrainingCostController extends Controller
{
    public function index(TrainingProgram $trainingProgram): JsonResponse
    {
        return response()->json([
            'data' => $trainingProgram->costs,
        ]);
    }

    public function store(Request $request, TrainingProgram $trainingProgram): JsonResponse
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'receipt_path' => ['nullable', 'string'],
        ]);

        $cost = $trainingProgram->costs()->create($validated);

        return response()->json([
            'message' => 'Cost added.',
            'data' => $cost,
        ], 201);
    }

    public function update(Request $request, TrainingCost $trainingCost): JsonResponse
    {
        $validated = $request->validate([
            'description' => ['sometimes', 'string', 'max:255'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'receipt_path' => ['nullable', 'string'],
        ]);

        $trainingCost->update($validated);

        return response()->json([
            'message' => 'Cost updated.',
            'data' => $trainingCost,
        ]);
    }

    public function destroy(TrainingCost $trainingCost): JsonResponse
    {
        $trainingCost->delete();

        return response()->json(['message' => 'Cost deleted.']);
    }
}
