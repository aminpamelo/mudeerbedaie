<?php

namespace App\Jobs;

use App\Mail\CertificateSent;
use App\Models\CertificateIssue;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendCertificateEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public int $certificateIssueId,
        public string $recipientEmail,
        public string $recipientName,
        public string $customMessage,
        public int $sentByUserId
    ) {}

    public function handle(): void
    {
        $issue = CertificateIssue::with(['certificate', 'student.user'])->find($this->certificateIssueId);

        if (! $issue || ! $issue->hasFile()) {
            Log::warning('SendCertificateEmailJob: Issue not found or no PDF', [
                'issue_id' => $this->certificateIssueId,
            ]);

            return;
        }

        Mail::to($this->recipientEmail, $this->recipientName)
            ->send(new CertificateSent($issue, $this->customMessage));

        $sentBy = User::find($this->sentByUserId);
        $issue->logAction('sent_email', $sentBy, [
            'status' => 'sent',
            'email' => $this->recipientEmail,
        ]);

        Log::info('Certificate email sent', [
            'issue_id' => $this->certificateIssueId,
            'email' => $this->recipientEmail,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendCertificateEmailJob failed', [
            'issue_id' => $this->certificateIssueId,
            'email' => $this->recipientEmail,
            'error' => $exception->getMessage(),
        ]);

        $issue = CertificateIssue::find($this->certificateIssueId);
        if ($issue) {
            $sentBy = User::find($this->sentByUserId);
            $issue->logAction('sent_email', $sentBy, [
                'status' => 'failed',
                'email' => $this->recipientEmail,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
