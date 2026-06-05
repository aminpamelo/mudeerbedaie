<?php

namespace App\Console\Commands;

use App\Models\LiveSession;
use App\Notifications\LiveHost\SessionStartingSoonNotification;
use Illuminate\Console\Command;

/**
 * Pushes a "your session starts soon" reminder to hosts ~15 minutes before a
 * scheduled session. Scheduled every five minutes; the reminder_15m_sent_at
 * flag dedupes so a session is only ever reminded once.
 */
class SendSessionRemindersCommand extends Command
{
    protected $signature = 'livehost:send-session-reminders {--minutes=15 : Lead time before start, in minutes}';

    protected $description = 'Notify live hosts shortly before their scheduled sessions begin.';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $now = now();
        $threshold = $now->copy()->addMinutes($minutes);
        $sent = 0;

        LiveSession::query()
            ->where('status', 'scheduled')
            ->whereNotNull('live_host_id')
            ->whereNull('reminder_15m_sent_at')
            ->where('scheduled_start_at', '>', $now)
            ->where('scheduled_start_at', '<=', $threshold)
            ->with(['liveHost', 'platformAccount'])
            ->each(function (LiveSession $session) use ($minutes, &$sent): void {
                if ($session->liveHost === null) {
                    return;
                }

                $session->liveHost->notify(new SessionStartingSoonNotification($session, $minutes));
                $session->forceFill(['reminder_15m_sent_at' => now()])->save();
                $sent++;
            });

        $this->info("Sent {$sent} session reminder(s).");

        return self::SUCCESS;
    }
}
