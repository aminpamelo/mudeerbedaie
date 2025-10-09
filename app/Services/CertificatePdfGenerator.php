<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateIssue;
use App\Models\Enrollment;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CertificatePdfGenerator
{
    /**
     * Generate a PDF certificate for a student
     */
    public function generate(Certificate $certificate, Student $student, ?Enrollment $enrollment = null, array $additionalData = []): string
    {
        // Prepare data for certificate
        $data = $this->prepareData($certificate, $student, $enrollment, $additionalData);

        // Generate HTML from certificate template
        $html = $this->renderHtml($certificate, $data);

        // Generate PDF
        $pdf = Pdf::loadHTML($html)
            ->setPaper($this->getPaperSize($certificate), $certificate->orientation)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);

        // Generate file path
        $filePath = $this->generateFilePath($data['certificate_number']);

        // Save PDF to public storage so it can be accessed via URL
        Storage::disk('public')->put($filePath, $pdf->output());

        return $filePath;
    }

    /**
     * Prepare data for certificate rendering
     */
    public function prepareData(Certificate $certificate, Student $student, ?Enrollment $enrollment, array $additionalData): array
    {
        $defaultData = [
            'certificate_name' => $certificate->name,
            'student_name' => $student->full_name,
            'student_id' => $student->student_id,
            'student_email' => $student->user->email ?? '',
            'student_ic' => $student->ic_number ?? '',
            'course_name' => $enrollment?->course->name ?? '',
            'course_description' => $enrollment?->course->description ?? '',
            'class_name' => $additionalData['class_name'] ?? '',
            'class_teacher' => $additionalData['teacher_name'] ?? '',
            'certificate_number' => $additionalData['certificate_number'] ?? 'CERT-TEMP',
            'issue_date' => now()->format('F j, Y'),
            'completion_date' => $enrollment?->completion_date?->format('F j, Y') ?? now()->format('F j, Y'),
            'enrollment_date' => $enrollment?->enrollment_date?->format('F j, Y') ?? '',
            'current_date' => now()->format('F j, Y'),
            'current_year' => now()->year,
            'verification_url' => $additionalData['verification_url'] ?? '',
        ];

        return array_merge($defaultData, $additionalData);
    }

    /**
     * Render HTML from certificate elements
     */
    protected function renderHtml(Certificate $certificate, array $data): string
    {
        $elements = $certificate->getElementsArray();
        $width = $certificate->width;
        $height = $certificate->height;
        $backgroundColor = $certificate->background_color;
        $backgroundImage = $certificate->background_image;

        // Build HTML structure
        $html = view('certificates.pdf-template', [
            'certificate' => $certificate,
            'elements' => $elements,
            'data' => $data,
            'width' => $width,
            'height' => $height,
            'backgroundColor' => $backgroundColor,
            'backgroundImage' => $backgroundImage,
        ])->render();

        return $html;
    }

    /**
     * Get paper size for PDF
     */
    protected function getPaperSize(Certificate $certificate): array|string
    {
        if ($certificate->size === 'letter') {
            return 'letter'; // 8.5" x 11"
        }

        return 'a4'; // 210mm x 297mm
    }

    /**
     * Generate file path for certificate PDF
     */
    protected function generateFilePath(string $certificateNumber): string
    {
        $year = now()->year;
        $month = now()->format('m');

        return "certificates/generated/{$year}/{$month}/{$certificateNumber}.pdf";
    }

    /**
     * Replace dynamic fields in text
     */
    public function replaceDynamicFields(string $text, array $data): string
    {
        $replacements = [
            '{{student_name}}' => $data['student_name'] ?? '',
            '{{student_id}}' => $data['student_id'] ?? '',
            '{{student_email}}' => $data['student_email'] ?? '',
            '{{student_ic}}' => $data['student_ic'] ?? '',
            '{{course_name}}' => $data['course_name'] ?? '',
            '{{course_description}}' => $data['course_description'] ?? '',
            '{{class_name}}' => $data['class_name'] ?? '',
            '{{class_teacher}}' => $data['class_teacher'] ?? '',
            '{{teacher_name}}' => $data['class_teacher'] ?? '',
            '{{certificate_number}}' => $data['certificate_number'] ?? '',
            '{{issue_date}}' => $data['issue_date'] ?? '',
            '{{completion_date}}' => $data['completion_date'] ?? '',
            '{{enrollment_date}}' => $data['enrollment_date'] ?? '',
            '{{current_date}}' => $data['current_date'] ?? '',
            '{{current_year}}' => $data['current_year'] ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    /**
     * Generate QR code image data URL
     */
    public function generateQrCodeDataUrl(string $data): string
    {
        $qrCode = QrCode::format('png')
            ->size(200)
            ->generate($data);

        return 'data:image/png;base64,'.base64_encode($qrCode);
    }

    /**
     * Generate preview HTML (without saving)
     */
    public function generatePreview(Certificate $certificate, array $sampleData = []): string
    {
        $data = array_merge($certificate->generatePreview(), $sampleData);

        return $this->renderHtml($certificate, $data);
    }

    /**
     * Stream PDF download
     */
    public function download(CertificateIssue $certificateIssue)
    {
        if (! $certificateIssue->hasFile()) {
            throw new \Exception('Certificate PDF file not found.');
        }

        // Log download
        $certificateIssue->logAction('downloaded', auth()->user());

        $fileName = "{$certificateIssue->certificate_number}_Certificate.pdf";

        return Storage::disk('public')->download($certificateIssue->file_path, $fileName);
    }
}
