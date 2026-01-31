<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SendClassNotificationEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public int $notificationLogId,
        public string $recipientEmail,
        public string $recipientName,
        public string $subject,
        public string $htmlContent,
        public array $fileAttachmentIds = [],
    ) {}

    public function handle(): void
    {
        // Skip @example.com addresses
        if (str_ends_with(strtolower($this->recipientEmail), '@example.com')) {
            $log = NotificationLog::find($this->notificationLogId);
            if ($log) {
                $log->markAsSkipped('Skipped @example.com address');
            }

            Log::info('Email skipped for @example.com address', [
                'log_id' => $this->notificationLogId,
                'email' => $this->recipientEmail,
            ]);

            return;
        }

        $log = NotificationLog::find($this->notificationLogId);

        if (! $log) {
            Log::warning('SendClassNotificationEmailJob: NotificationLog not found', [
                'log_id' => $this->notificationLogId,
            ]);

            return;
        }

        // Load file attachments if any
        $fileAttachments = [];
        if (! empty($this->fileAttachmentIds)) {
            $fileAttachments = \App\Models\ClassNotificationAttachment::whereIn('id', $this->fileAttachmentIds)->get();
        }

        Mail::send([], [], function ($message) use ($fileAttachments) {
            $message->to($this->recipientEmail, $this->recipientName)
                ->subject($this->subject)
                ->html($this->htmlContent);

            foreach ($fileAttachments as $file) {
                if (Storage::disk($file->disk)->exists($file->file_path)) {
                    $message->attach($file->full_path, [
                        'as' => $file->file_name,
                        'mime' => $file->file_type,
                    ]);
                }
            }
        });

        $log->markAsSent();

        // Update parent notification sent count
        $scheduledNotification = $log->scheduledNotification;
        if ($scheduledNotification) {
            $scheduledNotification->increment('total_sent');
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendClassNotificationEmailJob failed', [
            'log_id' => $this->notificationLogId,
            'email' => $this->recipientEmail,
            'error' => $exception->getMessage(),
        ]);

        $log = NotificationLog::find($this->notificationLogId);
        if ($log) {
            $log->markAsFailed($exception->getMessage());

            $scheduledNotification = $log->scheduledNotification;
            if ($scheduledNotification) {
                $scheduledNotification->increment('total_failed');
            }
        }
    }
}
