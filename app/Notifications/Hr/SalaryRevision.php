<?php

namespace App\Notifications\Hr;

use App\Models\EmployeeSalary;

class SalaryRevision extends BaseHrNotification
{
    public function __construct(
        public EmployeeSalary $salary
    ) {}

    protected function channels(): array
    {
        return ['database', 'mail'];
    }

    protected function title(): string
    {
        return 'Salary Update';
    }

    protected function body(): string
    {
        $effective = $this->salary->effective_from?->format('M j, Y') ?? 'immediately';

        return "Your salary has been updated, effective {$effective}.";
    }

    protected function actionUrl(): string
    {
        return '/hr/my/payslips';
    }

    protected function icon(): string
    {
        return 'trending-up';
    }
}
