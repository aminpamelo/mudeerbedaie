<?php

namespace App\Notifications\Hr;

use App\Models\Meeting;
use App\Models\Task;

class TaskDeadlineApproachingNotification extends BaseHrNotification
{
    public function __construct(
        public Task $task
    ) {}

    protected function channels(): array
    {
        return ['database', 'push'];
    }

    protected function title(): string
    {
        return 'Task Deadline Approaching';
    }

    protected function body(): string
    {
        $title = $this->task->title;
        $deadline = $this->task->deadline?->format('M j, Y') ?? 'soon';

        return "Your task '{$title}' is due {$deadline}.";
    }

    protected function actionUrl(): string
    {
        if ($this->task->taskable_type === Meeting::class) {
            return '/hr/meetings/'.$this->task->taskable_id;
        }

        return '/hr/tasks';
    }

    protected function icon(): string
    {
        return 'clock';
    }
}
