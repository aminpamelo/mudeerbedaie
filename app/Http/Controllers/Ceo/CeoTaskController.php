<?php

namespace App\Http\Controllers\Ceo;

use App\Http\Controllers\Concerns\ResolvesTaskAssignees;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreTaskRequest;
use App\Http\Requests\Hr\UpdateTaskRequest;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;

/**
 * Lets the CEO act on tasks straight from the Task Monitoring page: create a new
 * standalone task, edit any field (status, priority, deadline, reassignment,
 * category, title), or delete one. Mutations redirect back so the Inertia visit
 * reloads the board and the aggregates with fresh data.
 *
 * A task can be co-owned by several employees: the first selected becomes the
 * canonical `assigned_to` (so single-assignee features keep working) and the full
 * set is synced to the task_assignee pivot.
 */
class CeoTaskController extends Controller
{
    use ResolvesTaskAssignees;

    /**
     * Create a standalone task (not tied to a meeting).
     */
    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $assigneeIds = $this->resolveAssigneeIds($data);

        $task = Task::create([
            ...$data,
            'assigned_to' => $assigneeIds[0],
            'taskable_type' => null,
            'taskable_id' => null,
            'assigned_by' => $request->user()->employee?->id,
            'status' => 'pending',
        ]);

        $task->assignees()->sync($assigneeIds);

        return back();
    }

    /**
     * Update any editable field on a task.
     */
    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $validated = $request->validated();
        $assigneeIds = $this->resolveAssigneeIds($validated, required: false);

        if ($assigneeIds !== null) {
            $validated['assigned_to'] = $assigneeIds[0];
        }

        if (array_key_exists('status', $validated)) {
            if ($validated['status'] === 'completed' && ! $task->completed_at) {
                $validated['completed_at'] = now();
            } elseif ($validated['status'] !== 'completed' && $task->completed_at) {
                $validated['completed_at'] = null;
            }
        }

        $task->update($validated);

        if ($assigneeIds !== null) {
            $task->assignees()->sync($assigneeIds);
        }

        return back();
    }

    /**
     * Soft-delete a task.
     */
    public function destroy(Task $task): RedirectResponse
    {
        $task->delete();

        return back();
    }
}
