<?php

namespace App\Notifications\LiveHost;

use App\Models\LiveSession;

/**
 * "Your session recap is overdue" reminder, fired by the
 * livehost:send-recap-reminders command for ended sessions that still have no
 * uploaded proof a while after they finished.
 */
class RecapOverdueNotification extends BasePocketNotification
{
    public function __construct(public LiveSession $session) {}

    protected function channels(): array
    {
        return ['database', 'push'];
    }

    protected function title(): string
    {
        return 'Rekap sesi belum dihantar';
    }

    protected function body(): string
    {
        $label = $this->session->title ?: 'Sesi live';
        $date = $this->session->scheduled_start_at?->format('j M') ?? '';

        return trim("Sila muat naik bukti & analitik untuk {$label} {$date}.");
    }

    protected function actionUrl(): string
    {
        return route('live-host.sessions.show', $this->session);
    }
}
