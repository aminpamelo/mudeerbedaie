<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateIssue;
use App\Models\ClassModel;
use App\Models\Student;
use Illuminate\Support\Collection;

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
     * Issue a certificate to a single student
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
            // Prepare data snapshot
            $dataSnapshot = [
                'student_name' => $student->full_name,
                'student_id' => $student->student_id,
                'course_name' => $class->course->name,
                'class_name' => $class->title,
                'certificate_name' => $certificate->name,
                'teacher_name' => $class->teacher->user->name ?? 'N/A',
                'completion_date' => $class->isCompleted() ? $class->updated_at->format('F j, Y') : now()->format('F j, Y'),
            ];

            // Create certificate issue
            $certificateIssue = CertificateIssue::create([
                'certificate_id' => $certificate->id,
                'student_id' => $student->id,
                'enrollment_id' => $enrollmentId,
                'class_id' => $class->id,
                'issue_date' => now(),
                'issued_by' => auth()->id(),
                'data_snapshot' => $dataSnapshot,
                'status' => 'issued',
            ]);

            // Generate PDF
            $pdfPath = $this->pdfGenerator->generate($certificate, $student, $enrollmentId, $dataSnapshot);
            $certificateIssue->update(['file_path' => $pdfPath]);

            return [
                'success' => true,
                'message' => 'Certificate issued successfully.',
                'certificate_issue' => $certificateIssue,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to issue certificate: '.$e->getMessage(),
                'certificate_issue' => null,
            ];
        }
    }

    /**
     * Bulk issue certificates to multiple students in a class
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
                'results' => [],
            ];
        }

        $students = empty($studentIds)
            ? $this->getEligibleStudentsForClass($class)
            : Student::whereIn('id', $studentIds)->get();

        $issuedCount = 0;
        $skippedCount = 0;
        $failedCount = 0;
        $results = [];

        foreach ($students as $student) {
            $canIssue = $this->canIssueToStudent($certificate, $student, $class);

            if (! $canIssue['can_issue']) {
                if ($skipExisting && str_contains($canIssue['reason'], 'already issued')) {
                    $skippedCount++;
                    $results[] = [
                        'student_id' => $student->id,
                        'student_name' => $student->full_name,
                        'status' => 'skipped',
                        'reason' => $canIssue['reason'],
                    ];

                    continue;
                } else {
                    $failedCount++;
                    $results[] = [
                        'student_id' => $student->id,
                        'student_name' => $student->full_name,
                        'status' => 'failed',
                        'reason' => $canIssue['reason'],
                    ];

                    continue;
                }
            }

            $result = $this->issueToStudent($certificate, $student, $class);

            if ($result['success']) {
                $issuedCount++;
                $results[] = [
                    'student_id' => $student->id,
                    'student_name' => $student->full_name,
                    'status' => 'issued',
                    'certificate_issue_id' => $result['certificate_issue']->id,
                ];
            } else {
                $failedCount++;
                $results[] = [
                    'student_id' => $student->id,
                    'student_name' => $student->full_name,
                    'status' => 'failed',
                    'reason' => $result['message'],
                ];
            }
        }

        return [
            'success' => $issuedCount > 0,
            'message' => "Issued: {$issuedCount}, Skipped: {$skippedCount}, Failed: {$failedCount}",
            'issued_count' => $issuedCount,
            'skipped_count' => $skippedCount,
            'failed_count' => $failedCount,
            'results' => $results,
        ];
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
}
