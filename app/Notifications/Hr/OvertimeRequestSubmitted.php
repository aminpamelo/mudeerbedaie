<?php

namespace App\Notifications\Hr;

use App\Models\OvertimeRequest;

class OvertimeRequestSubmitted extends BaseHrNotification
{
    public function __construct(
        public OvertimeRequest $overtimeRequest
    ) {}

    protected function channels(): array
    {
        return ['database', 'mail', 'push'];
    }

    protected function title(): string
    {
        return 'New Overtime Request';
    }

    protected function body(): string
    {
        $name = $this->overtimeRequest->employee->full_name;
        $date = $this->overtimeRequest->requested_date->format('M j, Y');
        $hours = $this->overtimeRequest->estimated_hours;

        return "{$name} requested {$hours} hours overtime on {$date}.";
    }

    protected function actionUrl(): string
    {
        return '/hr/attendance/overtime';
    }

    protected function icon(): string
    {
        return 'timer';
    }
}
