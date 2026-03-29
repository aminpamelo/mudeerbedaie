<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ExitInterview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrExitInterviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $interviews = ExitInterview::query()
            ->with(['employee:id,full_name,employee_id', 'conductor:id,full_name'])
            ->orderByDesc('interview_date')
            ->paginate($request->get('per_page', 15));

        return response()->json($interviews);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'interview_date' => ['required', 'date'],
            'reason_for_leaving' => ['required', 'in:better_opportunity,salary,work_environment,personal,relocation,career_change,management,other'],
            'overall_satisfaction' => ['required', 'integer', 'min:1', 'max:5'],
            'would_recommend' => ['required', 'boolean'],
            'feedback' => ['nullable', 'string'],
            'improvements' => ['nullable', 'string'],
        ]);

        $conductor = Employee::where('user_id', $request->user()->id)->first();

        $interview = ExitInterview::create(array_merge($validated, [
            'conducted_by' => $conductor?->id ?? $validated['employee_id'],
        ]));

        return response()->json([
            'message' => 'Exit interview recorded.',
            'data' => $interview->load(['employee:id,full_name', 'conductor:id,full_name']),
        ], 201);
    }

    public function show(ExitInterview $exitInterview): JsonResponse
    {
        return response()->json([
            'data' => $exitInterview->load([
                'employee:id,full_name,employee_id,department_id',
                'employee.department:id,name',
                'conductor:id,full_name',
            ]),
        ]);
    }

    public function update(Request $request, ExitInterview $exitInterview): JsonResponse
    {
        $validated = $request->validate([
            'reason_for_leaving' => ['sometimes', 'in:better_opportunity,salary,work_environment,personal,relocation,career_change,management,other'],
            'overall_satisfaction' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'would_recommend' => ['sometimes', 'boolean'],
            'feedback' => ['nullable', 'string'],
            'improvements' => ['nullable', 'string'],
        ]);

        $exitInterview->update($validated);

        return response()->json([
            'message' => 'Exit interview updated.',
            'data' => $exitInterview,
        ]);
    }

    public function analytics(): JsonResponse
    {
        $interviews = ExitInterview::all();

        $reasonCounts = $interviews->groupBy('reason_for_leaving')
            ->map(fn ($group) => $group->count());

        $avgSatisfaction = $interviews->avg('overall_satisfaction');
        $recommendRate = $interviews->count() > 0
            ? round(($interviews->where('would_recommend', true)->count() / $interviews->count()) * 100, 1)
            : 0;

        return response()->json([
            'data' => [
                'total_interviews' => $interviews->count(),
                'reasons' => $reasonCounts,
                'average_satisfaction' => round($avgSatisfaction ?? 0, 1),
                'recommendation_rate' => $recommendRate,
            ],
        ]);
    }
}
