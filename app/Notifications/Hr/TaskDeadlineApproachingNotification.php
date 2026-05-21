<?php

namespace App\Notifications\Hr;

use App\Models\Meeting;
use App\Models\Task;

class TaskDeadlineApproachingNotification extends BaseHrNotification
{
    public const STAGE_THREE_DAYS = 'upcoming_3d';

    public const STAGE_ONE_DAY = 'upcoming_1d';

    public const STAGE_DUE_TODAY = 'due_today';

    public const STAGE_OVERDUE_1 = 'overdue_1d';

    public const STAGE_OVERDUE_3 = 'overdue_3d';

    public const STAGE_OVERDUE_7 = 'overdue_7d';

    public const STAGE_OVERDUE_WEEKLY = 'overdue_weekly';

    public function __construct(
        public Task $task,
        public string $stage,
    ) {}

    protected function channels(): array
    {
        return ['database', 'push'];
    }

    protected function title(): string
    {
        return match ($this->stage) {
            self::STAGE_THREE_DAYS => 'Task Due in 3 Days',
            self::STAGE_ONE_DAY => 'Task Due Tomorrow',
            self::STAGE_DUE_TODAY => 'Task Due Today',
            self::STAGE_OVERDUE_1,
            self::STAGE_OVERDUE_3,
            self::STAGE_OVERDUE_7,
            self::STAGE_OVERDUE_WEEKLY => 'Task Overdue',
            default => 'Task Deadline Reminder',
        };
    }

    protected function body(): string
    {
        $title = $this->task->title;
        $deadline = $this->task->deadline?->format('M j, Y') ?? 'soon';
        $daysOverdue = $this->task->deadline ? (int) $this->task->deadline->diffInDays(now()->startOfDay()) : 0;

        return match ($this->stage) {
            self::STAGE_THREE_DAYS => "'{$title}' is due in 3 days ({$deadline}).",
            self::STAGE_ONE_DAY => "'{$title}' is due tomorrow ({$deadline}).",
            self::STAGE_DUE_TODAY => "'{$title}' is due today.",
            self::STAGE_OVERDUE_1 => "'{$title}' was due yesterday and is now overdue.",
            self::STAGE_OVERDUE_3 => "'{$title}' is 3 days overdue.",
            self::STAGE_OVERDUE_7 => "'{$title}' is 1 week overdue.",
            self::STAGE_OVERDUE_WEEKLY => "'{$title}' is {$daysOverdue} days overdue.",
            default => "'{$title}' is due {$deadline}.",
        };
    }

    protected function actionUrl(): string
    {
        if ($this->task->taskable_type === Meeting::class) {
            return '/hr/meetings/'.$this->task->taskable_id;
        }

        return '/hr/my/tasks';
    }

    protected function icon(): string
    {
        return str_starts_with($this->stage, 'overdue') ? 'alert-circle' : 'clock';
    }
}
