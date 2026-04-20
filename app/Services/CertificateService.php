<?php

namespace App\Services;

use App\Jobs\GenerateCertificatePdfJob;
use App\Models\Certificate;
use App\Models\CertificateIssue;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CertificateService
{
    public function __construct(
        protected CertificatePdfGenerator $pdfGenerator
    ) {}

    /**
     * Get eligible students for certificate issuance in a class
     */
    public function getEligibleStudentsForClass(ClassModel $class, bool $onlyCompleted = false): Collection
    {
        $query = $class->activeStudents()->with('student.user');

        if ($onlyCompleted && $class->isCompleted()) {
            // Only include students who have attended required percentage of sessions
            // For now, we'll include all active students
            // You can add attendance requirements later
        }

        return $query->get()->pluck('student');
    }

    /**
     * Check if a certificate can be issued to a student for a class
     */
    public function canIssueToStudent(Certificate $certificate, Student $student, ClassModel $class): array
    {
        if (! $certificate->isActive()) {
            return [
                'can_issue' => false,
                'reason' => 'Certificate template is not active.',
            ];
        }

        // Check if student is enrolled in the class
        $isEnrolled = $class->activeStudents()
            ->where('student_id', $student->id)
            ->exists();

        if (! $isEnrolled) {
            return [
                'can_issue' => false,
                'reason' => 'Student is not enrolled in this class.',
            ];
        }

        // Check if already issued
        $existingIssue = CertificateIssue::where('certificate_id', $certificate->id)
            ->where('student_id', $student->id)
            ->where('class_id', $class->id)
            ->where('status', 'issued')
            ->exists();

        if ($existingIssue) {
            return [
                'can_issue' => false,
                'reason' => 'Certificate already issued to this student for this class.',
            ];
        }

        return [
            'can_issue' => true,
            'reason' => null,
        ];
    }

    /**
     * Issue a certificate to a single student (synchronous PDF generation).
     * Used for single-student flows; bulk flows should use issueToClass().
     */
    public function issueToStudent(
        Certificate $certificate,
        Student $student,
        ClassModel $class,
        ?int $enrollmentId = null
    ): array {
        $canIssue = $this->canIssueToStudent($certificate, $student, $class);

        if (! $canIssue['can_issue']) {
            return [
                'success' => false,
                'message' => $canIssue['reason'],
                'certificate_issue' => null,
            ];
        }

        try {
            $certificateIssue = DB::transaction(function () use ($certificate, $student, $class, $enrollmentId) {
                $certificateIssue = $this->createIssueRecord($certificate, $student, $class, $enrollmentId);

                $dataSnapshot = $certificateIssue->data_snapshot;
                $dataSnapshot['certificate_number'] = $certificateIssue->certificate_number;

                try {
                    $dataSnapshot['verification_url'] = $certificateIssue->getVerificationUrl();
                } catch (\Exception $e) {
                    $dataSnapshot['verification_url'] = '';
                }

                $enrollment = $enrollmentId ? Enrollment::find($enrollmentId) : null;

                // Generate PDF — if this fails, the transaction rolls back
                $pdfPath = $this->pdfGenerator->generate($certificate, $student, $enrollment, $dataSnapshot);
                $certificateIssue->update(['file_path' => $pdfPath]);

                return $certificateIssue;
            });

            return [
                'success' => true,
                'message' => 'Certificate issued successfully.',
                'certificate_issue' => $certificateIssue,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to issue certificate', [
                'certificate_id' => $certificate->id,
                'student_id' => $student->id,
                'class_id' => $class->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to issue certificate: '.$e->getMessage(),
                'certificate_issue' => null,
            ];
        }
    }

    /**
     * Bulk issue certificates to students in a class.
     * Creates CertificateIssue records synchronously, then queues PDF generation as a batch.
     *
     * @return array{success:bool,message:string,issued_count:int,skipped_count:int,failed_count:int,batch_id:?string,results:array}
     */
    public function issueToClass(
        Certificate $certificate,
        ClassModel $class,
        array $studentIds = [],
        bool $skipExisting = true
    ): array {
        if (! $certificate->isActive()) {
            return [
                'success' => false,
                'message' => 'Cannot issue certificates. Certificate template is not active.',
                'issued_count' => 0,
                'skipped_count' => 0,
                'failed_count' => 0,
                'batch_id' => null,
                'results' => [],
            ];
        }

        $students = empty($studentIds)
            ? $this->getEligibleStudentsForClass($class)
            : Student::whereIn('id', $studentIds)->get();

        $issuedIds = [];
        $skippedCount = 0;
        $failedCount = 0;
        $reissuedCount = 0;
        $results = [];

        foreach ($students as $student) {
            $canIssue = $this->canIssueToStudent($certificate, $student, $class);
            $alreadyIssued = ! $canIssue['can_issue'] && str_contains($canIssue['reason'], 'already issued');

            // Skip path: student already has a cert and the admin chose to skip them.
            if ($alreadyIssued && $skipExisting) {
                $skippedCount++;
                $results[] = [
                    'student_id' => $student->id,
                    'student_name' => $student->full_name,
                    'status' => 'skipped',
                    'reason' => $canIssue['reason'],
                ];

                continue;
            }

            // Fail path: any other blocker (not enrolled, template inactive, etc).
            if (! $canIssue['can_issue'] && ! $alreadyIssued) {
                $failedCount++;
                $results[] = [
                    'student_id' => $student->id,
                    'student_name' => $student->full_name,
                    'status' => 'failed',
                    'reason' => $canIssue['reason'],
                ];

                continue;
            }

            // Reissue path: student already has a cert and admin unchecked "skip". Delete the
            // existing issue(s) outright (incl. PDF files) and fall through to create a fresh record.
            $isReissue = false;
            if ($alreadyIssued) {
                $this->deleteExistingIssues($certificate, $student, $class);
                $isReissue = true;
            }

            try {
                $issue = $this->createIssueRecord($certificate, $student, $class);
                $issuedIds[] = $issue->id;

                if ($isReissue) {
                    $reissuedCount++;
                }

                $results[] = [
                    'student_id' => $student->id,
                    'student_name' => $student->full_name,
                    'status' => $isReissue ? 'reissued' : 'issued',
                    'certificate_issue_id' => $issue->id,
                ];
            } catch (\Exception $e) {
                Log::error('Failed to create certificate issue record', [
                    'certificate_id' => $certificate->id,
                    'student_id' => $student->id,
                    'class_id' => $class->id,
                    'error' => $e->getMessage(),
                ]);
                $failedCount++;
                $results[] = [
                    'student_id' => $student->id,
                    'student_name' => $student->full_name,
                    'status' => 'failed',
                    'reason' => $e->getMessage(),
                ];
            }
        }

        $batchId = null;
        if (! empty($issuedIds)) {
            $batchId = $this->dispatchPdfBatch(
                $issuedIds,
                $class,
                "Issue certificates for class #{$class->id}",
                'issued'
            );
        }

        $issuedCount = count($issuedIds);

        return [
            'success' => $issuedCount > 0,
            'message' => "Queued: {$issuedCount}, Reissued: {$reissuedCount}, Skipped: {$skippedCount}, Failed: {$failedCount}",
            'issued_count' => $issuedCount,
            'reissued_count' => $reissuedCount,
            'skipped_count' => $skippedCount,
            'failed_count' => $failedCount,
            'batch_id' => $batchId,
            'results' => $results,
        ];
    }

    /**
     * Hard-delete all existing CertificateIssue rows for a given student+certificate+class,
     * including their PDF files on disk. Used when reissuing without keeping audit history.
     */
    protected function deleteExistingIssues(Certificate $certificate, Student $student, ClassModel $class): void
    {
        CertificateIssue::where('certificate_id', $certificate->id)
            ->where('student_id', $student->id)
            ->where('class_id', $class->id)
            ->get()
            ->each(function (CertificateIssue $issue) {
                $issue->deleteFile();
                $issue->delete();
            });
    }

    /**
     * Queue PDF regeneration for a set of existing CertificateIssue records.
     *
     * @param  array<int>  $issueIds
     */
    public function dispatchRegenerationBatch(array $issueIds, ClassModel $class, string $name = 'Regenerate certificates'): ?string
    {
        if (empty($issueIds)) {
            return null;
        }

        return $this->dispatchPdfBatch($issueIds, $class, $name, 'regenerated');
    }

    /**
     * Get certificate issuance statistics for a class
     */
    public function getClassCertificateStats(ClassModel $class, ?Certificate $certificate = null): array
    {
        $totalStudents = $class->activeStudents()->count();

        $query = CertificateIssue::where('class_id', $class->id);

        if ($certificate) {
            $query->where('certificate_id', $certificate->id);
        }

        $issuedCount = $query->where('status', 'issued')->count();
        $revokedCount = $query->where('status', 'revoked')->count();
        $pendingCount = $totalStudents - $issuedCount;

        return [
            'total_students' => $totalStudents,
            'issued_count' => $issuedCount,
            'revoked_count' => $revokedCount,
            'pending_count' => max(0, $pendingCount),
            'completion_rate' => $totalStudents > 0 ? round(($issuedCount / $totalStudents) * 100, 2) : 0,
        ];
    }

    /**
     * Get default certificate for a class
     */
    public function getDefaultCertificateForClass(ClassModel $class): ?Certificate
    {
        // First check if class has a directly assigned default certificate
        $classCertificate = $class->certificates()
            ->wherePivot('is_default', true)
            ->active()
            ->first();

        if ($classCertificate) {
            return $classCertificate;
        }

        // Fall back to course default certificate
        $courseCertificate = $class->course->certificates()
            ->wherePivot('is_default', true)
            ->active()
            ->first();

        return $courseCertificate;
    }

    /**
     * Auto-issue certificates when class is completed
     */
    public function autoIssueOnCompletion(ClassModel $class): ?array
    {
        if (! $class->isCompleted()) {
            return null;
        }

        $certificate = $this->getDefaultCertificateForClass($class);

        if (! $certificate) {
            return null;
        }

        return $this->issueToClass($certificate, $class, skipExisting: true);
    }

    /**
     * Create a CertificateIssue DB record (without PDF).
     */
    protected function createIssueRecord(
        Certificate $certificate,
        Student $student,
        ClassModel $class,
        ?int $enrollmentId = null
    ): CertificateIssue {
        $dataSnapshot = [
            'student_name' => $student->full_name,
            'student_id' => $student->student_id,
            'course_name' => $class->course->name ?? '',
            'class_name' => $class->title,
            'certificate_name' => $certificate->name,
            'teacher_name' => $class->teacher->user->name ?? 'N/A',
            'completion_date' => $class->isCompleted() ? $class->updated_at->format('F j, Y') : now()->format('F j, Y'),
        ];

        return CertificateIssue::create([
            'certificate_id' => $certificate->id,
            'student_id' => $student->id,
            'enrollment_id' => $enrollmentId,
            'class_id' => $class->id,
            'issue_date' => now(),
            'issued_by' => auth()->id(),
            'data_snapshot' => $dataSnapshot,
            'status' => 'issued',
        ]);
    }

    /**
     * Dispatch a Bus batch that generates PDFs for the given issue IDs and
     * records the batch ID on the class for progress tracking.
     *
     * @param  array<int>  $issueIds
     */
    protected function dispatchPdfBatch(array $issueIds, ClassModel $class, string $name, string $logAction): string
    {
        $actorId = auth()->id();
        $classId = $class->id;

        $jobs = array_map(
            fn (int $id) => new GenerateCertificatePdfJob($id, $actorId, $logAction),
            $issueIds,
        );

        $batch = Bus::batch($jobs)
            ->name($name)
            ->allowFailures()
            ->finally(function () use ($classId) {
                ClassModel::whereKey($classId)
                    ->whereNotNull('certificate_pdf_batch_id')
                    ->update(['certificate_pdf_batch_id' => null]);
            })
            ->dispatch();

        $class->update(['certificate_pdf_batch_id' => $batch->id]);

        return $batch->id;
    }
}
