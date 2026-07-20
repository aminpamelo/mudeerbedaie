<?php

namespace App\Console\Commands;

use App\Models\LiveSession;
use App\Notifications\LiveHost\RecapOverdueNotification;
use Illuminate\Console\Command;

/**
 * Pushes a "recap overdue" reminder to hosts whose ended sessions still have no
 * uploaded proof a while after they finished. Scheduled hourly; the
 * recap_reminder_sent_at flag dedupes so a session is only reminded once.
 *
 * "No proof" mirrors the Pocket's own definition (Schedule/Sessions UI): an
 * ended session with zero attachments.
 */
class SendRecapRemindersCommand extends Command
{
    protected $signature = 'livehost:send-recap-reminders {--hours=2 : How long after a session ends before nudging}';

    protected $description = 'Remind live hosts to submit recaps for ended sessions that have no uploaded proof.';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $cutoff = now()->subHours($hours);
        $sent = 0;

        LiveSession::query()
            ->where('status', 'ended')
            ->whereNotNull('live_host_id')
            // TikTok-recorded lives need no manual proof — don't nag hosts for them.
            ->where('gmv_source', '!=', 'tiktok_actual')
            ->whereNull('recap_reminder_sent_at')
            ->whereDoesntHave('attachments')
            ->where(function ($query) use ($cutoff): void {
                $query->where('actual_end_at', '<', $cutoff)
                    ->orWhere(function ($query) use ($cutoff): void {
                        $query->whereNull('actual_end_at')
                            ->where('scheduled_start_at', '<', $cutoff);
                    });
            })
            ->with('liveHost')
            ->each(function (LiveSession $session) use (&$sent): void {
                if ($session->liveHost === null) {
                    return;
                }

                $session->liveHost->notify(new RecapOverdueNotification($session));
                $session->forceFill(['recap_reminder_sent_at' => now()])->save();
                $sent++;
            });

        $this->info("Sent {$sent} recap reminder(s).");

        return self::SUCCESS;
    }
}
