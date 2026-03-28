<?php

namespace App\Notifications\Hr;

use App\Models\LeaveRequest;

class UpcomingLeaveReminder extends BaseHrNotification
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
        return 'Leave Starts Tomorrow';
    }

    protected function body(): string
    {
        $type = $this->leaveRequest->leaveType->name;
        $end = $this->leaveRequest->end_date->format('M j, Y');

        return "Reminder: Your {$type} starts tomorrow until {$end}.";
    }

    protected function actionUrl(): string
    {
        return '/hr/my/leave';
    }

    protected function icon(): string
    {
        return 'calendar';
    }
}
