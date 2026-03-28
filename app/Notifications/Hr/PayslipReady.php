<?php

namespace App\Notifications\Hr;

use App\Models\PayrollItem;

class PayslipReady extends BaseHrNotification
{
    public function __construct(
        public PayrollItem $payrollItem
    ) {}

    protected function channels(): array
    {
        return ['database', 'mail'];
    }

    protected function title(): string
    {
        return 'Your Payslip is Ready';
    }

    protected function body(): string
    {
        $period = $this->payrollItem->payrollRun->month.'/'.$this->payrollItem->payrollRun->year;

        return "Your payslip for {$period} is ready to download.";
    }

    protected function actionUrl(): string
    {
        return '/hr/my/payslips';
    }

    protected function icon(): string
    {
        return 'file-text';
    }
}
