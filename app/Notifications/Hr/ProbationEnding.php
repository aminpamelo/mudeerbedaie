<?php

namespace App\Notifications\Hr;

use App\Models\Employee;

class ProbationEnding extends BaseHrNotification
{
    public function __construct(
        public Employee $employee,
        public int $daysLeft
    ) {}

    protected function channels(): array
    {
        return ['database', 'mail', 'push'];
    }

    protected function title(): string
    {
        return 'Probation Period Ending';
    }

    protected function body(): string
    {
        $name = $this->employee->full_name;
        $endDate = $this->employee->probation_end_date?->format('M j, Y') ?? 'soon';

        return "{$name}'s probation period ends on {$endDate} ({$this->daysLeft} days remaining). Action required.";
    }

    protected function actionUrl(): string
    {
        return '/hr/employees/'.$this->employee->id;
    }

    protected function icon(): string
    {
        return 'user-check';
    }
}
