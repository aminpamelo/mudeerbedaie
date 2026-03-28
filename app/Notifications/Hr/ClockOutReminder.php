<?php

namespace App\Notifications\Hr;

class ClockOutReminder extends BaseHrNotification
{
    public function __construct(
        public string $shiftEnd
    ) {}

    protected function channels(): array
    {
        return ['database', 'push'];
    }

    protected function title(): string
    {
        return 'Clock-Out Reminder';
    }

    protected function body(): string
    {
        return "Your shift ends at {$this->shiftEnd}. Remember to clock out!";
    }

    protected function actionUrl(): string
    {
        return '/hr/clock';
    }

    protected function icon(): string
    {
        return 'clock';
    }
}
