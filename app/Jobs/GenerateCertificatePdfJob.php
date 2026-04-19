<?php

namespace App\Jobs;

use App\Models\CertificateIssue;
use App\Models\User;
use App\Services\CertificatePdfGenerator;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateCertificatePdfJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 120;

    public function __construct(
        public int $certificateIssueId,
        public ?int $actorUserId = null,
        public string $logAction = 'issued',
    ) {}

    public function handle(CertificatePdfGenerator $pdfGenerator): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $issue = CertificateIssue::with(['certificate', 'student.user', 'enrollment'])
            ->find($this->certificateIssueId);

        if (! $issue || ! $issue->certificate) {
            Log::warning('GenerateCertificatePdfJob: issue or certificate missing', [
                'issue_id' => $this->certificateIssueId,
            ]);

            return;
        }

        $issue->deleteFile();

        $data = $issue->data_snapshot ?? [];
        $data['certificate_number'] = $issue->certificate_number;

        try {
            $data['verification_url'] = $issue->getVerificationUrl();
        } catch (\Throwable $e) {
            $data['verification_url'] = '';
        }

        $filePath = $pdfGenerator->generate(
            certificate: $issue->certificate,
            student: $issue->student,
            enrollment: $issue->enrollment,
            additionalData: $data
        );

        $issue->update(['file_path' => $filePath]);

        $actor = $this->actorUserId ? User::find($this->actorUserId) : null;
        $issue->logAction($this->logAction, $actor);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateCertificatePdfJob failed', [
            'issue_id' => $this->certificateIssueId,
            'error' => $exception->getMessage(),
        ]);
    }
}
