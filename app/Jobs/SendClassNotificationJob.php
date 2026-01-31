<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Models\ScheduledNotification;
use App\Services\EmailTemplateCompiler;
use App\Services\NotificationService;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

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

        // Check channel settings upfront
        $shouldSendEmail = $notificationService->shouldSendEmail($class, $setting);
        $shouldSendWhatsApp = $notificationService->shouldSendWhatsApp($class, $setting);

        // If both channels are disabled, skip processing
        if (! $shouldSendEmail && ! $shouldSendWhatsApp) {
            $notification->update([
                'total_sent' => 0,
                'total_failed' => 0,
            ]);
            $notification->markAsSent();
            Log::info('Notification skipped - both channels disabled', [
                'notification_id' => $notification->id,
                'class_id' => $class->id,
            ]);

            return;
        }

        // Collect file attachment IDs for email jobs
        $fileAttachmentIds = $fileAttachments->pluck('id')->toArray();

        $totalDispatched = 0;

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

                // Dispatch individual email job if enabled AND recipient has valid email address
                if ($shouldSendEmail && ! empty($recipient['email']) && ! str_ends_with(strtolower($recipient['email']), '@example.com')) {
                    // Create log entry
                    $log = NotificationLog::create([
                        'scheduled_notification_id' => $notification->id,
                        'recipient_type' => $recipient['type'],
                        'recipient_id' => $recipient['model']->id,
                        'channel' => 'email',
                        'destination' => $recipient['email'],
                        'status' => 'pending',
                    ]);

                    // Dispatch individual email job instead of sending synchronously
                    SendClassNotificationEmailJob::dispatch(
                        $log->id,
                        $recipient['email'],
                        $recipient['name'],
                        $personalizedSubject,
                        $htmlContent,
                        $fileAttachmentIds,
                    );

                    $totalDispatched++;
                }

                // Send WhatsApp notification if enabled and phone number available
                if ($shouldSendWhatsApp) {
                    $this->dispatchWhatsAppNotification(
                        $notification,
                        $setting,
                        $recipient,
                        $personalizedContent,
                        $isVisualTemplate,
                        $notificationService
                    );
                }

            } catch (\Exception $e) {
                Log::error('Failed to dispatch class notification', [
                    'notification_id' => $notification->id,
                    'recipient' => $recipient['email'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);

                if (isset($log)) {
                    $log->markAsFailed($e->getMessage());
                }
                $totalFailed++;
            }
        }

        // Initialize counters - individual email jobs will increment these as they complete
        $notification->update([
            'total_sent' => 0,
            'total_failed' => $totalFailed,
        ]);

        // Mark as sent (dispatched) - individual jobs handle their own success/failure
        if ($totalDispatched > 0 || $shouldSendWhatsApp) {
            $notification->markAsSent();
        } elseif ($totalFailed > 0) {
            $notification->markAsFailed('All recipients failed during dispatch');
        } else {
            $notification->markAsSent();
        }

        Log::info('Class notification dispatched', [
            'notification_id' => $notification->id,
            'total_email_jobs_dispatched' => $totalDispatched,
            'total_failed_during_dispatch' => $totalFailed,
        ]);
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

    /**
     * Convert HTML content to plain text suitable for WhatsApp.
     */
    private function convertHtmlToWhatsAppText(string $html): string
    {
        // Replace common HTML elements with appropriate text representations
        $text = $html;

        // Replace line break elements with newlines
        $text = str_replace(['<br>', '<br/>', '<br />', '<BR>', '<BR/>', '<BR />'], "\n", $text);

        // Replace closing block elements with double newlines
        $text = preg_replace('/<\/(p|div|h[1-6]|li|tr)>/i', "\n\n", $text);

        // Replace list items with bullets
        $text = preg_replace('/<li[^>]*>/i', '• ', $text);

        // Replace horizontal rules with dashes
        $text = preg_replace('/<hr[^>]*>/i', "\n---\n", $text);

        // Extract href from links and format as: text (url)
        $text = preg_replace('/<a[^>]*href=["\']([^"\']+)["\'][^>]*>([^<]+)<\/a>/i', '$2 ($1)', $text);

        // Remove all remaining HTML tags
        $text = strip_tags($text);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Clean up excessive whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text); // Multiple spaces to single
        $text = preg_replace('/\n\s*\n\s*\n/', "\n\n", $text); // Multiple newlines to double
        $text = trim($text);

        return $text;
    }

    /**
     * Dispatch WhatsApp notification job if phone available.
     * Note: Channel settings are already checked before calling this method.
     */
    private function dispatchWhatsAppNotification(
        ScheduledNotification $notification,
        $setting,
        array $recipient,
        string $personalizedContent,
        bool $isVisualTemplate,
        ?NotificationService $notificationService = null
    ): void {
        // Check if WhatsApp service is globally enabled (API level)
        $whatsApp = app(WhatsAppService::class);
        if (! $whatsApp->isEnabled()) {
            return;
        }

        // Check if recipient has a phone number
        $phone = $recipient['phone'] ?? null;
        if (empty($phone)) {
            Log::info('WhatsApp notification skipped - no phone number', [
                'notification_id' => $notification->id,
                'recipient_type' => $recipient['type'],
                'recipient_name' => $recipient['name'],
            ]);

            return;
        }

        try {
            // Determine WhatsApp content source
            if ($setting->hasCustomWhatsAppTemplate()) {
                // Use dedicated custom WhatsApp template
                $whatsAppContent = $setting->whatsapp_content;

                // Replace placeholders in WhatsApp content
                if ($notificationService) {
                    $student = $recipient['type'] === 'student' ? $recipient['model'] : null;
                    $teacher = $recipient['type'] === 'teacher' ? $recipient['model'] : null;
                    $session = $notification->session;
                    $class = $notification->class;
                    $isTimetableBased = $notification->scheduled_session_date && ! $notification->session_id;

                    // Extract session time
                    $sessionTime = $notification->scheduled_session_time;
                    if ($sessionTime && preg_match('/\d{4}-\d{2}-\d{2}\s+(\d{2}:\d{2}(:\d{2})?)/', $sessionTime, $matches)) {
                        $sessionTime = $matches[1];
                    }

                    if ($isTimetableBased) {
                        $whatsAppContent = $notificationService->replacePlaceholdersForTimetable(
                            $whatsAppContent,
                            $class,
                            Carbon::parse($notification->scheduled_session_date),
                            $sessionTime,
                            $student,
                            $teacher
                        );
                    } else {
                        $whatsAppContent = $notificationService->replacePlaceholders(
                            $whatsAppContent,
                            $session,
                            $student,
                            $teacher
                        );
                    }
                }

                Log::info('Using custom WhatsApp template', [
                    'notification_id' => $notification->id,
                    'setting_id' => $setting->id,
                ]);
            } else {
                // Fall back to converting email content to WhatsApp-friendly text
                $whatsAppContent = $isVisualTemplate
                    ? $this->convertHtmlToWhatsAppText($personalizedContent)
                    : $personalizedContent;
            }

            // Calculate random delay for this message (anti-ban measure)
            $delay = $whatsApp->getRandomDelay();

            // Create WhatsApp notification log
            $waLog = NotificationLog::create([
                'scheduled_notification_id' => $notification->id,
                'recipient_type' => $recipient['type'],
                'recipient_id' => $recipient['model']->id,
                'channel' => 'whatsapp',
                'destination' => $phone,
                'status' => 'pending',
            ]);

            // Get WhatsApp image path if exists
            $whatsAppImagePath = $setting->whatsapp_image_path;

            // Dispatch WhatsApp job with calculated delay
            SendWhatsAppNotificationJob::dispatch(
                $phone,
                $whatsAppContent,
                $waLog->id,
                $whatsAppImagePath
            )->delay(now()->addSeconds($delay))
                ->onQueue('whatsapp');

            Log::info('WhatsApp notification queued', [
                'notification_id' => $notification->id,
                'recipient_type' => $recipient['type'],
                'phone' => $phone,
                'delay_seconds' => $delay,
                'has_image' => ! empty($whatsAppImagePath),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to queue WhatsApp notification', [
                'notification_id' => $notification->id,
                'recipient_type' => $recipient['type'],
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
        }
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
