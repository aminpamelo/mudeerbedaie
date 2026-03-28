<?php

namespace App\Notifications\Hr;

use App\Models\PayrollRun;

class PayrollApproved extends BaseHrNotification
{
    public function __construct(
        public PayrollRun $payrollRun
    ) {}

    protected function channels(): array
    {
        return ['database', 'push'];
    }

    protected function title(): string
    {
        return 'Payroll Approved';
    }

    protected function body(): string
    {
        $period = $this->payrollRun->month.'/'.$this->payrollRun->year;

        return "{$period} payroll has been approved.";
    }

    protected function actionUrl(): string
    {
        return '/hr/payroll';
    }

    protected function icon(): string
    {
        return 'check-circle';
    }
}
