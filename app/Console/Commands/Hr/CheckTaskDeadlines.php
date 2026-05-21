<?php

namespace App\Console\Commands\Hr;

use App\Models\Task;
use App\Notifications\Hr\TaskDeadlineApproachingNotification;
use App\Notifications\Hr\TaskOverdueForAssignerNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CheckTaskDeadlines extends Command
{
    protected $signature = 'hr:check-task-deadlines';

    protected $description = 'Notify assignees of upcoming and overdue task deadlines, with a smart non-spamming cadence.';

    public function handle(): int
    {
        $today = Carbon::today();

        $tasks = Task::query()
            ->with(['assignee.user', 'assigner.user'])
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereNotNull('deadline')
            ->get();

        $assigneeCount = 0;
        $assignerCount = 0;

        foreach ($tasks as $task) {
            $stage = $this->resolveStage($task, $today);

            if ($stage === null) {
                continue;
            }

            if ($this->sendAssigneeReminder($task, $stage)) {
                $assigneeCount++;
            }

            if ($this->isOverdueStage($stage) && $this->sendAssignerReminder($task, $stage, $today)) {
                $assignerCount++;
            }
        }

        $this->info("Sent {$assigneeCount} assignee reminder(s) and {$assignerCount} assigner reminder(s).");

        return self::SUCCESS;
    }

    private function resolveStage(Task $task, Carbon $today): ?string
    {
        $deadline = Carbon::parse($task->deadline)->startOfDay();
        $diff = (int) $today->diffInDays($deadline, false);

        return match (true) {
            $diff === 3 => TaskDeadlineApproachingNotification::STAGE_THREE_DAYS,
            $diff === 1 => TaskDeadlineApproachingNotification::STAGE_ONE_DAY,
            $diff === 0 => TaskDeadlineApproachingNotification::STAGE_DUE_TODAY,
            $diff === -1 => TaskDeadlineApproachingNotification::STAGE_OVERDUE_1,
            $diff === -3 => TaskDeadlineApproachingNotification::STAGE_OVERDUE_3,
            $diff === -7 => TaskDeadlineApproachingNotification::STAGE_OVERDUE_7,
            $diff < -7 && $diff % 7 === 0 => TaskDeadlineApproachingNotification::STAGE_OVERDUE_WEEKLY,
            default => null,
        };
    }

    private function isOverdueStage(string $stage): bool
    {
        return str_starts_with($stage, 'overdue');
    }

    private function sendAssigneeReminder(Task $task, string $stage): bool
    {
        $reminders = $task->reminders_sent ?? [];

        if (in_array("assignee:{$stage}", $reminders, true)) {
            return false;
        }

        $user = $task->assignee?->user;

        if (! $user) {
            return false;
        }

        $user->notify(new TaskDeadlineApproachingNotification($task, $stage));

        $reminders[] = "assignee:{$stage}";
        $task->forceFill(['reminders_sent' => array_values(array_unique($reminders))])->save();

        return true;
    }

    private function sendAssignerReminder(Task $task, string $stage, Carbon $today): bool
    {
        if (! $task->assigned_by || $task->assigned_by === $task->assigned_to) {
            return false;
        }

        $reminders = $task->reminders_sent ?? [];

        if (in_array("assigner:{$stage}", $reminders, true)) {
            return false;
        }

        $user = $task->assigner?->user;

        if (! $user) {
            return false;
        }

        $daysOverdue = (int) abs($today->diffInDays(Carbon::parse($task->deadline)->startOfDay(), false));

        $user->notify(new TaskOverdueForAssignerNotification($task, $daysOverdue));

        $reminders[] = "assigner:{$stage}";
        $task->forceFill(['reminders_sent' => array_values(array_unique($reminders))])->save();

        return true;
    }
}
