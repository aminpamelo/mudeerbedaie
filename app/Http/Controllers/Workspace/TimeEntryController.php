<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskTimeEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimeEntryController extends Controller
{
    public function start(Request $request, Task $task): JsonResponse
    {
        // Stop any running timer for this user
        TaskTimeEntry::where('user_id', $request->user()->id)
            ->whereNull('ended_at')
            ->each(fn ($e) => $e->stop());

        $entry = $task->timeEntries()->create([
            'user_id' => $request->user()->id,
            'started_at' => now(),
        ]);

        return response()->json(['data' => $entry, 'message' => 'Timer started.'], 201);
    }

    public function stop(Request $request, Task $task, TaskTimeEntry $entry): JsonResponse
    {
        $entry->stop();

        return response()->json(['data' => $entry->fresh(), 'message' => 'Timer stopped.']);
    }

    public function index(Request $request, Task $task): JsonResponse
    {
        $entries = $task->timeEntries()->with('user')->orderByDesc('started_at')->get();

        return response()->json(['data' => $entries]);
    }

    public function activeTimer(Request $request): JsonResponse
    {
        $entry = TaskTimeEntry::where('user_id', $request->user()->id)
            ->whereNull('ended_at')
            ->with('task')
            ->first();

        return response()->json(['data' => $entry]);
    }
}
