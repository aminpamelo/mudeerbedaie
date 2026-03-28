<?php

namespace App\Notifications\Hr;

use App\Models\PayrollRun;

class PayrollRunCreated extends BaseHrNotification
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
        return 'Payroll Run Created';
    }

    protected function body(): string
    {
        $period = $this->payrollRun->month.'/'.$this->payrollRun->year;

        return "{$period} payroll run has been created.";
    }

    protected function actionUrl(): string
    {
        return '/hr/payroll';
    }

    protected function icon(): string
    {
        return 'dollar-sign';
    }
}
