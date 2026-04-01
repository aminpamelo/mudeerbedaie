<?php

namespace App\Notifications\Hr;

use App\Models\OvertimeClaimRequest;

class OvertimeClaimDecision extends BaseHrNotification
{
    public function __construct(
        public OvertimeClaimRequest $claim,
        public string $decision
    ) {}

    protected function channels(): array
    {
        return ['database', 'push'];
    }

    protected function title(): string
    {
        return 'OT Claim '.ucfirst($this->decision);
    }

    protected function body(): string
    {
        $date = $this->claim->claim_date->format('M j, Y');

        return "Your OT claim for {$date} has been {$this->decision}.";
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
