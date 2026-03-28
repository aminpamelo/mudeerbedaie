<?php

namespace App\Notifications\Hr;

use App\Models\Employee;

class AttendancePenaltyFlagged extends BaseHrNotification
{
    public function __construct(
        public Employee $employee,
        public int $lateCount
    ) {}

    protected function channels(): array
    {
        return ['database', 'push'];
    }

    protected function title(): string
    {
        return 'Attendance Penalty';
    }

    protected function body(): string
    {
        return "You have been flagged for late attendance ({$this->lateCount} times this month).";
    }

    protected function actionUrl(): string
    {
        return '/hr/my/attendance';
    }

    protected function icon(): string
    {
        return 'alert-circle';
    }
}
