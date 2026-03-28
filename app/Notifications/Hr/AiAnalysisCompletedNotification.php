<?php

namespace App\Notifications\Hr;

use App\Models\Meeting;

class AiAnalysisCompletedNotification extends BaseHrNotification
{
    public function __construct(
        public Meeting $meeting
    ) {}

    protected function channels(): array
    {
        return ['database', 'push'];
    }

    protected function title(): string
    {
        return 'AI Analysis Complete';
    }

    protected function body(): string
    {
        $title = $this->meeting->title;

        return "AI analysis for '{$title}' is ready for review.";
    }

    protected function actionUrl(): string
    {
        return '/hr/meetings/'.$this->meeting->id;
    }

    protected function icon(): string
    {
        return 'sparkles';
    }
}
