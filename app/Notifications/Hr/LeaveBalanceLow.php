<?php

namespace App\Notifications\Hr;

use App\Models\LeaveBalance;

class LeaveBalanceLow extends BaseHrNotification
{
    public function __construct(
        public LeaveBalance $leaveBalance
    ) {}

    protected function channels(): array
    {
        return ['database', 'push'];
    }

    protected function title(): string
    {
        return 'Leave Balance Low';
    }

    protected function body(): string
    {
        $remaining = $this->leaveBalance->entitled_days - $this->leaveBalance->used_days - $this->leaveBalance->pending_days;
        $type = $this->leaveBalance->leaveType->name ?? 'Annual Leave';

        return "You have only {$remaining} day(s) of {$type} remaining.";
    }

    protected function actionUrl(): string
    {
        return '/hr/my/leave';
    }

    protected function icon(): string
    {
        return 'alert-triangle';
    }
}
