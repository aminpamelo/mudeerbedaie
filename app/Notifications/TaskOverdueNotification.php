<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskOverdueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Task $task
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Task Overdue: :title', ['title' => $this->task->title]))
            ->greeting(__('Hello :name!', ['name' => $notifiable->name]))
            ->line(__('Your task is now overdue in the :department department.', [
                'department' => $this->task->department->name,
            ]))
            ->line(__('**Task:** :title', ['title' => $this->task->title]))
            ->line(__('**Priority:** :priority', ['priority' => $this->task->priority->label()]))
            ->line(__('**Due Date:** :date', [
                'date' => $this->task->due_date->format('d M Y'),
            ]))
            ->action(__('View Task'), route('tasks.show', $this->task))
            ->line(__('Please complete this task as soon as possible.'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'task_id' => $this->task->id,
            'task_number' => $this->task->task_number,
            'task_title' => $this->task->title,
            'department_id' => $this->task->department_id,
            'department_name' => $this->task->department->name,
            'priority' => $this->task->priority->value,
            'due_date' => $this->task->due_date->toDateString(),
            'type' => 'overdue',
            'message' => __('Task ":title" is overdue!', ['title' => $this->task->title]),
            'url' => route('tasks.show', $this->task),
        ];
    }
}
