<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SendWhatsAppNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 300; // 5 minutes between retries

    public function __construct(
        public string $phoneNumber,
        public string $message,
        public ?int $notificationLogId = null,
        public ?string $imagePath = null
    ) {}

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        // Ensure only one WhatsApp message is sent at a time
        return [
            new WithoutOverlapping('whatsapp-send'),
        ];
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addHours(24);
    }

    public function handle(WhatsAppService $whatsApp): void
    {
        // Check if we can send now (time restrictions + daily limit)
        if (! $whatsApp->canSendNow()) {
            $nextAllowedTime = $whatsApp->getNextAllowedSendTime();

            if ($nextAllowedTime) {
                // Re-queue for the next allowed time
                $delaySeconds = now()->diffInSeconds($nextAllowedTime);
                $this->release($delaySeconds);

                Log::info('WhatsApp job delayed - outside allowed hours', [
                    'phone' => $this->phoneNumber,
                    'next_allowed' => $nextAllowedTime->toDateTimeString(),
                ]);

                return;
            }

            // Daily limit reached - try again tomorrow
            $tomorrowStart = now()->addDay()->setTime(
                config('services.onsend.send_hours_start', 8),
                0,
                0
            );
            $delaySeconds = now()->diffInSeconds($tomorrowStart);
            $this->release($delaySeconds);

            Log::warning('WhatsApp job delayed - daily limit reached', [
                'phone' => $this->phoneNumber,
                'next_attempt' => $tomorrowStart->toDateTimeString(),
            ]);

            return;
        }

        // Check if batch pause is active
        $pauseUntil = Cache::get('whatsapp_batch_pause_until');
        if ($pauseUntil && now()->lt($pauseUntil)) {
            $delaySeconds = now()->diffInSeconds($pauseUntil);
            $this->release($delaySeconds);

            Log::info('WhatsApp job delayed - batch pause active', [
                'phone' => $this->phoneNumber,
                'pause_until' => $pauseUntil->toDateTimeString(),
            ]);

            return;
        }

        // Send image first if provided
        $imageResult = null;
        if ($this->imagePath) {
            $imageUrl = Storage::disk('public')->url($this->imagePath);
            $imageResult = $whatsApp->sendImage($this->phoneNumber, $imageUrl);

            if (! $imageResult['success']) {
                Log::warning('WhatsApp image send failed, continuing with text', [
                    'phone' => $this->phoneNumber,
                    'error' => $imageResult['error'] ?? 'Unknown error',
                ]);
            } else {
                // Add small delay between image and text message
                sleep(random_int(2, 5));
            }
        }

        // Send the text message
        $result = $whatsApp->send($this->phoneNumber, $this->message);

        // Update notification log if provided
        if ($this->notificationLogId) {
            $log = NotificationLog::find($this->notificationLogId);
            if ($log) {
                // Consider success if text was sent (image failure is logged separately)
                if ($result['success']) {
                    $log->markAsSent($result['message_id'] ?? null);
                } else {
                    $log->markAsFailed($result['error'] ?? 'Unknown error');
                }
            }
        }

        // Check if we should trigger a batch pause
        if ($whatsApp->shouldPauseBatch()) {
            $pauseDuration = $whatsApp->getBatchPauseDuration();
            Cache::put('whatsapp_batch_pause_until', now()->addSeconds($pauseDuration));

            Log::info('WhatsApp batch pause triggered', [
                'pause_seconds' => $pauseDuration,
            ]);
        }

        // Add a small delay before allowing next job (in addition to random delay in dispatch)
        sleep(random_int(1, 3));
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendWhatsAppNotificationJob failed', [
            'phone' => $this->phoneNumber,
            'notification_log_id' => $this->notificationLogId,
            'error' => $exception->getMessage(),
        ]);

        // Update notification log if provided
        if ($this->notificationLogId) {
            $log = NotificationLog::find($this->notificationLogId);
            if ($log && $log->isPending()) {
                $log->markAsFailed($exception->getMessage());
            }
        }
    }
}
