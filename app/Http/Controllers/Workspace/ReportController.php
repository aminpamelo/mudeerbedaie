<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $departments = DB::table('departments')->select('id', 'name')->orderBy('name')->get();
        $staff = Employee::with('user:id,name')->whereHas('user')->get()->map(fn ($e) => [
            'id' => $e->id,
            'name' => $e->user?->name ?? 'Unknown',
        ]);

        return Inertia::render('Reports', [
            'departments' => $departments,
            'staff' => $staff,
        ]);
    }

    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'required|in:daily,weekly,monthly',
            'department_id' => 'nullable|exists:departments,id',
            'employee_id' => 'nullable|exists:employees,id',
            'project_id' => 'nullable|exists:tms_projects,id',
        ]);

        $period = $request->input('period');

        $startDate = match ($period) {
            'daily' => now()->startOfDay(),
            'weekly' => now()->startOfWeek(),
            'monthly' => now()->startOfMonth(),
        };
        $endDate = now();

        $query = Task::query()
            ->with('assignee.user:id,name', 'project:id,name');

        if ($request->filled('department_id')) {
            $employeeIds = Employee::where('department_id', $request->input('department_id'))->pluck('id');
            $query->whereIn('assigned_to', $employeeIds);
        }

        if ($request->filled('employee_id')) {
            $query->where('assigned_to', $request->input('employee_id'));
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }

        // Tasks created in the period
        $created = (clone $query)->whereBetween('created_at', [$startDate, $endDate])->count();

        // Tasks completed in the period
        $completed = (clone $query)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->count();

        // Overdue tasks
        $overdue = (clone $query)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotNull('deadline')
            ->where('deadline', '<', now())
            ->count();

        // In-progress tasks
        $inProgress = (clone $query)->where('status', 'in_progress')->count();

        // Pending review
        $review = (clone $query)->where('status', 'review')->count();

        // Total active
        $totalActive = (clone $query)->whereNotIn('status', ['completed', 'cancelled'])->count();

        // Completion rate
        $totalForRate = $completed + $totalActive;
        $completionRate = $totalForRate > 0 ? round(($completed / $totalForRate) * 100) : 0;

        // Per-staff breakdown
        $staffBreakdown = (clone $query)
            ->select('assigned_to')
            ->selectRaw('COUNT(*) as total_tasks')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            ->selectRaw("SUM(CASE WHEN status NOT IN ('completed','cancelled') AND deadline IS NOT NULL AND deadline < ? THEN 1 ELSE 0 END) as overdue", [now()])
            ->whereNotNull('assigned_to')
            ->groupBy('assigned_to')
            ->get()
            ->map(function ($row) {
                $employee = Employee::with('user:id,name')->find($row->assigned_to);

                return [
                    'employee_id' => $row->assigned_to,
                    'name' => $employee?->user?->name ?? 'Unknown',
                    'total_tasks' => $row->total_tasks,
                    'completed' => $row->completed,
                    'overdue' => $row->overdue,
                ];
            });

        return response()->json([
            'period' => $period,
            'date_range' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'summary' => [
                'created' => $created,
                'completed' => $completed,
                'overdue' => $overdue,
                'in_progress' => $inProgress,
                'review' => $review,
                'total_active' => $totalActive,
                'completion_rate' => $completionRate,
            ],
            'staff_breakdown' => $staffBreakdown,
        ]);
    }
}
