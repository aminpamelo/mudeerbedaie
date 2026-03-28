<?php

namespace App\Notifications\Hr;

use App\Models\Meeting;

class MeetingCancelledNotification extends BaseHrNotification
{
    public function __construct(
        public Meeting $meeting
    ) {}

    protected function channels(): array
    {
        return ['database', 'mail', 'push'];
    }

    protected function title(): string
    {
        return 'Meeting Cancelled';
    }

    protected function body(): string
    {
        $title = $this->meeting->title;
        $date = $this->meeting->meeting_date->format('M j, Y');

        return "Meeting '{$title}' scheduled for {$date} has been cancelled.";
    }

    protected function actionUrl(): string
    {
        return '/hr/meetings/'.$this->meeting->id;
    }

    protected function icon(): string
    {
        return 'calendar-x';
    }
}
