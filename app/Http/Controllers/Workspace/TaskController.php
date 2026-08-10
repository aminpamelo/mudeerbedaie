<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $employeeId = $user->employee?->id;

        $query = Task::query()
            ->with(['category', 'project', 'assignees.user', 'checklists'])
            ->withCount(['subtasks', 'checklists', 'attachments', 'comments']);

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->input('priority'));
        }
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->input('assigned_to'));
        }

        $query->whereNull('parent_id'); // Only top-level tasks
        $tasks = $query->orderByDesc('created_at')->paginate(20);

        return Inertia::render('MyTasks', ['tasks' => $tasks]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'project_id' => 'nullable|exists:tms_projects,id',
            'category_id' => 'nullable|exists:task_categories,id',
            'priority' => 'required|in:low,medium,high,urgent',
            'status' => 'nullable|in:pending,in_progress,review,completed,cancelled',
            'deadline' => 'nullable|date',
            'start_date' => 'nullable|date',
            'estimated_minutes' => 'nullable|integer|min:1',
            'assigned_to' => 'nullable|exists:employees,id',
            'tags' => 'nullable|array',
        ]);

        $validated['assigned_by'] = $request->user()->employee?->id;
        $validated['status'] = $validated['status'] ?? 'pending';

        $task = Task::create($validated);

        TaskActivityLog::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'action' => 'created',
        ]);

        return response()->json(['data' => $task->load(['category', 'project']), 'message' => 'Task created.'], 201);
    }

    public function show(Request $request, Task $task): Response
    {
        $task->load([
            'category', 'project', 'assignees.user', 'checklists',
            'subtasks.assignees.user', 'subtasks.checklists',
            'comments.employee.user', 'comments.user',
            'attachments', 'activityLogs.user', 'watchers',
            'timeEntries.user', 'dependencies.dependsOnTask',
            'recurringConfig', 'approvedByUser',
        ])->loadCount(['subtasks', 'checklists', 'attachments', 'comments', 'timeEntries']);

        return Inertia::render('TaskDetail', ['task' => $task]);
    }

    public function update(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'project_id' => 'nullable|exists:tms_projects,id',
            'category_id' => 'nullable|exists:task_categories,id',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'status' => 'sometimes|in:pending,in_progress,review,completed,cancelled',
            'deadline' => 'nullable|date',
            'start_date' => 'nullable|date',
            'estimated_minutes' => 'nullable|integer|min:1',
            'assigned_to' => 'nullable|exists:employees,id',
            'tags' => 'nullable|array',
            'sort_order' => 'nullable|integer',
        ]);

        // Log changes
        foreach ($validated as $field => $newValue) {
            $oldValue = $task->getOriginal($field);
            if ($oldValue != $newValue) {
                TaskActivityLog::create([
                    'task_id' => $task->id,
                    'user_id' => $request->user()->id,
                    'action' => 'updated',
                    'field' => $field,
                    'old_value' => is_array($oldValue) ? json_encode($oldValue) : (string) $oldValue,
                    'new_value' => is_array($newValue) ? json_encode($newValue) : (string) $newValue,
                ]);
            }
        }

        if (isset($validated['status']) && $validated['status'] === 'completed' && $task->status !== 'completed') {
            $validated['completed_at'] = now();
        }

        $task->update($validated);

        return response()->json(['data' => $task->fresh(['category', 'project']), 'message' => 'Task updated.']);
    }

    public function destroy(Task $task): JsonResponse
    {
        $task->delete();

        return response()->json(['message' => 'Task deleted.']);
    }

    public function updateStatus(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate(['status' => 'required|in:pending,in_progress,review,completed,cancelled']);

        $oldStatus = $task->status;
        $updates = ['status' => $validated['status']];

        if ($validated['status'] === 'completed' && $oldStatus !== 'completed') {
            $updates['completed_at'] = now();
        }

        $task->update($updates);

        TaskActivityLog::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'action' => 'status_changed',
            'field' => 'status',
            'old_value' => $oldStatus,
            'new_value' => $validated['status'],
        ]);

        return response()->json(['data' => $task->fresh(), 'message' => 'Status updated.']);
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tasks' => 'required|array',
            'tasks.*.id' => 'required|exists:tasks,id',
            'tasks.*.sort_order' => 'required|integer',
            'tasks.*.status' => 'sometimes|in:pending,in_progress,review,completed,cancelled',
        ]);

        foreach ($validated['tasks'] as $item) {
            Task::where('id', $item['id'])->update([
                'sort_order' => $item['sort_order'],
                'status' => $item['status'] ?? null,
            ]);
        }

        return response()->json(['message' => 'Reordered.']);
    }

    public function assign(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'employee_ids' => 'required|array',
            'employee_ids.*' => 'exists:employees,id',
        ]);

        $task->assignees()->sync($validated['employee_ids']);

        if (! empty($validated['employee_ids'])) {
            $task->update(['assigned_to' => $validated['employee_ids'][0]]);
        }

        TaskActivityLog::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'action' => 'assigned',
            'new_value' => implode(',', $validated['employee_ids']),
        ]);

        return response()->json(['message' => 'Assigned.']);
    }
}
