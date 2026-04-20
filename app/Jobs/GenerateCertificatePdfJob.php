<?php

namespace App\Jobs;

use App\Models\CertificateIssue;
use App\Models\User;
use App\Services\CertificatePdfGenerator;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Generates one certificate PDF.
 *
 * Recommended queue worker command to run this safely on constrained hosts:
 *   php artisan queue:work --memory=512 --max-jobs=50 --timeout=120
 *
 * --memory=512 lets the DomPDF image pipeline breathe (cert templates often
 * embed multi-megabyte background images). --max-jobs=50 restarts the worker
 * periodically so any DomPDF memory that PHP's GC can't reclaim is released.
 */
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
        // DomPDF with base64-embedded images can spike well past 128MB per render.
        // Bump the runtime limit for the duration of this job.
        @ini_set('memory_limit', '512M');

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

        try {
            $filePath = $pdfGenerator->generate(
                certificate: $issue->certificate,
                student: $issue->student,
                enrollment: $issue->enrollment,
                additionalData: $data
            );

            $issue->update(['file_path' => $filePath]);

            $actor = $this->actorUserId ? User::find($this->actorUserId) : null;
            $issue->logAction($this->logAction, $actor);
        } finally {
            // Release internal DomPDF buffers between jobs under the same worker.
            unset($issue, $data, $pdfGenerator);
            gc_collect_cycles();
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateCertificatePdfJob failed', [
            'issue_id' => $this->certificateIssueId,
            'error' => $exception->getMessage(),
        ]);
    }
}
