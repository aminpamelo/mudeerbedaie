<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\TrainingBudget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrTrainingBudgetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $year = $request->get('year', now()->year);

        $budgets = TrainingBudget::where('year', $year)
            ->with('department:id,name')
            ->get()
            ->map(fn ($b) => array_merge($b->toArray(), [
                'utilization_percentage' => $b->utilization_percentage,
            ]));

        return response()->json(['data' => $budgets]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'year' => ['required', 'integer', 'min:2020', 'max:2050'],
            'allocated_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $budget = TrainingBudget::updateOrCreate(
            ['department_id' => $validated['department_id'], 'year' => $validated['year']],
            ['allocated_amount' => $validated['allocated_amount']]
        );

        return response()->json([
            'message' => 'Budget set.',
            'data' => $budget->load('department:id,name'),
        ], 201);
    }

    public function update(Request $request, TrainingBudget $trainingBudget): JsonResponse
    {
        $validated = $request->validate([
            'allocated_amount' => ['sometimes', 'numeric', 'min:0'],
            'spent_amount' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $trainingBudget->update($validated);

        return response()->json([
            'message' => 'Budget updated.',
            'data' => $trainingBudget,
        ]);
    }
}
