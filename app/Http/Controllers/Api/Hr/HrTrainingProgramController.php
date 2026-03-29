<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\TrainingProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrTrainingProgramController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TrainingProgram::query()
            ->withCount('enrollments')
            ->withSum('costs', 'amount');

        if ($search = $request->get('search')) {
            $query->where('title', 'like', "%{$search}%");
        }

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        if ($category = $request->get('category')) {
            $query->where('category', $category);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $programs = $query->orderByDesc('start_date')->paginate($request->get('per_page', 15));

        return response()->json($programs);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:internal,external'],
            'category' => ['required', 'in:mandatory,technical,soft_skill,compliance,other'],
            'provider' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'max_participants' => ['nullable', 'integer', 'min:1'],
            'cost_per_person' => ['nullable', 'numeric', 'min:0'],
            'total_budget' => ['nullable', 'numeric', 'min:0'],
        ]);

        $program = TrainingProgram::create(array_merge($validated, [
            'status' => 'planned',
            'created_by' => $request->user()->id,
        ]));

        return response()->json([
            'message' => 'Training program created.',
            'data' => $program,
        ], 201);
    }

    public function show(TrainingProgram $trainingProgram): JsonResponse
    {
        return response()->json([
            'data' => $trainingProgram->load([
                'enrollments.employee:id,full_name,employee_id,department_id',
                'enrollments.employee.department:id,name',
                'costs',
                'creator:id,name',
            ]),
        ]);
    }

    public function update(Request $request, TrainingProgram $trainingProgram): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['sometimes', 'in:internal,external'],
            'category' => ['sometimes', 'in:mandatory,technical,soft_skill,compliance,other'],
            'provider' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'max_participants' => ['nullable', 'integer', 'min:1'],
            'cost_per_person' => ['nullable', 'numeric', 'min:0'],
            'total_budget' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'in:planned,ongoing,completed,cancelled'],
        ]);

        $trainingProgram->update($validated);

        return response()->json([
            'message' => 'Training program updated.',
            'data' => $trainingProgram,
        ]);
    }

    public function destroy(TrainingProgram $trainingProgram): JsonResponse
    {
        if ($trainingProgram->status !== 'planned') {
            return response()->json(['message' => 'Only planned programs can be deleted.'], 422);
        }

        $trainingProgram->delete();

        return response()->json(['message' => 'Training program deleted.']);
    }

    public function complete(TrainingProgram $trainingProgram): JsonResponse
    {
        $trainingProgram->update(['status' => 'completed']);

        return response()->json([
            'message' => 'Training program marked as completed.',
            'data' => $trainingProgram,
        ]);
    }
}
