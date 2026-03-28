<?php

namespace App\Notifications\Hr;

use App\Models\ClaimRequest;

class ClaimSubmitted extends BaseHrNotification
{
    public function __construct(
        public ClaimRequest $claimRequest
    ) {}

    protected function channels(): array
    {
        return ['database', 'mail', 'push'];
    }

    protected function title(): string
    {
        return 'New Claim Submitted';
    }

    protected function body(): string
    {
        $name = $this->claimRequest->employee->full_name;
        $type = $this->claimRequest->claimType->name;
        $amount = number_format($this->claimRequest->amount, 2);

        return "{$name} submitted a {$type} claim for RM {$amount}.";
    }

    protected function actionUrl(): string
    {
        return '/hr/claims/requests';
    }

    protected function icon(): string
    {
        return 'receipt';
    }
}
