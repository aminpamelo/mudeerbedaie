<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $userId = $request->user()->id;
        $employeeId = $request->user()->employee?->id;

        $stats = [
            'activeTasks' => $employeeId ? Task::where('assigned_to', $employeeId)->whereNotIn('status', ['completed', 'cancelled'])->count() : 0,
            'completedToday' => $employeeId ? Task::where('assigned_to', $employeeId)->where('status', 'completed')->whereDate('completed_at', today())->count() : 0,
            'overdue' => $employeeId ? Task::where('assigned_to', $employeeId)->whereNotIn('status', ['completed', 'cancelled'])->whereNotNull('deadline')->where('deadline', '<', now())->count() : 0,
            'total' => $employeeId ? Task::where('assigned_to', $employeeId)->count() : 0,
        ];

        return Inertia::render('Dashboard', ['stats' => $stats]);
    }
}
