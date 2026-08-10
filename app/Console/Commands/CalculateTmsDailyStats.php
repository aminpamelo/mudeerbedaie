<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskTimeEntry;
use App\Models\TmsUserStat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CalculateTmsDailyStats extends Command
{
    protected $signature = 'tms:calculate-daily-stats';

    protected $description = 'Calculate and cache daily task stats for each user with an employee record.';

    public function handle(): int
    {
        $employees = Employee::with('user')->whereHas('user')->get();

        if ($employees->isEmpty()) {
            $this->info('No employees found.');

            return self::SUCCESS;
        }

        $today = today();
        $processed = 0;

        foreach ($employees as $employee) {
            $userId = $employee->user_id;
            $employeeId = $employee->id;

            $completedToday = Task::where('assigned_to', $employeeId)
                ->where('status', 'completed')
                ->whereDate('completed_at', $today)
                ->count();

            $createdToday = Task::where('assigned_by', $employeeId)
                ->whereDate('created_at', $today)
                ->count();

            $overdue = Task::where('assigned_to', $employeeId)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->whereNotNull('deadline')
                ->where('deadline', '<', $today)
                ->count();

            // Calculate time tracked today
            $timeTracked = 0;
            if (DB::getDriverName() === 'sqlite') {
                $timeTracked = TaskTimeEntry::where('user_id', $userId)
                    ->whereDate('started_at', $today)
                    ->whereNotNull('ended_at')
                    ->get()
                    ->sum(fn ($entry) => $entry->ended_at->diffInSeconds($entry->started_at));
            } else {
                $timeTracked = (int) TaskTimeEntry::where('user_id', $userId)
                    ->whereDate('started_at', $today)
                    ->whereNotNull('ended_at')
                    ->sum(DB::raw('TIMESTAMPDIFF(SECOND, started_at, ended_at)'));
            }

            // Calculate streak (consecutive days with completed tasks)
            $streak = 0;
            $checkDate = $today->copy()->subDay();
            while (true) {
                $had = Task::where('assigned_to', $employeeId)
                    ->where('status', 'completed')
                    ->whereDate('completed_at', $checkDate)
                    ->exists();

                if (! $had) {
                    break;
                }

                $streak++;
                $checkDate->subDay();
            }

            // Add today if completed
            if ($completedToday > 0) {
                $streak++;
            }

            // Calculate total points (sum of all task points)
            $totalPoints = (int) Task::where('assigned_to', $employeeId)
                ->where('status', 'completed')
                ->sum('points');

            TmsUserStat::updateOrCreate(
                ['user_id' => $userId, 'date' => $today],
                [
                    'tasks_completed' => $completedToday,
                    'tasks_created' => $createdToday,
                    'tasks_overdue' => $overdue,
                    'time_tracked_seconds' => $timeTracked,
                    'streak_days' => $streak,
                    'total_points' => $totalPoints,
                ]
            );

            $processed++;
        }

        $this->info("Daily stats calculated for {$processed} employee(s).");

        return self::SUCCESS;
    }
}
