<?php

namespace App\Notifications\Workspace;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskDueReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Task $task,
        public string $reminderType = 'due_today',
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $messages = [
            'due_tomorrow' => "\"{$this->task->title}\" is due tomorrow.",
            'due_today' => "\"{$this->task->title}\" is due today.",
            'overdue' => "\"{$this->task->title}\" is overdue!",
        ];

        return [
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'message' => $messages[$this->reminderType] ?? $messages['due_today'],
            'type' => "task_{$this->reminderType}",
            'url' => "/workspace/tasks/{$this->task->id}",
            'deadline' => $this->task->deadline?->toDateString(),
        ];
    }
}
