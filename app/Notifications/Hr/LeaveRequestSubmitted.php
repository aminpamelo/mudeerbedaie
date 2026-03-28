<?php

namespace App\Notifications\Hr;

use App\Models\LeaveRequest;

class LeaveRequestSubmitted extends BaseHrNotification
{
    public function __construct(
        public LeaveRequest $leaveRequest
    ) {}

    protected function channels(): array
    {
        return ['database', 'mail', 'push'];
    }

    protected function title(): string
    {
        return 'New Leave Request';
    }

    protected function body(): string
    {
        $name = $this->leaveRequest->employee->full_name;
        $type = $this->leaveRequest->leaveType->name;
        $days = $this->leaveRequest->total_days;
        $start = $this->leaveRequest->start_date->format('M j');
        $end = $this->leaveRequest->end_date->format('M j, Y');

        return "{$name} requested {$days} day(s) {$type} ({$start} - {$end})";
    }

    protected function actionUrl(): string
    {
        return '/hr/leave/requests';
    }

    protected function icon(): string
    {
        return 'calendar-plus';
    }
}
