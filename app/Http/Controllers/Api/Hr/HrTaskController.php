<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreTaskRequest;
use App\Http\Requests\Hr\UpdateTaskRequest;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrTaskController extends Controller
{
    /**
     * Paginated list of tasks with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Task::query()
            ->with(['assignee:id,full_name', 'assigner:id,full_name', 'taskable']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($priority = $request->get('priority')) {
            $query->where('priority', $priority);
        }

        if ($assignedTo = $request->get('assigned_to')) {
            $query->where('assigned_to', $assignedTo);
        }

        if ($taskableType = $request->get('taskable_type')) {
            $query->where('taskable_type', $taskableType);
        }

        if ($deadlineFrom = $request->get('deadline_from')) {
            $query->where('deadline', '>=', $deadlineFrom);
        }

        if ($deadlineTo = $request->get('deadline_to')) {
            $query->where('deadline', '<=', $deadlineTo);
        }

        $query->orderBy('deadline', 'asc');

        $tasks = $query->paginate($request->get('per_page', 15));

        return response()->json($tasks);
    }

    /**
     * Show task detail with subtasks, comments, and attachments.
     */
    public function show(Task $task): JsonResponse
    {
        $task->load([
            'assignee:id,full_name',
            'assigner:id,full_name',
            'taskable',
            'subtasks.assignee:id,full_name',
            'comments.employee:id,full_name',
            'attachments.uploader:id,full_name',
        ]);

        return response()->json(['data' => $task]);
    }

    /**
     * Create a task for a meeting.
     */
    public function storeForMeeting(StoreTaskRequest $request, Meeting $meeting): JsonResponse
    {
        $employee = $request->user()->employee;

        $task = Task::create([
            ...$request->validated(),
            'taskable_type' => Meeting::class,
            'taskable_id' => $meeting->id,
            'assigned_by' => $employee?->id,
            'status' => 'pending',
        ]);

        $task->load(['assignee:id,full_name', 'assigner:id,full_name']);

        return response()->json([
            'data' => $task,
            'message' => 'Task created successfully.',
        ], 201);
    }

    /**
     * Update a task.
     */
    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        $validated = $request->validated();

        if (isset($validated['status']) && $validated['status'] === 'completed' && ! $task->completed_at) {
            $validated['completed_at'] = now();
        }

        $task->update($validated);

        $task->load(['assignee:id,full_name', 'assigner:id,full_name']);

        return response()->json([
            'data' => $task,
            'message' => 'Task updated successfully.',
        ]);
    }

    /**
     * Update task status.
     */
    public function updateStatus(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,in_progress,completed,cancelled'],
        ]);

        $updateData = ['status' => $validated['status']];

        if ($validated['status'] === 'completed' && ! $task->completed_at) {
            $updateData['completed_at'] = now();
        }

        $task->update($updateData);

        return response()->json([
            'data' => $task,
            'message' => 'Task status updated successfully.',
        ]);
    }

    /**
     * Soft delete a task.
     */
    public function destroy(Task $task): JsonResponse
    {
        $task->delete();

        return response()->json(['message' => 'Task deleted successfully.']);
    }

    /**
     * Create a subtask.
     */
    public function storeSubtask(StoreTaskRequest $request, Task $task): JsonResponse
    {
        $employee = $request->user()->employee;

        $subtask = Task::create([
            ...$request->validated(),
            'taskable_type' => $task->taskable_type,
            'taskable_id' => $task->taskable_id,
            'parent_id' => $task->id,
            'assigned_by' => $employee?->id,
            'status' => 'pending',
        ]);

        $subtask->load(['assignee:id,full_name', 'assigner:id,full_name']);

        return response()->json([
            'data' => $subtask,
            'message' => 'Subtask created successfully.',
        ], 201);
    }

    /**
     * Add a comment to a task.
     */
    public function storeComment(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string'],
        ]);

        $employee = $request->user()->employee;

        $comment = TaskComment::create([
            'task_id' => $task->id,
            'employee_id' => $employee?->id,
            'content' => $validated['content'],
        ]);

        $comment->load('employee:id,full_name');

        return response()->json([
            'data' => $comment,
            'message' => 'Comment added successfully.',
        ], 201);
    }

    /**
     * Upload an attachment to a task.
     */
    public function storeAttachment(Request $request, Task $task): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
        ]);

        $file = $request->file('file');
        $path = $file->store("tasks/attachments/{$task->id}", 'public');

        $employee = $request->user()->employee;

        $attachment = TaskAttachment::create([
            'task_id' => $task->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'file_type' => $file->getMimeType(),
            'uploaded_by' => $employee?->id,
        ]);

        $attachment->load('uploader:id,full_name');

        return response()->json([
            'data' => $attachment,
            'message' => 'Attachment uploaded successfully.',
        ], 201);
    }
}
