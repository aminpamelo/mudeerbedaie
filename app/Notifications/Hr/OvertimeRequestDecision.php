<?php

namespace App\Notifications\Hr;

use App\Models\OvertimeRequest;

class OvertimeRequestDecision extends BaseHrNotification
{
    public function __construct(
        public OvertimeRequest $overtimeRequest,
        public string $decision
    ) {}

    protected function channels(): array
    {
        return ['database', 'push'];
    }

    protected function title(): string
    {
        return 'Overtime Request '.ucfirst($this->decision);
    }

    protected function body(): string
    {
        $date = $this->overtimeRequest->requested_date->format('M j, Y');

        return "Your overtime request for {$date} has been {$this->decision}.";
    }

    protected function actionUrl(): string
    {
        return '/hr/my/overtime';
    }

    protected function icon(): string
    {
        return $this->decision === 'approved' ? 'check-circle' : 'x-circle';
    }
}
