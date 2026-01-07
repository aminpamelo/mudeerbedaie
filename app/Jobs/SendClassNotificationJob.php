<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Models\ScheduledNotification;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendClassNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public ScheduledNotification $scheduledNotification
    ) {}

    public function handle(NotificationService $notificationService): void
    {
        $notification = $this->scheduledNotification;
        $setting = $notification->setting;
        $session = $notification->session;
        $class = $notification->class;

        // Validate notification has required data
        if (! $setting) {
            $notification->markAsFailed('Missing notification settings');

            return;
        }

        // For session-based notifications, require session
        // For timetable-based, require class and scheduled date/time
        $isTimetableBased = $notification->scheduled_session_date && ! $notification->session_id;

        if (! $isTimetableBased && ! $session) {
            $notification->markAsFailed('Missing session for session-based notification');

            return;
        }

        if ($isTimetableBased && ! $class) {
            $notification->markAsFailed('Missing class for timetable-based notification');

            return;
        }

        // Mark as processing
        $notification->markAsProcessing();

        $recipients = $notificationService->getRecipients($setting);
        $totalSent = 0;
        $totalFailed = 0;

        $subject = $setting->getEffectiveSubject();
        $content = $setting->getEffectiveContent();

        if (! $subject || ! $content) {
            $notification->markAsFailed('Missing subject or content template');

            return;
        }

        foreach ($recipients as $recipient) {
            try {
                // Replace placeholders for this recipient
                if ($isTimetableBased) {
                    // Use timetable-based placeholder replacement
                    $personalizedSubject = $notificationService->replacePlaceholdersForTimetable(
                        $subject,
                        $class,
                        Carbon::parse($notification->scheduled_session_date),
                        $notification->scheduled_session_time,
                        $recipient['type'] === 'student' ? $recipient['model'] : null,
                        $recipient['type'] === 'teacher' ? $recipient['model'] : null
                    );

                    $personalizedContent = $notificationService->replacePlaceholdersForTimetable(
                        $content,
                        $class,
                        Carbon::parse($notification->scheduled_session_date),
                        $notification->scheduled_session_time,
                        $recipient['type'] === 'student' ? $recipient['model'] : null,
                        $recipient['type'] === 'teacher' ? $recipient['model'] : null
                    );
                } else {
                    // Use session-based placeholder replacement
                    $personalizedSubject = $notificationService->replacePlaceholders(
                        $subject,
                        $session,
                        $recipient['type'] === 'student' ? $recipient['model'] : null,
                        $recipient['type'] === 'teacher' ? $recipient['model'] : null
                    );

                    $personalizedContent = $notificationService->replacePlaceholders(
                        $content,
                        $session,
                        $recipient['type'] === 'student' ? $recipient['model'] : null,
                        $recipient['type'] === 'teacher' ? $recipient['model'] : null
                    );
                }

                // Convert markdown to HTML for email
                $htmlContent = $this->convertMarkdownToHtml($personalizedContent);

                // Create log entry
                $log = NotificationLog::create([
                    'scheduled_notification_id' => $notification->id,
                    'recipient_type' => $recipient['type'],
                    'recipient_id' => $recipient['model']->id,
                    'channel' => 'email',
                    'destination' => $recipient['email'],
                    'status' => 'pending',
                ]);

                // Send email
                Mail::html($htmlContent, function ($message) use ($recipient, $personalizedSubject) {
                    $message->to($recipient['email'], $recipient['name'])
                        ->subject($personalizedSubject);
                });

                $log->markAsSent();
                $totalSent++;

            } catch (\Exception $e) {
                Log::error('Failed to send class notification', [
                    'notification_id' => $notification->id,
                    'recipient' => $recipient['email'],
                    'error' => $e->getMessage(),
                ]);

                if (isset($log)) {
                    $log->markAsFailed($e->getMessage());
                }
                $totalFailed++;
            }
        }

        // Update notification stats
        $notification->update([
            'total_sent' => $totalSent,
            'total_failed' => $totalFailed,
        ]);

        // Determine final status
        if ($totalFailed === $recipients->count()) {
            $notification->markAsFailed('All recipients failed');
        } else {
            $notification->markAsSent();
        }
    }

    private function convertMarkdownToHtml(string $markdown): string
    {
        // Simple markdown to HTML conversion
        $html = $markdown;

        // Bold: **text** -> <strong>text</strong>
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);

        // Links: [text](url) -> <a href="url">text</a>
        $html = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2">$1</a>', $html);

        // Line breaks
        $html = nl2br($html);

        // Wrap in basic email styling
        return <<<HTML
        <div style="font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #333;">
            {$html}
        </div>
        HTML;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendClassNotificationJob failed', [
            'notification_id' => $this->scheduledNotification->id,
            'error' => $exception->getMessage(),
        ]);

        $this->scheduledNotification->markAsFailed($exception->getMessage());
    }
}
