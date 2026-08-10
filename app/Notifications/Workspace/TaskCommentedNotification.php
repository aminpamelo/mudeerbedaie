<?php

namespace App\Notifications\Workspace;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskCommentedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Task $task,
        public string $commenterName = 'Someone',
        public string $commentExcerpt = '',
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
            'message' => "{$this->commenterName} commented on \"{$this->task->title}\".",
            'type' => 'task_commented',
            'url' => "/workspace/tasks/{$this->task->id}",
            'excerpt' => \Illuminate\Support\Str::limit($this->commentExcerpt, 100),
        ];
    }
}
