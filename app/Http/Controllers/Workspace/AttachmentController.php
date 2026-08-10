<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskActivityLog;
use App\Models\TaskAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function store(Request $request, Task $task): JsonResponse
    {
        $request->validate(['file' => 'required|file|max:51200']); // 50MB

        $file = $request->file('file');
        $path = $file->store('task-attachments/'.$task->id, 'public');

        $attachment = $task->attachments()->create([
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'file_type' => $file->getMimeType(),
            'uploaded_by' => $request->user()->employee?->id,
        ]);

        TaskActivityLog::create([
            'task_id' => $task->id,
            'user_id' => $request->user()->id,
            'action' => 'attachment_added',
            'new_value' => $file->getClientOriginalName(),
        ]);

        return response()->json(['data' => $attachment, 'message' => 'Uploaded.'], 201);
    }

    public function destroy(Task $task, TaskAttachment $attachment): JsonResponse
    {
        if ($attachment->file_path) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $attachment->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
