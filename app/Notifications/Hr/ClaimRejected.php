<?php

namespace App\Notifications\Hr;

use App\Models\ClaimRequest;

class ClaimRejected extends BaseHrNotification
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
        return 'Claim Rejected';
    }

    protected function body(): string
    {
        $type = $this->claimRequest->claimType->name;
        $reason = $this->claimRequest->rejection_reason ?? $this->claimRequest->rejected_reason ?? 'No reason provided';

        return "Your {$type} claim was rejected. Reason: {$reason}";
    }

    protected function actionUrl(): string
    {
        return '/hr/my/claims';
    }

    protected function icon(): string
    {
        return 'x-circle';
    }
}
