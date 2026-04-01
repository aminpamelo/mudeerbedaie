<?php

namespace App\Notifications\Hr;

use App\Models\OvertimeClaimRequest;

class OvertimeClaimSubmitted extends BaseHrNotification
{
    public function __construct(
        public OvertimeClaimRequest $claim
    ) {}

    protected function channels(): array
    {
        return ['database', 'push'];
    }

    protected function title(): string
    {
        return 'New OT Claim Request';
    }

    protected function body(): string
    {
        $name = $this->claim->employee->full_name;
        $date = $this->claim->claim_date->format('M j, Y');
        $mins = $this->claim->duration_minutes;

        return "{$name} submitted an OT claim for {$mins} minutes on {$date}.";
    }

    protected function actionUrl(): string
    {
        return '/hr/my/approvals/overtime';
    }

    protected function icon(): string
    {
        return 'timer';
    }
}
