<?php

namespace App\Notifications\Hr;

use App\Models\LeaveRequest;
use App\Models\User;

class LeaveRequestApproved extends BaseHrNotification
{
    public function __construct(
        public LeaveRequest $leaveRequest,
        public User $approver
    ) {}

    protected function channels(): array
    {
        return ['database', 'mail', 'push'];
    }

    protected function title(): string
    {
        return 'Leave Request Approved';
    }

    protected function body(): string
    {
        $type = $this->leaveRequest->leaveType->name;
        $start = $this->leaveRequest->start_date->format('M j');
        $end = $this->leaveRequest->end_date->format('M j, Y');

        return "Your {$type} request ({$start} - {$end}) has been approved by {$this->approver->name}.";
    }

    protected function actionUrl(): string
    {
        return '/hr/my/leave';
    }

    protected function icon(): string
    {
        return 'check-circle';
    }
}
