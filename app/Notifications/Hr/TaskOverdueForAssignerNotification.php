<?php

namespace App\Notifications\Hr;

use App\Models\Meeting;
use App\Models\Task;

class TaskOverdueForAssignerNotification extends BaseHrNotification
{
    public function __construct(
        public Task $task,
        public int $daysOverdue,
    ) {}

    protected function channels(): array
    {
        return ['database', 'push'];
    }

    protected function title(): string
    {
        return 'Task You Assigned is Overdue';
    }

    protected function body(): string
    {
        $title = $this->task->title;
        $assignee = $this->task->assignee?->full_name ?? 'the assignee';

        return "'{$title}' assigned to {$assignee} is {$this->daysOverdue} day(s) overdue.";
    }

    protected function actionUrl(): string
    {
        if ($this->task->taskable_type === Meeting::class) {
            return '/hr/meetings/'.$this->task->taskable_id;
        }

        return '/hr/meetings/tasks';
    }

    protected function icon(): string
    {
        return 'alert-circle';
    }
}
