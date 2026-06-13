<?php

namespace App\Http\Controllers\Ceo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreTaskRequest;
use App\Http\Requests\Hr\UpdateTaskRequest;
use App\Models\Task;
use App\Services\Ceo\CeoDashboardService;
use Illuminate\Http\RedirectResponse;

/**
 * Lets the CEO act on tasks straight from the Task Monitoring page: create a new
 * standalone task, edit any field (status, priority, deadline, reassignment,
 * category, title), or delete one. Mutations redirect back so the Inertia visit
 * reloads the board and the aggregates with fresh data.
 */
class CeoTaskController extends Controller
{
    /**
     * Create a standalone task (not tied to a meeting).
     */
    public function store(StoreTaskRequest $request): RedirectResponse
    {
        Task::create([
            ...$request->validated(),
            'taskable_type' => null,
            'taskable_id' => null,
            'assigned_by' => $request->user()->employee?->id,
            'status' => 'pending',
        ]);

        CeoDashboardService::bustTaskCache();

        return back();
    }

    /**
     * Update any editable field on a task.
     */
    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $validated = $request->validated();

        if (array_key_exists('status', $validated)) {
            if ($validated['status'] === 'completed' && ! $task->completed_at) {
                $validated['completed_at'] = now();
            } elseif ($validated['status'] !== 'completed' && $task->completed_at) {
                $validated['completed_at'] = null;
            }
        }

        $task->update($validated);

        CeoDashboardService::bustTaskCache();

        return back();
    }

    /**
     * Soft-delete a task.
     */
    public function destroy(Task $task): RedirectResponse
    {
        $task->delete();

        CeoDashboardService::bustTaskCache();

        return back();
    }
}
