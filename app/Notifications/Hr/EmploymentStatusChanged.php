<?php

namespace App\Notifications\Hr;

use App\Models\Employee;

class EmploymentStatusChanged extends BaseHrNotification
{
    public function __construct(
        public Employee $employee,
        public string $newStatus
    ) {}

    protected function channels(): array
    {
        return ['database', 'mail'];
    }

    protected function title(): string
    {
        return 'Employment Status Updated';
    }

    protected function body(): string
    {
        return "Your employment status has been changed to '{$this->newStatus}'.";
    }

    protected function actionUrl(): string
    {
        return '/hr/my/profile';
    }

    protected function icon(): string
    {
        return 'user-check';
    }
}
