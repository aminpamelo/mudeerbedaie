<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\OnboardingTask;
use App\Models\OnboardingTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrOnboardingController extends Controller
{
    public function dashboard(): JsonResponse
    {
        $tasks = OnboardingTask::query()
            ->selectRaw('employee_id, COUNT(*) as total, SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed')
            ->groupBy('employee_id')
            ->with('employee:id,full_name,employee_id,department_id')
            ->get()
            ->map(fn ($row) => [
                'employee' => $row->employee,
                'total_tasks' => $row->total,
                'completed_tasks' => $row->completed,
                'progress' => $row->total > 0 ? round(($row->completed / $row->total) * 100) : 0,
            ]);

        return response()->json(['data' => $tasks]);
    }

    public function assign(Request $request, int $employeeId): JsonResponse
    {
        $employee = Employee::findOrFail($employeeId);

        $request->validate([
            'template_id' => ['nullable', 'exists:onboarding_templates,id'],
        ]);

        return DB::transaction(function () use ($request, $employee) {
            $template = null;

            if ($request->template_id) {
                $template = OnboardingTemplate::with('items')->findOrFail($request->template_id);
            } else {
                $template = OnboardingTemplate::with('items')
                    ->where('is_active', true)
                    ->where(function ($q) use ($employee) {
                        $q->where('department_id', $employee->department_id)
                            ->orWhereNull('department_id');
                    })
                    ->orderByRaw('department_id IS NULL')
                    ->first();
            }

            if (! $template) {
                return response()->json(['message' => 'No onboarding template found.'], 404);
            }

            $tasks = [];
            foreach ($template->items as $item) {
                $tasks[] = OnboardingTask::create([
                    'employee_id' => $employee->id,
                    'template_item_id' => $item->id,
                    'title' => $item->title,
                    'description' => $item->description,
                    'assigned_to' => null,
                    'due_date' => $employee->join_date
                        ? $employee->join_date->addDays($item->due_days)
                        : now()->addDays($item->due_days),
                    'status' => 'pending',
                ]);
            }

            return response()->json([
                'message' => 'Onboarding checklist assigned.',
                'data' => $tasks,
            ], 201);
        });
    }

    public function tasks(int $employeeId): JsonResponse
    {
        $tasks = OnboardingTask::where('employee_id', $employeeId)
            ->with('assignedEmployee:id,full_name')
            ->orderBy('due_date')
            ->get();

        return response()->json(['data' => $tasks]);
    }

    public function updateTask(Request $request, OnboardingTask $onboardingTask): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'in:pending,in_progress,completed,skipped'],
            'notes' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'exists:employees,id'],
        ]);

        if (isset($validated['status']) && $validated['status'] === 'completed') {
            $validated['completed_at'] = now();
            $validated['completed_by'] = $request->user()->id;
        }

        $onboardingTask->update($validated);

        return response()->json([
            'message' => 'Onboarding task updated.',
            'data' => $onboardingTask,
        ]);
    }
}
