<?php

namespace App\Notifications\Workspace;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Task $task,
        public string $assignedByName = 'Someone',
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
        return [
            'task_id' => $this->task->id,
            'task_title' => $this->task->title,
            'message' => "{$this->assignedByName} assigned you to \"{$this->task->title}\".",
            'type' => 'task_assigned',
            'url' => "/workspace/tasks/{$this->task->id}",
        ];
    }
}
