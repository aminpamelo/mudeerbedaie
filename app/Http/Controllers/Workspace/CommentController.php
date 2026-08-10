<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskActivityLog;
use App\Models\TaskComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $request, Task $task): JsonResponse
    {
        $validated = $request->validate(['content' => 'required|string|max:5000']);

        $comment = $task->comments()->create([
            'content' => $validated['content'],
            'user_id' => $request->user()->id,
            'employee_id' => $request->user()->employee?->id,
        ]);

        TaskActivityLog::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'action' => 'commented',
        ]);

        return response()->json(['data' => $comment->load(['user', 'employee.user']), 'message' => 'Comment added.'], 201);
    }

    public function destroy(Task $task, TaskComment $comment): JsonResponse
    {
        $comment->delete();

        return response()->json(['message' => 'Comment deleted.']);
    }
}
