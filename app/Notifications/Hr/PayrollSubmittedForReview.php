<?php

namespace App\Notifications\Hr;

use App\Models\PayrollRun;

class PayrollSubmittedForReview extends BaseHrNotification
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
        return 'Payroll Ready for Review';
    }

    protected function body(): string
    {
        $period = $this->payrollRun->month.'/'.$this->payrollRun->year;

        return "{$period} payroll is ready for your review.";
    }

    protected function actionUrl(): string
    {
        return '/hr/payroll';
    }

    protected function icon(): string
    {
        return 'clipboard-check';
    }
}
