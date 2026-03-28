<?php

namespace App\Notifications\Hr;

class ClaimPendingReminder extends BaseHrNotification
{
    public function __construct(
        public int $pendingCount
    ) {}

    protected function channels(): array
    {
        return ['database', 'push'];
    }

    protected function title(): string
    {
        return 'Pending Claims';
    }

    protected function body(): string
    {
        return "You have {$this->pendingCount} pending claim(s) awaiting your approval.";
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
