<?php

namespace App\Notifications\Hr;

use App\Models\PayrollRun;

class PayrollFinalized extends BaseHrNotification
{
    public function __construct(
        public PayrollRun $payrollRun
    ) {}

    protected function channels(): array
    {
        return ['database', 'mail', 'push'];
    }

    protected function title(): string
    {
        return 'Payroll Finalized';
    }

    protected function body(): string
    {
        $period = $this->payrollRun->month.'/'.$this->payrollRun->year;

        return "Your payslip for {$period} is now available.";
    }

    protected function actionUrl(): string
    {
        return '/hr/my/payslips';
    }

    protected function icon(): string
    {
        return 'banknote';
    }
}
