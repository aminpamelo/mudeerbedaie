<?php

namespace App\Notifications\Hr;

use App\Models\EmployeeSchedule;

class ScheduleChanged extends BaseHrNotification
{
    public function __construct(
        public EmployeeSchedule $employeeSchedule
    ) {}

    protected function channels(): array
    {
        return ['database', 'mail', 'push'];
    }

    protected function title(): string
    {
        return 'Work Schedule Changed';
    }

    protected function body(): string
    {
        $scheduleName = $this->employeeSchedule->workSchedule?->name ?? 'a new schedule';
        $effective = $this->employeeSchedule->effective_from?->format('M j, Y') ?? 'immediately';

        return "Your work schedule has been changed to '{$scheduleName}' effective {$effective}.";
    }

    protected function actionUrl(): string
    {
        return '/hr/clock';
    }

    protected function icon(): string
    {
        return 'calendar-clock';
    }
}
