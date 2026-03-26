<?php

namespace App\Jobs;

use App\Models\CertificateIssue;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SendCertificateWhatsAppJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 300;

    public function __construct(
        public int $certificateIssueId,
        public string $phoneNumber,
        public string $customMessage,
        public int $sentByUserId
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            new WithoutOverlapping('whatsapp-send'),
        ];
    }

    public function retryUntil(): \DateTime
    {
        return now()->addHours(24);
    }

    public function handle(WhatsAppService $whatsApp): void
    {
        if (! $whatsApp->canSendNow()) {
            $nextAllowedTime = $whatsApp->getNextAllowedSendTime();

            if ($nextAllowedTime) {
                $this->release(now()->diffInSeconds($nextAllowedTime));

                return;
            }

            $tomorrowStart = now()->addDay()->setTime(
                config('services.onsend.send_hours_start', 8),
                0,
                0
            );
            $this->release(now()->diffInSeconds($tomorrowStart));

            return;
        }

        $pauseUntil = Cache::get('whatsapp_batch_pause_until');
        if ($pauseUntil && now()->lt($pauseUntil)) {
            $this->release(now()->diffInSeconds($pauseUntil));

            return;
        }

        $issue = CertificateIssue::with(['certificate', 'student.user'])->find($this->certificateIssueId);

        if (! $issue || ! $issue->hasFile()) {
            Log::warning('SendCertificateWhatsAppJob: Issue not found or no PDF', [
                'issue_id' => $this->certificateIssueId,
            ]);

            return;
        }

        // Send the text message first
        $textResult = $whatsApp->send($this->phoneNumber, $this->customMessage);

        // Store text message in inbox
        $whatsApp->storeOutboundMessage(
            phoneNumber: $this->phoneNumber,
            type: 'text',
            body: $this->customMessage,
            sendResult: $textResult,
            sentByUserId: $this->sentByUserId,
        );

        // Small delay between text and document
        sleep(random_int(2, 5));

        // Send the PDF document
        $documentUrl = Storage::disk('public')->url($issue->file_path);
        $filename = $issue->getDownloadFilename();
        $docResult = $whatsApp->sendDocument($this->phoneNumber, $documentUrl, 'application/pdf', $filename);

        // Store document message in inbox
        $whatsApp->storeOutboundMessage(
            phoneNumber: $this->phoneNumber,
            type: 'document',
            body: $filename,
            sendResult: $docResult,
            sentByUserId: $this->sentByUserId,
            mediaUrl: $documentUrl,
            mediaMimeType: 'application/pdf',
            mediaFilename: $filename,
        );

        $sentBy = User::find($this->sentByUserId);

        if ($docResult['success']) {
            $issue->logAction('sent_whatsapp', $sentBy, [
                'status' => 'sent',
                'phone' => $this->phoneNumber,
                'message_id' => $docResult['message_id'] ?? null,
            ]);

            Log::info('Certificate WhatsApp sent', [
                'issue_id' => $this->certificateIssueId,
                'phone' => $this->phoneNumber,
            ]);
        } else {
            $issue->logAction('sent_whatsapp', $sentBy, [
                'status' => 'failed',
                'phone' => $this->phoneNumber,
                'error' => $docResult['error'] ?? 'Unknown error',
            ]);

            Log::warning('Certificate WhatsApp document send failed', [
                'issue_id' => $this->certificateIssueId,
                'phone' => $this->phoneNumber,
                'error' => $docResult['error'] ?? 'Unknown error',
            ]);
        }

        // Check if we should trigger a batch pause
        if ($whatsApp->shouldPauseBatch()) {
            $pauseDuration = $whatsApp->getBatchPauseDuration();
            Cache::put('whatsapp_batch_pause_until', now()->addSeconds($pauseDuration));
        }

        sleep(random_int(1, 3));
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendCertificateWhatsAppJob failed', [
            'issue_id' => $this->certificateIssueId,
            'phone' => $this->phoneNumber,
            'error' => $exception->getMessage(),
        ]);

        $issue = CertificateIssue::find($this->certificateIssueId);
        if ($issue) {
            $sentBy = User::find($this->sentByUserId);
            $issue->logAction('sent_whatsapp', $sentBy, [
                'status' => 'failed',
                'phone' => $this->phoneNumber,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
