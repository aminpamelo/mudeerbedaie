<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskChecklist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChecklistController extends Controller
{
    public function store(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate(['title' => 'required|string|max:255']);

        $maxOrder = $task->checklists()->max('sort_order') ?? 0;

        $item = $task->checklists()->create([
            'title' => $validated['title'],
            'sort_order' => $maxOrder + 1,
        ]);

        return response()->json(['data' => $item, 'message' => 'Checklist item added.'], 201);
    }

    public function toggle(Request $request, Task $task, TaskChecklist $checklist): JsonResponse
    {
        $checklist->update([
            'is_completed' => ! $checklist->is_completed,
            'completed_by' => ! $checklist->is_completed ? $request->user()->id : null,
            'completed_at' => ! $checklist->is_completed ? now() : null,
        ]);

        return response()->json(['data' => $checklist->fresh(), 'message' => 'Toggled.']);
    }

    public function update(Request $request, Task $task, TaskChecklist $checklist): JsonResponse
    {
        $validated = $request->validate(['title' => 'required|string|max:255']);
        $checklist->update($validated);

        return response()->json(['data' => $checklist->fresh()]);
    }

    public function destroy(Task $task, TaskChecklist $checklist): JsonResponse
    {
        $checklist->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    public function reorder(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:task_checklists,id',
            'items.*.sort_order' => 'required|integer',
        ]);

        foreach ($validated['items'] as $item) {
            TaskChecklist::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['message' => 'Reordered.']);
    }
}
