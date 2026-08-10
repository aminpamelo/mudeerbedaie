<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\JsonResponse;

class ActivityLogController extends Controller
{
    public function index(Task $task): JsonResponse
    {
        $logs = $task->activityLogs()
            ->with('user')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json(['data' => $logs]);
    }
}
