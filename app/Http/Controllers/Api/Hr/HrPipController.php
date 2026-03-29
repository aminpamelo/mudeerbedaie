<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PerformanceImprovementPlan;
use App\Models\PipGoal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrPipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PerformanceImprovementPlan::query()
            ->with(['employee:id,full_name,employee_id', 'initiator:id,full_name'])
            ->withCount('goals');

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $pips = $query->orderByDesc('created_at')->paginate($request->get('per_page', 15));

        return response()->json($pips);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'performance_review_id' => ['nullable', 'exists:performance_reviews,id'],
            'reason' => ['required', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'goals' => ['required', 'array', 'min:1'],
            'goals.*.title' => ['required', 'string', 'max:255'],
            'goals.*.description' => ['nullable', 'string'],
            'goals.*.target_date' => ['required', 'date'],
        ]);

        return DB::transaction(function () use ($validated, $request) {
            // Find the initiator employee record
            $initiator = Employee::where('user_id', $request->user()->id)->first();

            $pip = PerformanceImprovementPlan::create([
                'employee_id' => $validated['employee_id'],
                'initiated_by' => $initiator?->id ?? $validated['employee_id'],
                'performance_review_id' => $validated['performance_review_id'] ?? null,
                'reason' => $validated['reason'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
            ]);

            foreach ($validated['goals'] as $goal) {
                PipGoal::create(array_merge($goal, ['pip_id' => $pip->id]));
            }

            return response()->json([
                'message' => 'PIP created.',
                'data' => $pip->load('goals'),
            ], 201);
        });
    }

    public function show(PerformanceImprovementPlan $pip): JsonResponse
    {
        return response()->json([
            'data' => $pip->load([
                'employee:id,full_name,employee_id,department_id',
                'employee.department:id,name',
                'initiator:id,full_name',
                'performanceReview',
                'goals',
            ]),
        ]);
    }

    public function update(Request $request, PerformanceImprovementPlan $pip): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['sometimes', 'string'],
            'end_date' => ['sometimes', 'date'],
        ]);

        $pip->update($validated);

        return response()->json([
            'message' => 'PIP updated.',
            'data' => $pip,
        ]);
    }

    public function extend(Request $request, PerformanceImprovementPlan $pip): JsonResponse
    {
        $validated = $request->validate([
            'end_date' => ['required', 'date', 'after:today'],
        ]);

        $pip->update([
            'end_date' => $validated['end_date'],
            'status' => 'extended',
        ]);

        return response()->json([
            'message' => 'PIP extended.',
            'data' => $pip,
        ]);
    }

    public function complete(Request $request, PerformanceImprovementPlan $pip): JsonResponse
    {
        $validated = $request->validate([
            'outcome' => ['required', 'in:completed_improved,completed_not_improved'],
            'outcome_notes' => ['nullable', 'string'],
        ]);

        $pip->update([
            'status' => $validated['outcome'],
            'outcome_notes' => $validated['outcome_notes'],
            'completed_at' => now(),
        ]);

        return response()->json([
            'message' => 'PIP completed.',
            'data' => $pip,
        ]);
    }

    public function addGoal(Request $request, PerformanceImprovementPlan $pip): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'target_date' => ['required', 'date'],
        ]);

        $goal = PipGoal::create(array_merge($validated, ['pip_id' => $pip->id]));

        return response()->json([
            'message' => 'PIP goal added.',
            'data' => $goal,
        ], 201);
    }

    public function updateGoal(Request $request, PerformanceImprovementPlan $pip, PipGoal $goal): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'in:pending,in_progress,achieved,not_achieved'],
            'check_in_notes' => ['nullable', 'string'],
        ]);

        if (isset($validated['check_in_notes'])) {
            $validated['checked_at'] = now();
        }

        $goal->update($validated);

        return response()->json([
            'message' => 'PIP goal updated.',
            'data' => $goal,
        ]);
    }
}
