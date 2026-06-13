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
 *
 * A task can be co-owned by several employees: the first selected becomes the
 * canonical `assigned_to` (so single-assignee features keep working) and the full
 * set is synced to the task_assignee pivot.
 */
class CeoTaskController extends Controller
{
    /**
     * Create a standalone task (not tied to a meeting).
     */
    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $assigneeIds = $this->assigneeIds($data);

        $task = Task::create([
            ...$data,
            'assigned_to' => $assigneeIds[0],
            'taskable_type' => null,
            'taskable_id' => null,
            'assigned_by' => $request->user()->employee?->id,
            'status' => 'pending',
        ]);

        $task->assignees()->sync($assigneeIds);

        CeoDashboardService::bustTaskCache();

        return back();
    }

    /**
     * Update any editable field on a task.
     */
    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $validated = $request->validated();
        $assigneeIds = $this->assigneeIds($validated, required: false);

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

    /**
     * Resolve the ordered, de-duplicated set of assignee ids from the validated
     * payload (multi-select `assignee_ids` or the legacy single `assigned_to`).
     * Strips `assignee_ids` from the payload so it isn't mass-assigned.
     *
     * @param  array<string, mixed>  $data
     * @return ($required is true ? array<int, int> : array<int, int>|null)
     */
    private function assigneeIds(array &$data, bool $required = true): ?array
    {
        $ids = [];

        if (! empty($data['assignee_ids'])) {
            $ids = $data['assignee_ids'];
        } elseif (! empty($data['assigned_to'])) {
            $ids = [$data['assigned_to']];
        }

        unset($data['assignee_ids']);

        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return $required ? [] : null;
        }

        return $ids;
    }
}
