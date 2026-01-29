<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Notifications\TaskDueReminderNotification;
use App\Notifications\TaskOverdueNotification;
use Illuminate\Console\Command;

class SendTaskReminders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tasks:send-reminders';

    /**
     * The console command description.
     */
    protected $description = 'Send reminder notifications for tasks due soon and overdue tasks';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->sendDueReminders();
        $this->sendOverdueNotifications();

        return self::SUCCESS;
    }

    /**
     * Send reminders for tasks due within 24 hours
     */
    protected function sendDueReminders(): void
    {
        $tasks = Task::with(['assignee', 'department'])
            ->whereNotNull('due_date')
            ->whereNotNull('assigned_to')
            ->whereNull('reminder_sent_at')
            ->where('due_date', '>=', now())
            ->where('due_date', '<=', now()->addHours(24))
            ->whereNotIn('status', [TaskStatus::COMPLETED, TaskStatus::CANCELLED])
            ->get();

        $count = 0;

        foreach ($tasks as $task) {
            if ($task->assignee) {
                $task->assignee->notify(new TaskDueReminderNotification($task));
                $task->update(['reminder_sent_at' => now()]);
                $count++;
            }
        }

        $this->info("Sent {$count} due reminder(s).");
    }

    /**
     * Send notifications for overdue tasks
     */
    protected function sendOverdueNotifications(): void
    {
        $tasks = Task::with(['assignee', 'department'])
            ->whereNotNull('due_date')
            ->whereNotNull('assigned_to')
            ->whereNull('overdue_notified_at')
            ->where('due_date', '<', now())
            ->whereNotIn('status', [TaskStatus::COMPLETED, TaskStatus::CANCELLED])
            ->get();

        $count = 0;

        foreach ($tasks as $task) {
            if ($task->assignee) {
                $task->assignee->notify(new TaskOverdueNotification($task));
                $task->update(['overdue_notified_at' => now()]);
                $count++;
            }
        }

        $this->info("Sent {$count} overdue notification(s).");
    }
}
