<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Notifications\Workspace\TaskDueReminderNotification;
use Illuminate\Console\Command;

class SendTmsReminders extends Command
{
    protected $signature = 'tms:send-reminders';

    protected $description = 'Send deadline reminder notifications for tasks due today, tomorrow, or overdue.';

    public function handle(): int
    {
        $sent = 0;

        // Tasks due tomorrow
        $dueTomorrow = Task::query()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereDate('deadline', today()->addDay())
            ->whereNotNull('assigned_to')
            ->with('assignees.user')
            ->get();

        foreach ($dueTomorrow as $task) {
            foreach ($task->assignees as $assignee) {
                if ($assignee->user) {
                    $assignee->user->notify(new TaskDueReminderNotification($task, 'due_tomorrow'));
                    $sent++;
                }
            }
        }

        // Tasks due today
        $dueToday = Task::query()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereDate('deadline', today())
            ->whereNotNull('assigned_to')
            ->with('assignees.user')
            ->get();

        foreach ($dueToday as $task) {
            foreach ($task->assignees as $assignee) {
                if ($assignee->user) {
                    $assignee->user->notify(new TaskDueReminderNotification($task, 'due_today'));
                    $sent++;
                }
            }
        }

        // Overdue tasks
        $overdue = Task::query()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotNull('deadline')
            ->where('deadline', '<', today())
            ->whereNotNull('assigned_to')
            ->with('assignees.user')
            ->get();

        foreach ($overdue as $task) {
            foreach ($task->assignees as $assignee) {
                if ($assignee->user) {
                    $assignee->user->notify(new TaskDueReminderNotification($task, 'overdue'));
                    $sent++;
                }
            }
        }

        $this->info("Sent {$sent} reminder notification(s).");

        return self::SUCCESS;
    }
}
