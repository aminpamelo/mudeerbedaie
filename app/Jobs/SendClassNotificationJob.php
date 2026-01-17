<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Models\ScheduledNotification;
use App\Services\EmailTemplateCompiler;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SendClassNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public ScheduledNotification $scheduledNotification
    ) {}

    public function handle(NotificationService $notificationService, EmailTemplateCompiler $compiler): void
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

        // Check if class has custom template (visual or text) - these take priority
        $template = $setting->template;
        $hasCustomVisualTemplate = $setting->isVisualEditor() && $setting->html_content;
        $hasCustomTextTemplate = $setting->isTextEditor() && $setting->custom_content;

        // Determine content source with priority: class custom > system template
        if ($hasCustomVisualTemplate) {
            // Use class-level visual template
            $content = $setting->html_content;
            $isVisualTemplate = true;
        } elseif ($hasCustomTextTemplate) {
            // Use class-level text template
            $content = $setting->custom_content;
            $isVisualTemplate = false;
        } elseif ($template && $template->isVisualEditor() && $template->html_content) {
            // Fall back to system visual template
            $content = $template->html_content;
            $isVisualTemplate = true;
        } else {
            // Fall back to system text template
            $content = $setting->getEffectiveContent();
            $isVisualTemplate = false;
        }

        if (! $subject || ! $content) {
            $notification->markAsFailed('Missing subject or content template');

            return;
        }

        // Get attachments for this notification setting
        $attachments = $setting->attachments()->ordered()->get();
        $embeddedImages = $attachments->filter(fn ($a) => $a->isImage() && $a->embed_in_email);
        $fileAttachments = $attachments->filter(fn ($a) => ! $a->isImage() || ! $a->embed_in_email);

        // Extract session time - handle both formats:
        // - Time only: "18:00:00"
        // - Full datetime: "2026-01-10 18:00:00" (legacy data with datetime cast)
        $sessionTime = $notification->scheduled_session_time;
        if ($sessionTime && preg_match('/\d{4}-\d{2}-\d{2}\s+(\d{2}:\d{2}(:\d{2})?)/', $sessionTime, $matches)) {
            $sessionTime = $matches[1];
        }

        foreach ($recipients as $recipient) {
            try {
                $student = $recipient['type'] === 'student' ? $recipient['model'] : null;
                $teacher = $recipient['type'] === 'teacher' ? $recipient['model'] : null;

                // Replace placeholders for this recipient
                if ($isTimetableBased) {
                    // Use timetable-based placeholder replacement
                    $personalizedSubject = $notificationService->replacePlaceholdersForTimetable(
                        $subject,
                        $class,
                        Carbon::parse($notification->scheduled_session_date),
                        $sessionTime,
                        $student,
                        $teacher
                    );

                    // Use appropriate compiler based on template type
                    if ($isVisualTemplate) {
                        $personalizedContent = $compiler->replacePlaceholdersForTimetable(
                            $content,
                            $class,
                            Carbon::parse($notification->scheduled_session_date),
                            $sessionTime,
                            $student,
                            $teacher
                        );
                    } else {
                        $personalizedContent = $notificationService->replacePlaceholdersForTimetable(
                            $content,
                            $class,
                            Carbon::parse($notification->scheduled_session_date),
                            $sessionTime,
                            $student,
                            $teacher
                        );
                    }
                } else {
                    // Use session-based placeholder replacement
                    $personalizedSubject = $notificationService->replacePlaceholders(
                        $subject,
                        $session,
                        $student,
                        $teacher
                    );

                    if ($isVisualTemplate) {
                        $personalizedContent = $compiler->replacePlaceholders(
                            $content,
                            $session,
                            $student,
                            $teacher
                        );
                    } else {
                        $personalizedContent = $notificationService->replacePlaceholders(
                            $content,
                            $session,
                            $student,
                            $teacher
                        );
                    }
                }

                // Convert to HTML - visual templates are already HTML
                $htmlContent = $isVisualTemplate
                    ? $personalizedContent
                    : $this->convertMarkdownToHtml($personalizedContent);

                // Create log entry
                $log = NotificationLog::create([
                    'scheduled_notification_id' => $notification->id,
                    'recipient_type' => $recipient['type'],
                    'recipient_id' => $recipient['model']->id,
                    'channel' => 'email',
                    'destination' => $recipient['email'],
                    'status' => 'pending',
                ]);

                // Send email with attachments
                Mail::send([], [], function ($message) use ($recipient, $personalizedSubject, $htmlContent, $fileAttachments) {
                    $message->to($recipient['email'], $recipient['name'])
                        ->subject($personalizedSubject)
                        ->html($htmlContent);

                    // Attach files (PDFs, documents, non-embedded images)
                    foreach ($fileAttachments as $file) {
                        if (Storage::disk($file->disk)->exists($file->file_path)) {
                            $message->attach($file->full_path, [
                                'as' => $file->file_name,
                                'mime' => $file->file_type,
                            ]);
                        }
                    }

                    // Note: For embedded images in HTML emails, they should be referenced
                    // in the HTML content using absolute URLs from the storage
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
