<?php

namespace App\Notifications\Hr;

use App\Models\Employee;
use Illuminate\Support\Carbon;

class MissedClockIn extends BaseHrNotification
{
    public function __construct(
        public Employee $employee,
        public Carbon $date
    ) {}

    protected function channels(): array
    {
        return ['database', 'mail', 'push'];
    }

    protected function title(): string
    {
        return 'Missed Clock-In';
    }

    protected function body(): string
    {
        return "You didn't clock in on {$this->date->format('M j, Y')}. Please submit an attendance correction.";
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
