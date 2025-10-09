<?php

namespace App\Console\Commands;

use App\Models\CertificateIssue;
use App\Services\CertificatePdfGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RegenerateCertificates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'certificates:regenerate {--all : Regenerate all certificates}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate certificate PDFs with correct file paths and background images';

    /**
     * Execute the console command.
     */
    public function handle(CertificatePdfGenerator $pdfGenerator)
    {
        $query = CertificateIssue::with(['certificate', 'student.user', 'enrollment']);

        if ($this->option('all')) {
            $issues = $query->get();
        } else {
            // Only regenerate certificates with CERT-TEMP file path
            $issues = $query->where('file_path', 'like', '%CERT-TEMP%')->get();
        }

        if ($issues->isEmpty()) {
            $this->info('No certificates need regeneration.');

            return 0;
        }

        $this->info("Found {$issues->count()} certificate(s) to regenerate...");

        $bar = $this->output->createProgressBar($issues->count());
        $bar->start();

        $successCount = 0;
        $failCount = 0;

        foreach ($issues as $issue) {
            try {
                // Delete old file if it exists
                if ($issue->file_path && Storage::disk('public')->exists($issue->file_path)) {
                    Storage::disk('public')->delete($issue->file_path);
                }

                // Prepare data from snapshot or generate fresh
                $data = $issue->data_snapshot ?? [];
                $data['certificate_number'] = $issue->certificate_number;

                // Set verification URL if route exists
                try {
                    $data['verification_url'] = $issue->getVerificationUrl();
                } catch (\Exception $e) {
                    $data['verification_url'] = '';
                }

                // Generate new PDF with correct file path
                $filePath = $pdfGenerator->generate(
                    certificate: $issue->certificate,
                    student: $issue->student,
                    enrollment: $issue->enrollment,
                    additionalData: $data
                );

                // Update file path in database
                $issue->update(['file_path' => $filePath]);

                $successCount++;
            } catch (\Exception $e) {
                $this->error("\nFailed to regenerate certificate {$issue->certificate_number}: {$e->getMessage()}");
                $failCount++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Regeneration complete!');
        $this->info("✓ Success: {$successCount}");
        if ($failCount > 0) {
            $this->error("✗ Failed: {$failCount}");
        }

        return 0;
    }
}
