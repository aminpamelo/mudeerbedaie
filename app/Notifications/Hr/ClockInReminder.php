<?php

namespace App\Notifications\Hr;

class ClockInReminder extends BaseHrNotification
{
    public function __construct(
        public string $shiftStart
    ) {}

    protected function channels(): array
    {
        return ['database', 'push'];
    }

    protected function title(): string
    {
        return 'Clock-In Reminder';
    }

    protected function body(): string
    {
        return "Your shift starts at {$this->shiftStart}. Don't forget to clock in!";
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
