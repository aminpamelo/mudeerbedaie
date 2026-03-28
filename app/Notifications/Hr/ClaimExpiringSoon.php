<?php

namespace App\Notifications\Hr;

use App\Models\ClaimRequest;

class ClaimExpiringSoon extends BaseHrNotification
{
    public function __construct(
        public ClaimRequest $claimRequest,
        public int $daysLeft
    ) {}

    protected function channels(): array
    {
        return ['database', 'push'];
    }

    protected function title(): string
    {
        return 'Claim Receipt Expiring';
    }

    protected function body(): string
    {
        $type = $this->claimRequest->claimType->name ?? 'claim';

        return "Your {$type} claim receipt expires in {$this->daysLeft} day(s).";
    }

    protected function actionUrl(): string
    {
        return '/hr/my/claims';
    }

    protected function icon(): string
    {
        return 'alert-triangle';
    }
}
