<?php

namespace App\Notifications\Hr;

use App\Models\LeaveRequest;
use App\Models\User;

class LeaveRequestRejected extends BaseHrNotification
{
    public function __construct(
        public LeaveRequest $leaveRequest,
        public User $rejector
    ) {}

    protected function channels(): array
    {
        return ['database', 'mail', 'push'];
    }

    protected function title(): string
    {
        return 'Leave Request Rejected';
    }

    protected function body(): string
    {
        $type = $this->leaveRequest->leaveType->name;
        $reason = $this->leaveRequest->rejection_reason ?? 'No reason provided';

        return "Your {$type} request was rejected. Reason: {$reason}";
    }

    protected function actionUrl(): string
    {
        return '/hr/my/leave';
    }

    protected function icon(): string
    {
        return 'x-circle';
    }
}
