<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubtaskController extends Controller
{
    public function store(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'deadline' => 'nullable|date',
            'assigned_to' => 'nullable|exists:employees,id',
        ]);

        $subtask = $task->subtasks()->create([
            ...$validated,
            'project_id' => $task->project_id,
            'status' => 'pending',
            'priority' => $validated['priority'] ?? 'medium',
            'assigned_by' => $request->user()->employee?->id,
        ]);

        TaskActivityLog::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'action' => 'subtask_added',
            'new_value' => $subtask->title,
        ]);

        return response()->json(['data' => $subtask, 'message' => 'Subtask created.'], 201);
    }

    public function update(Request $request, Task $task, Task $subtask): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:pending,in_progress,review,completed,cancelled',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'deadline' => 'nullable|date',
            'assigned_to' => 'nullable|exists:employees,id',
        ]);

        if (isset($validated['status']) && $validated['status'] === 'completed' && $subtask->status !== 'completed') {
            $validated['completed_at'] = now();
        }

        $subtask->update($validated);

        return response()->json(['data' => $subtask->fresh(), 'message' => 'Subtask updated.']);
    }

    public function destroy(Task $task, Task $subtask): JsonResponse
    {
        $subtask->delete();

        return response()->json(['message' => 'Subtask deleted.']);
    }
}
