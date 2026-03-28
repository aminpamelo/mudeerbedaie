<?php

namespace App\Notifications\Hr;

class TeamLeaveAlert extends BaseHrNotification
{
    public function __construct(
        public string $departmentName,
        public int $employeeCount,
        public string $date
    ) {}

    protected function channels(): array
    {
        return ['database', 'push'];
    }

    protected function title(): string
    {
        return 'Team Leave Alert';
    }

    protected function body(): string
    {
        return "{$this->employeeCount} employee(s) on leave today in {$this->departmentName}.";
    }

    protected function actionUrl(): string
    {
        return '/hr/leave/calendar';
    }

    protected function icon(): string
    {
        return 'users';
    }
}
