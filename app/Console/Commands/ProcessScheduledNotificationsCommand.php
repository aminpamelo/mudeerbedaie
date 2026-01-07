<?php

namespace App\Console\Commands;

use App\Jobs\SendClassNotificationJob;
use App\Models\ScheduledNotification;
use Illuminate\Console\Command;

class ProcessScheduledNotificationsCommand extends Command
{
    protected $signature = 'notifications:process {--limit=50 : Maximum notifications to process}';

    protected $description = 'Process and send due notifications';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $notifications = ScheduledNotification::readyToSend()
            ->with(['setting', 'session.class', 'class'])
            ->limit($limit)
            ->get();

        if ($notifications->isEmpty()) {
            $this->info('No notifications ready to process.');

            return Command::SUCCESS;
        }

        $this->info("Processing {$notifications->count()} notifications...");

        foreach ($notifications as $notification) {
            // Skip if setting no longer exists
            if (! $notification->setting) {
                $notification->cancel();
                $this->warn("Cancelled notification #{$notification->id} - missing settings");

                continue;
            }

            // For session-based notifications, check session exists and is valid
            if ($notification->session_id) {
                if (! $notification->session) {
                    $notification->cancel();
                    $this->warn("Cancelled notification #{$notification->id} - missing session");

                    continue;
                }

                // Skip if session was cancelled or rescheduled
                if (in_array($notification->session->status, ['cancelled', 'rescheduled'])) {
                    $notification->cancel();
                    $this->warn("Cancelled notification #{$notification->id} - session status: {$notification->session->status}");

                    continue;
                }
            } else {
                // For timetable-based notifications, ensure class and timetable still valid
                if (! $notification->class) {
                    $notification->cancel();
                    $this->warn("Cancelled notification #{$notification->id} - missing class");

                    continue;
                }

                // Check if timetable is still active
                if (! $notification->class->timetable || ! $notification->class->timetable->is_active) {
                    $notification->cancel();
                    $this->warn("Cancelled notification #{$notification->id} - timetable no longer active");

                    continue;
                }

                // Check if scheduled session date is past timetable's end_date
                $timetable = $notification->class->timetable;
                if ($timetable->end_date && $notification->scheduled_session_date) {
                    if ($notification->scheduled_session_date->gt($timetable->end_date)) {
                        $notification->cancel();
                        $this->warn("Cancelled notification #{$notification->id} - session date past timetable end date");

                        continue;
                    }
                }
            }

            SendClassNotificationJob::dispatch($notification);

            if ($notification->session_id) {
                $this->line("Dispatched notification #{$notification->id} for session #{$notification->session_id}");
            } else {
                $this->line("Dispatched notification #{$notification->id} for timetable slot {$notification->scheduled_session_date} {$notification->scheduled_session_time}");
            }
        }

        return Command::SUCCESS;
    }
}
