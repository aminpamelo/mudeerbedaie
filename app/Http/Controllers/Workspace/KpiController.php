<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskTimeEntry;
use App\Models\TmsUserStat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class KpiController extends Controller
{
    public function index(Request $request): Response
    {
        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

        $startDate = now()->setYear($year)->setMonth($month)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        // Per-staff KPI stats
        $staffStats = Employee::query()
            ->with('user:id,name,email', 'department:id,name')
            ->whereHas('user')
            ->get()
            ->map(function (Employee $employee) use ($startDate, $endDate) {
                $taskQuery = Task::where('assigned_to', $employee->id);

                $completed = (clone $taskQuery)
                    ->where('status', 'completed')
                    ->whereBetween('completed_at', [$startDate, $endDate])
                    ->count();

                $overdue = (clone $taskQuery)
                    ->whereNotIn('status', ['completed', 'cancelled'])
                    ->whereNotNull('deadline')
                    ->where('deadline', '<', now())
                    ->count();

                $avgCompletionMinutes = (clone $taskQuery)
                    ->where('status', 'completed')
                    ->whereBetween('completed_at', [$startDate, $endDate])
                    ->whereNotNull('actual_minutes')
                    ->avg('actual_minutes');

                $timeTracked = TaskTimeEntry::where('user_id', $employee->user_id)
                    ->whereBetween('started_at', [$startDate, $endDate])
                    ->whereNotNull('ended_at')
                    ->sum(DB::raw('TIMESTAMPDIFF(SECOND, started_at, ended_at)'));

                // Fallback for SQLite
                if (DB::getDriverName() === 'sqlite') {
                    $timeTracked = TaskTimeEntry::where('user_id', $employee->user_id)
                        ->whereBetween('started_at', [$startDate, $endDate])
                        ->whereNotNull('ended_at')
                        ->get()
                        ->sum(fn ($entry) => $entry->ended_at->diffInSeconds($entry->started_at));
                }

                $cachedStat = TmsUserStat::where('user_id', $employee->user_id)
                    ->whereDate('date', today())
                    ->first();

                return [
                    'id' => $employee->id,
                    'name' => $employee->user?->name ?? 'Unknown',
                    'email' => $employee->user?->email,
                    'department' => $employee->department?->name ?? 'N/A',
                    'tasks_completed' => $completed,
                    'overdue_count' => $overdue,
                    'avg_completion_minutes' => round($avgCompletionMinutes ?? 0),
                    'time_tracked_seconds' => (int) $timeTracked,
                    'total_points' => $cachedStat?->total_points ?? 0,
                    'streak_days' => $cachedStat?->streak_days ?? 0,
                ];
            })
            ->sortByDesc('tasks_completed')
            ->values();

        // Department-level aggregates
        $departmentStats = $staffStats->groupBy('department')->map(function ($group, $deptName) {
            $totalTasks = $group->sum('tasks_completed') + $group->sum('overdue_count');
            $completionRate = $totalTasks > 0 ? round(($group->sum('tasks_completed') / $totalTasks) * 100) : 0;

            return [
                'department' => $deptName,
                'total_staff' => $group->count(),
                'total_completed' => $group->sum('tasks_completed'),
                'total_overdue' => $group->sum('overdue_count'),
                'completion_rate' => $completionRate,
                'total_time_tracked' => $group->sum('time_tracked_seconds'),
            ];
        })->values();

        return Inertia::render('Kpi', [
            'staffStats' => $staffStats,
            'departmentStats' => $departmentStats,
            'filters' => [
                'month' => (int) $month,
                'year' => (int) $year,
            ],
        ]);
    }
}
