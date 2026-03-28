<?php

namespace App\Notifications\Hr;

use App\Models\LeaveRequest;

class LeaveRequestCancelled extends BaseHrNotification
{
    public function __construct(
        public LeaveRequest $leaveRequest
    ) {}

    protected function channels(): array
    {
        return ['database', 'push'];
    }

    protected function title(): string
    {
        return 'Leave Request Cancelled';
    }

    protected function body(): string
    {
        $name = $this->leaveRequest->employee->full_name;
        $type = $this->leaveRequest->leaveType->name;
        $start = $this->leaveRequest->start_date->format('M j');
        $end = $this->leaveRequest->end_date->format('M j, Y');

        return "{$name} cancelled their {$type} request ({$start} - {$end}).";
    }

    protected function actionUrl(): string
    {
        return '/hr/leave/requests';
    }

    protected function icon(): string
    {
        return 'calendar-x';
    }
}
