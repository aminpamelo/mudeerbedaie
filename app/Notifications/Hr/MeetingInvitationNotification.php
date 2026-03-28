<?php

namespace App\Notifications\Hr;

use App\Models\Meeting;

class MeetingInvitationNotification extends BaseHrNotification
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
        return 'Meeting Invitation';
    }

    protected function body(): string
    {
        $title = $this->meeting->title;
        $date = $this->meeting->meeting_date->format('M j, Y');
        $time = $this->meeting->start_time;

        return "You've been invited to '{$title}' on {$date} at {$time}.";
    }

    protected function actionUrl(): string
    {
        return '/hr/meetings/'.$this->meeting->id;
    }

    protected function icon(): string
    {
        return 'calendar';
    }
}
