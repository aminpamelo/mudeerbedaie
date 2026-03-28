<?php

namespace App\Notifications\Hr;

use App\Models\ClaimRequest;

class ClaimMarkedPaid extends BaseHrNotification
{
    public function __construct(
        public ClaimRequest $claimRequest
    ) {}

    protected function channels(): array
    {
        return ['database', 'push'];
    }

    protected function title(): string
    {
        return 'Claim Payment Processed';
    }

    protected function body(): string
    {
        $type = $this->claimRequest->claimType->name;
        $amount = number_format($this->claimRequest->approved_amount ?? $this->claimRequest->amount, 2);

        return "Your {$type} claim (RM {$amount}) has been paid.";
    }

    protected function actionUrl(): string
    {
        return '/hr/my/claims';
    }

    protected function icon(): string
    {
        return 'banknote';
    }
}
