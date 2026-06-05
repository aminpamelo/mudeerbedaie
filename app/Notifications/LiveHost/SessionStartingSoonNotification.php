<?php

namespace App\Notifications\LiveHost;

use App\Models\LiveSession;

/**
 * "Your session starts soon" reminder, fired ~15 minutes before a scheduled
 * session's start by the livehost:send-session-reminders command.
 */
class SessionStartingSoonNotification extends BasePocketNotification
{
    public function __construct(
        public LiveSession $session,
        public int $minutesBefore = 15,
    ) {}

    protected function channels(): array
    {
        return ['database', 'push'];
    }

    protected function title(): string
    {
        return 'Sesi akan bermula sebentar lagi';
    }

    protected function body(): string
    {
        $this->session->loadMissing('platformAccount');
        $label = $this->session->title ?: ($this->session->platformAccount?->name ?? 'Sesi live');
        $time = $this->session->scheduled_start_at?->format('g:i A') ?? '';

        return trim("{$label} mula {$time} (~{$this->minutesBefore} minit lagi). Sedia untuk go-live!");
    }

    protected function actionUrl(): string
    {
        return route('live-host.go-live');
    }
}
