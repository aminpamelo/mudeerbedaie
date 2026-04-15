<?php

use App\Models\ClassModel;
use App\Models\Certificate;
use App\Models\CertificateIssue;
use App\Services\CertificateService;
use App\Services\WhatsAppService;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public ClassModel $class;

    public array $selectedStudentIds = [];

    public bool $selectAll = false;

    public bool $skipExisting = true;

    public string $filterStatus = 'all';

    public string $searchStudent = '';

    public bool $showBulkIssueModal = false;

    public ?int $selectedCertificateId = null;

    public bool $showPreviewModal = false;

    public ?int $previewCertificateId = null;

    public bool $showAssignModal = false;

    public ?int $assignCertificateId = null;

    public bool $assignAsDefault = false;

    public array $selectedIssueIds = [];

    public bool $selectAllIssued = false;

    public ?int $editingNameIssueId = null;

    public string $editingName = '';

    // Send certificate modal state
    public bool $showSendModal = false;

    public array $sendIssueIds = [];

    public string $sendChannel = 'email';

    public string $sendMessage = '';

    public bool $isBulkSend = false;

    public string $modalStudentSearch = '';

    // WABA state
    public string $whatsappProvider = 'onsend';
    public ?int $selectedWabaTemplateId = null;

    // Log modal state
    public bool $showLogModal = false;
    public ?int $logIssueId = null;

    // Send Logs tab
    public string $activeSection = 'certificates';
    public string $logSearch = '';
    public string $logActionFilter = 'all';

    public function mount(ClassModel $class): void
    {
        $this->class = $class->load(['certificates', 'course.certificates', 'activeStudents.student.user']);
    }

    public function with(): array
    {
        $defaultCertificate = $this->class->getDefaultCertificate();

        $assignedCertificates = collect($this->class->certificates)
            ->merge($this->class->course?->certificates ?? [])
            ->unique('id');

        $stats = $this->class->getCertificateIssuanceStats($defaultCertificate);

        $issuedCertificates = $this->getFilteredIssuedQuery()
            ->with(['certificate', 'student.user', 'issuedBy', 'logs' => fn ($q) => $q->latest()->with('user')])
            ->paginate(10);

        $eligibleStudents = $this->class->activeStudents()
            ->with('student.user')
            ->get()
            ->pluck('student');

        $previewCertificate = null;
        $previewData = [];
        if ($this->previewCertificateId) {
            $previewCertificate = Certificate::find($this->previewCertificateId);
            if ($previewCertificate) {
                $previewData = $previewCertificate->generatePreview();
            }
        }

        $assignedIds = $assignedCertificates->pluck('id')->toArray();
        $availableCertificates = Certificate::where('status', 'active')
            ->whereNotIn('id', $assignedIds)
            ->orderBy('name')
            ->get();

        // Get student IDs who already have an issued certificate for the selected template in this class
        $issuedStudentIds = [];
        if ($this->selectedCertificateId) {
            $issuedStudentIds = CertificateIssue::where('class_id', $this->class->id)
                ->where('certificate_id', $this->selectedCertificateId)
                ->where('status', 'issued')
                ->pluck('student_id')
                ->toArray();
        }

        return [
            'defaultCertificate' => $defaultCertificate,
            'assignedCertificates' => $assignedCertificates,
            'stats' => $stats,
            'issuedCertificates' => $issuedCertificates,
            'eligibleStudents' => $eligibleStudents,
            'issuedStudentIds' => $issuedStudentIds,
            'previewCertificate' => $previewCertificate,
            'previewData' => $previewData,
            'availableCertificates' => $availableCertificates,
        ];
    }

    public function openAssignModal(): void
    {
        $this->showAssignModal = true;
        $this->assignCertificateId = null;
        $this->assignAsDefault = false;
    }

    public function closeAssignModal(): void
    {
        $this->showAssignModal = false;
        $this->assignCertificateId = null;
        $this->assignAsDefault = false;
    }

    public function assignCertificate(): void
    {
        $this->validate([
            'assignCertificateId' => 'required|exists:certificates,id',
        ]);

        $certificate = Certificate::findOrFail($this->assignCertificateId);

        if ($certificate->isAssignedToClass($this->class)) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'This certificate is already assigned to this class.',
            ]);

            return;
        }

        $certificate->assignToClass($this->class, $this->assignAsDefault);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Certificate \"{$certificate->name}\" assigned to this class.",
        ]);

        $this->closeAssignModal();
        $this->class->load(['certificates', 'course.certificates']);
    }

    public function unassignCertificate(int $certificateId): void
    {
        $this->class->certificates()->detach($certificateId);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Certificate removed from this class.',
        ]);

        $this->class->load(['certificates', 'course.certificates']);
    }

    public function openPreviewModal(int $certificateId): void
    {
        $this->previewCertificateId = $certificateId;
        $this->showPreviewModal = true;
    }

    public function closePreviewModal(): void
    {
        $this->showPreviewModal = false;
        $this->previewCertificateId = null;
    }

    public function openBulkIssueModal(): void
    {
        $this->selectedCertificateId = $this->class->getDefaultCertificate()?->id;
        $this->showBulkIssueModal = true;
        $this->selectedStudentIds = [];
        $this->selectAll = false;
        $this->modalStudentSearch = '';
    }

    public function closeBulkIssueModal(): void
    {
        $this->showBulkIssueModal = false;
        $this->selectedStudentIds = [];
        $this->selectAll = false;
        $this->modalStudentSearch = '';
    }

    public function updatedSelectAll($value): void
    {
        if ($value) {
            $this->selectedStudentIds = $this->class->activeStudents()
                ->with('student')
                ->get()
                ->pluck('student.id')
                ->toArray();
        } else {
            $this->selectedStudentIds = [];
        }
    }

    public function bulkIssueCertificates(): void
    {
        \Log::info('bulkIssueCertificates called', [
            'selectedCertificateId' => $this->selectedCertificateId,
            'selectedStudentIds' => $this->selectedStudentIds,
            'skipExisting' => $this->skipExisting,
        ]);

        $this->validate([
            'selectedCertificateId' => 'required|exists:certificates,id',
            'selectedStudentIds' => 'required|array|min:1',
        ]);

        \Log::info('Validation passed, getting certificate');

        $certificate = Certificate::findOrFail($this->selectedCertificateId);
        $certificateService = app(CertificateService::class);

        // Cast student IDs to integers (HTML checkboxes return strings)
        $studentIds = array_map('intval', $this->selectedStudentIds);

        \Log::info('Calling issueToClass', [
            'certificate_id' => $certificate->id,
            'class_id' => $this->class->id,
            'student_ids' => $studentIds,
        ]);

        $result = $certificateService->issueToClass(
            $certificate,
            $this->class,
            $studentIds,
            $this->skipExisting
        );

        \Log::info('issueToClass result', $result);

        if ($result['success']) {
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => $result['message'],
            ]);
        } else {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => $result['message'],
            ]);
        }

        $this->closeBulkIssueModal();
        $this->class->refresh();
    }

    public function regeneratePdf(int $certificateIssueId): void
    {
        $issue = CertificateIssue::with(['certificate', 'student.user', 'enrollment'])->findOrFail($certificateIssueId);

        if (! $issue->certificate) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Certificate template not found.',
            ]);

            return;
        }

        try {
            $pdfGenerator = app(\App\Services\CertificatePdfGenerator::class);

            // Delete old file if it exists
            $issue->deleteFile();

            // Prepare data from snapshot or generate fresh
            $data = $issue->data_snapshot ?? [];
            $data['certificate_number'] = $issue->certificate_number;

            try {
                $data['verification_url'] = $issue->getVerificationUrl();
            } catch (\Exception $e) {
                $data['verification_url'] = '';
            }

            // Generate new PDF
            $filePath = $pdfGenerator->generate(
                certificate: $issue->certificate,
                student: $issue->student,
                enrollment: $issue->enrollment,
                additionalData: $data
            );

            $issue->update(['file_path' => $filePath]);
            $issue->logAction('regenerated', auth()->user());

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Certificate PDF regenerated successfully.',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to regenerate PDF: '.$e->getMessage(),
            ]);
        }
    }

    public function revokeCertificate(int $certificateIssueId, string $reason = 'Revoked by admin'): void
    {
        $issue = CertificateIssue::findOrFail($certificateIssueId);

        if ($issue->canBeRevoked()) {
            $issue->revoke($reason, auth()->user());

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Certificate revoked successfully.',
            ]);
        } else {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Certificate cannot be revoked.',
            ]);
        }
    }

    public function reinstateCertificate(int $certificateIssueId): void
    {
        $issue = CertificateIssue::findOrFail($certificateIssueId);

        if ($issue->canBeReinstated()) {
            $issue->reinstate(auth()->user());

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Certificate reinstated successfully.',
            ]);
        } else {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Certificate cannot be reinstated.',
            ]);
        }
    }

    public function updatedSelectAllIssued(bool $value): void
    {
        if ($value) {
            $this->selectedIssueIds = $this->getFilteredIssuedQuery()
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->toArray();
        } else {
            $this->selectedIssueIds = [];
        }
    }

    public function updatedSelectedIssueIds(): void
    {
        $allFilteredIds = $this->getFilteredIssuedQuery()
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->toArray();

        $this->selectAllIssued = count($this->selectedIssueIds) > 0
            && count(array_intersect($this->selectedIssueIds, $allFilteredIds)) === count($allFilteredIds);
    }

    public function updatingFilterStatus(): void
    {
        $this->selectedIssueIds = [];
        $this->selectAllIssued = false;
        $this->resetPage();
    }

    public function updatingSearchStudent(): void
    {
        $this->selectedIssueIds = [];
        $this->selectAllIssued = false;
        $this->resetPage();
    }

    public function startEditingName(int $issueId): void
    {
        $issue = CertificateIssue::find($issueId);
        if ($issue) {
            $this->editingNameIssueId = $issueId;
            $this->editingName = $issue->student?->user?->name ?? '';
        }
    }

    public function saveStudentName(): void
    {
        if (! $this->editingNameIssueId) {
            return;
        }

        $this->validate([
            'editingName' => 'required|string|max:255',
        ]);

        $issue = CertificateIssue::with(['student.user', 'certificate'])->find($this->editingNameIssueId);
        if (! $issue?->student?->user) {
            $this->editingNameIssueId = null;
            $this->editingName = '';

            return;
        }

        // Update user name
        $issue->student->user->update(['name' => $this->editingName]);

        // Update data_snapshot with new name
        $dataSnapshot = $issue->data_snapshot ?? [];
        $dataSnapshot['student_name'] = $this->editingName;
        $issue->update(['data_snapshot' => $dataSnapshot]);

        // Regenerate the PDF with updated name
        if ($issue->certificate) {
            try {
                $pdfGenerator = app(\App\Services\CertificatePdfGenerator::class);
                $enrollment = $issue->enrollment_id ? \App\Models\Enrollment::find($issue->enrollment_id) : null;

                // Delete old file
                $issue->deleteFile();

                // Regenerate PDF
                $pdfPath = $pdfGenerator->generate($issue->certificate, $issue->student, $enrollment, $dataSnapshot);
                $issue->update(['file_path' => $pdfPath]);
            } catch (\Exception $e) {
                \Log::error('Failed to regenerate certificate PDF: '.$e->getMessage());
            }
        }

        $this->editingNameIssueId = null;
        $this->editingName = '';

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Student name updated and certificate regenerated.',
        ]);
    }

    public function cancelEditingName(): void
    {
        $this->editingNameIssueId = null;
        $this->editingName = '';
    }

    public function bulkRevokeCertificates(): void
    {
        if (empty($this->selectedIssueIds)) {
            return;
        }

        $issues = CertificateIssue::whereIn('id', $this->selectedIssueIds)
            ->where('class_id', $this->class->id)
            ->get();

        $revokedCount = 0;
        foreach ($issues as $issue) {
            if ($issue->canBeRevoked()) {
                $issue->revoke('Bulk revoked by admin', auth()->user());
                $revokedCount++;
            }
        }

        $this->selectedIssueIds = [];
        $this->selectAllIssued = false;

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Revoked {$revokedCount} ".Str::plural('certificate', $revokedCount).'.',
        ]);
    }

    public function bulkReinstateCertificates(): void
    {
        if (empty($this->selectedIssueIds)) {
            return;
        }

        $issues = CertificateIssue::whereIn('id', $this->selectedIssueIds)
            ->where('class_id', $this->class->id)
            ->get();

        $reinstatedCount = 0;
        foreach ($issues as $issue) {
            if ($issue->canBeReinstated()) {
                $issue->reinstate(auth()->user());
                $reinstatedCount++;
            }
        }

        $this->selectedIssueIds = [];
        $this->selectAllIssued = false;

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Reinstated {$reinstatedCount} ".Str::plural('certificate', $reinstatedCount).'.',
        ]);
    }

    public function bulkRegeneratePdfs(): void
    {
        if (empty($this->selectedIssueIds)) {
            return;
        }

        $issues = CertificateIssue::with(['certificate', 'student.user', 'enrollment'])
            ->whereIn('id', $this->selectedIssueIds)
            ->where('class_id', $this->class->id)
            ->get();

        $pdfGenerator = app(\App\Services\CertificatePdfGenerator::class);
        $successCount = 0;
        $failCount = 0;

        foreach ($issues as $issue) {
            if (! $issue->certificate) {
                $failCount++;

                continue;
            }

            try {
                $issue->deleteFile();

                $data = $issue->data_snapshot ?? [];
                $data['certificate_number'] = $issue->certificate_number;

                try {
                    $data['verification_url'] = $issue->getVerificationUrl();
                } catch (\Exception $e) {
                    $data['verification_url'] = '';
                }

                $filePath = $pdfGenerator->generate(
                    certificate: $issue->certificate,
                    student: $issue->student,
                    enrollment: $issue->enrollment,
                    additionalData: $data
                );

                $issue->update(['file_path' => $filePath]);
                $issue->logAction('regenerated', auth()->user());
                $successCount++;
            } catch (\Exception $e) {
                \Log::error("Failed to regenerate certificate {$issue->certificate_number}: {$e->getMessage()}");
                $failCount++;
            }
        }

        $this->selectedIssueIds = [];
        $this->selectAllIssued = false;

        $message = "Regenerated {$successCount} " . Str::plural('certificate', $successCount) . '.';
        if ($failCount > 0) {
            $message .= " {$failCount} failed.";
        }

        $this->dispatch('notify', [
            'type' => $failCount > 0 ? 'warning' : 'success',
            'message' => $message,
        ]);
    }

    public function regenerateAllPdfs(): void
    {
        $issues = CertificateIssue::with(['certificate', 'student.user', 'enrollment'])
            ->where('class_id', $this->class->id)
            ->where('status', 'issued')
            ->get();

        if ($issues->isEmpty()) {
            $this->dispatch('notify', [
                'type' => 'info',
                'message' => 'No issued certificates to regenerate.',
            ]);

            return;
        }

        $pdfGenerator = app(\App\Services\CertificatePdfGenerator::class);
        $successCount = 0;
        $failCount = 0;

        foreach ($issues as $issue) {
            if (! $issue->certificate) {
                $failCount++;

                continue;
            }

            try {
                $issue->deleteFile();

                $data = $issue->data_snapshot ?? [];
                $data['certificate_number'] = $issue->certificate_number;

                try {
                    $data['verification_url'] = $issue->getVerificationUrl();
                } catch (\Exception $e) {
                    $data['verification_url'] = '';
                }

                $filePath = $pdfGenerator->generate(
                    certificate: $issue->certificate,
                    student: $issue->student,
                    enrollment: $issue->enrollment,
                    additionalData: $data
                );

                $issue->update(['file_path' => $filePath]);
                $issue->logAction('regenerated', auth()->user());
                $successCount++;
            } catch (\Exception $e) {
                \Log::error("Failed to regenerate certificate {$issue->certificate_number}: {$e->getMessage()}");
                $failCount++;
            }
        }

        $message = "Regenerated {$successCount} " . Str::plural('certificate', $successCount) . '.';
        if ($failCount > 0) {
            $message .= " {$failCount} failed.";
        }

        $this->dispatch('notify', [
            'type' => $failCount > 0 ? 'warning' : 'success',
            'message' => $message,
        ]);
    }

    public function bulkDownloadCertificates(): mixed
    {
        if (empty($this->selectedIssueIds)) {
            return null;
        }

        $issues = CertificateIssue::whereIn('id', $this->selectedIssueIds)
            ->where('class_id', $this->class->id)
            ->get()
            ->filter(fn ($issue) => $issue->hasFile());

        if ($issues->isEmpty()) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'None of the selected certificates have generated PDF files.',
            ]);

            return null;
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'certs_').'.zip';
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($issues as $issue) {
            $filePath = \Storage::disk('public')->path($issue->file_path);
            $filename = $issue->getDownloadFilename();
            $zip->addFile($filePath, $filename);
        }

        $zip->close();

        $zipContent = file_get_contents($zipPath);
        @unlink($zipPath);

        $downloadName = 'certificates-'.$this->class->id.'-'.date('Y-m-d').'.zip';

        return response()->streamDownload(function () use ($zipContent) {
            echo $zipContent;
        }, $downloadName, [
            'Content-Type' => 'application/zip',
        ]);
    }

    // Send Certificate Methods

    public function openSendModal(int $issueId): void
    {
        $issue = CertificateIssue::with(['student.user', 'certificate'])->find($issueId);

        if (! $issue || ! $issue->canBeSent()) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Certificate cannot be sent. It must be issued with a generated PDF.',
            ]);

            return;
        }

        $this->sendIssueIds = [$issueId];
        $this->isBulkSend = false;
        $this->sendChannel = 'email';
        $this->sendMessage = $this->getDefaultSendMessage($issue);
        $this->showSendModal = true;
    }

    public function openBulkSendModal(): void
    {
        if (empty($this->selectedIssueIds)) {
            return;
        }

        $validIds = CertificateIssue::whereIn('id', $this->selectedIssueIds)
            ->where('class_id', $this->class->id)
            ->where('status', 'issued')
            ->get()
            ->filter(fn ($issue) => $issue->hasFile())
            ->pluck('id')
            ->toArray();

        if (empty($validIds)) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'None of the selected certificates can be sent. They must be issued with generated PDFs.',
            ]);

            return;
        }

        $this->sendIssueIds = $validIds;
        $this->isBulkSend = true;
        $this->sendChannel = 'email';
        $this->sendMessage = $this->getDefaultBulkSendMessage();
        $this->showSendModal = true;
    }

    public function closeSendModal(): void
    {
        $this->showSendModal = false;
        $this->sendIssueIds = [];
        $this->sendChannel = 'email';
        $this->sendMessage = '';
        $this->isBulkSend = false;
        $this->whatsappProvider = 'onsend';
        $this->selectedWabaTemplateId = null;
    }

    public function openLogModal(int $issueId): void
    {
        $this->logIssueId = $issueId;
        $this->showLogModal = true;
    }

    public function closeLogModal(): void
    {
        $this->showLogModal = false;
        $this->logIssueId = null;
    }

    public function getLogIssueProperty(): ?CertificateIssue
    {
        if (! $this->logIssueId) {
            return null;
        }

        return CertificateIssue::with(['logs' => fn ($q) => $q->latest()->with('user'), 'student.user', 'certificate'])
            ->find($this->logIssueId);
    }

    public function getSendLogsProperty(): \Illuminate\Pagination\LengthAwarePaginator
    {
        return \App\Models\CertificateLog::query()
            ->whereHas('certificateIssue', fn ($q) => $q->where('class_id', $this->class->id))
            ->whereIn('action', ['sent_email', 'sent_whatsapp', 'sent_waba'])
            ->with(['certificateIssue.student.user', 'certificateIssue.certificate', 'user'])
            ->when($this->logSearch, function ($q) {
                $q->whereHas('certificateIssue.student.user', fn ($sq) => $sq->where('name', 'like', "%{$this->logSearch}%"));
            })
            ->when($this->logActionFilter !== 'all', fn ($q) => $q->where('action', $this->logActionFilter))
            ->latest()
            ->paginate(15, pageName: 'logPage');
    }

    protected function getDefaultSendMessage(CertificateIssue $issue): string
    {
        $studentName = $issue->student?->user?->name ?? 'Student';
        $certName = $issue->getCertificateName();
        $appName = config('app.name');

        return "Assalamualaikum {$studentName},\n\nTahniah! Sijil anda ({$certName}) telah dikeluarkan.\n\nSila rujuk sijil anda yang dilampirkan.\n\nTerima kasih,\n{$appName}";
    }

    protected function getDefaultBulkSendMessage(): string
    {
        $className = $this->class->title ?? 'the class';
        $appName = config('app.name');

        return "Assalamualaikum,\n\nTahniah! Sijil anda untuk {$className} telah dikeluarkan.\n\nSila rujuk sijil anda yang dilampirkan.\n\nTerima kasih,\n{$appName}";
    }

    public function getSendRecipientsProperty(): array
    {
        if (empty($this->sendIssueIds)) {
            return [];
        }

        return CertificateIssue::with(['student.user'])
            ->whereIn('id', $this->sendIssueIds)
            ->get()
            ->map(function ($issue) {
                $student = $issue->student;
                $user = $student?->user;

                return [
                    'issue_id' => $issue->id,
                    'name' => $user?->name ?? 'Unknown',
                    'email' => $user?->email,
                    'phone' => $student?->phone_number,
                    'has_email' => ! empty($user?->email),
                    'has_phone' => ! empty($student?->phone_number),
                    'certificate_number' => $issue->certificate_number,
                ];
            })
            ->toArray();
    }

    public function getWabaTemplatesProperty(): array
    {
        return \App\Models\WhatsAppTemplate::query()
            ->orderByRaw("CASE WHEN status = 'APPROVED' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    public function sendCertificates(): void
    {
        $isWaba = in_array($this->sendChannel, ['whatsapp', 'both']) && $this->whatsappProvider === 'waba';

        $rules = [
            'sendChannel' => 'required|in:email,whatsapp,both',
        ];

        // Message required for email, or onsend whatsapp, or email part of both
        if (! ($this->sendChannel === 'whatsapp' && $this->whatsappProvider === 'waba')) {
            $rules['sendMessage'] = 'required|string|min:10';
        }

        if ($isWaba) {
            $rules['selectedWabaTemplateId'] = 'required|exists:whatsapp_templates,id';
        }

        $this->validate($rules);

        // Verify WABA template is approved before dispatching
        if ($isWaba) {
            $wabaTemplate = \App\Models\WhatsAppTemplate::find($this->selectedWabaTemplateId);
            if (! $wabaTemplate || $wabaTemplate->status !== 'APPROVED') {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'The selected WABA template is not approved by Meta yet. Status: '.($wabaTemplate?->status ?? 'unknown'),
                ]);

                return;
            }
        }

        $issues = CertificateIssue::with(['student.user'])
            ->whereIn('id', $this->sendIssueIds)
            ->where('class_id', $this->class->id)
            ->where('status', 'issued')
            ->get()
            ->filter(fn ($issue) => $issue->hasFile());

        if ($issues->isEmpty()) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'No valid certificates to send.',
            ]);

            return;
        }

        $whatsApp = app(WhatsAppService::class);
        $emailCount = 0;
        $whatsappCount = 0;
        $skippedEmail = 0;
        $skippedWhatsapp = 0;
        $delay = 0;

        foreach ($issues as $issue) {
            $student = $issue->student;
            $user = $student?->user;

            // Personalize message for bulk send
            $message = $this->isBulkSend
                ? str_replace('Assalamualaikum,', 'Assalamualaikum '.($user?->name ?? '').',', $this->sendMessage)
                : $this->sendMessage;

            // Email channel
            if (in_array($this->sendChannel, ['email', 'both'])) {
                $email = $user?->email;
                if ($email) {
                    \App\Jobs\SendCertificateEmailJob::dispatch(
                        $issue->id,
                        $email,
                        $user->name ?? 'Student',
                        $message,
                        auth()->id()
                    );
                    $emailCount++;
                } else {
                    $skippedEmail++;
                }
            }

            // WhatsApp channel
            if (in_array($this->sendChannel, ['whatsapp', 'both'])) {
                $phone = $student?->phone_number;

                if ($phone && $this->whatsappProvider === 'waba' && $this->selectedWabaTemplateId) {
                    \App\Jobs\SendCertificateWabaJob::dispatch(
                        $issue->id,
                        $phone,
                        $this->selectedWabaTemplateId,
                        auth()->id()
                    )->onQueue('whatsapp');
                    $whatsappCount++;
                } elseif ($phone && $this->whatsappProvider === 'onsend' && $whatsApp->isEnabled()) {
                    $randomDelay = $whatsApp->getRandomDelay();
                    \App\Jobs\SendCertificateWhatsAppJob::dispatch(
                        $issue->id,
                        $phone,
                        $message,
                        auth()->id()
                    )->delay(now()->addSeconds($delay))
                        ->onQueue('whatsapp');
                    $delay += $randomDelay;
                    $whatsappCount++;
                } else {
                    $skippedWhatsapp++;
                }
            }
        }

        // Build feedback message
        $parts = [];
        if ($emailCount > 0) {
            $parts[] = "{$emailCount} ".Str::plural('email', $emailCount).' queued';
        }
        if ($whatsappCount > 0) {
            $parts[] = "{$whatsappCount} WhatsApp ".Str::plural('message', $whatsappCount).' queued';
        }
        $skippedParts = [];
        if ($skippedEmail > 0) {
            $skippedParts[] = "{$skippedEmail} without email";
        }
        if ($skippedWhatsapp > 0) {
            $skippedParts[] = "{$skippedWhatsapp} without phone";
        }

        $successMsg = implode(', ', $parts) ?: 'No messages sent';
        if (! empty($skippedParts)) {
            $successMsg .= ' (skipped: '.implode(', ', $skippedParts).')';
        }

        $this->dispatch('notify', [
            'type' => ($emailCount > 0 || $whatsappCount > 0) ? 'success' : 'warning',
            'message' => $successMsg,
        ]);

        $this->closeSendModal();
        $this->selectedIssueIds = [];
        $this->selectAllIssued = false;
    }

    protected function getFilteredIssuedQuery()
    {
        return CertificateIssue::where('class_id', $this->class->id)
            ->when($this->filterStatus !== 'all', fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->searchStudent, function ($q) {
                $q->whereHas('student.user', function ($query) {
                    $query->where('name', 'like', "%{$this->searchStudent}%");
                });
            })
            ->latest();
    }
}; ?>

<div class="space-y-6">
    <!-- Statistics Strip -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="relative overflow-hidden rounded-lg bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 p-4">
            <div class="flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700">
                    <flux:icon name="users" class="size-5 text-zinc-500 dark:text-zinc-400" />
                </div>
                <div class="min-w-0">
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 truncate">Total Students</span>
                    <p class="text-xl font-semibold text-zinc-900 dark:text-white tabular-nums">{{ $stats['total_students'] }}</p>
                </div>
            </div>
        </div>

        <div class="relative overflow-hidden rounded-lg bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 p-4">
            <div class="flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700">
                    <flux:icon name="document-check" class="size-5 text-zinc-500 dark:text-zinc-400" />
                </div>
                <div class="min-w-0">
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 truncate">Issued</span>
                    <p class="text-xl font-semibold text-zinc-900 dark:text-white tabular-nums">{{ $stats['issued_count'] }}</p>
                </div>
            </div>
        </div>

        <div class="relative overflow-hidden rounded-lg bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 p-4">
            <div class="flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700">
                    <flux:icon name="clock" class="size-5 text-zinc-500 dark:text-zinc-400" />
                </div>
                <div class="min-w-0">
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 truncate">Pending</span>
                    <p class="text-xl font-semibold text-zinc-900 dark:text-white tabular-nums">{{ $stats['pending_count'] }}</p>
                </div>
            </div>
        </div>

        <div class="relative overflow-hidden rounded-lg bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 p-4">
            <div class="flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700">
                    <flux:icon name="chart-bar" class="size-5 text-zinc-500 dark:text-zinc-400" />
                </div>
                <div class="min-w-0">
                    <span class="text-xs text-zinc-500 dark:text-zinc-400 truncate">Completion</span>
                    <p class="text-xl font-semibold text-zinc-900 dark:text-white tabular-nums">{{ $stats['completion_rate'] }}%</p>
                </div>
            </div>
            {{-- Progress bar --}}
            <div class="absolute bottom-0 inset-x-0 h-1 bg-zinc-100 dark:bg-zinc-700">
                <div class="h-full bg-zinc-400 dark:bg-zinc-500 transition-all duration-500" style="width: {{ min($stats['completion_rate'], 100) }}%"></div>
            </div>
        </div>
    </div>

    <!-- Assigned Certificates -->
    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700">
        <div class="border-b border-zinc-200 dark:border-zinc-700 px-5 py-3 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Templates</h3>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Certificate templates assigned to this class</p>
            </div>
            <div class="flex items-center gap-2">
                <flux:button variant="subtle" wire:click="openAssignModal" icon="plus">
                    Add Template
                </flux:button>
                @if($assignedCertificates->isNotEmpty())
                    <flux:button variant="primary" wire:click="openBulkIssueModal" icon="document-plus">
                        Issue Certificates
                    </flux:button>
                @endif
            </div>
        </div>

        <div class="px-5 py-4">
            @if($assignedCertificates->isEmpty())
                <div class="text-center py-8">
                    <flux:icon name="document-text" class="w-8 h-8 text-zinc-300 dark:text-zinc-600 mx-auto mb-2" />
                    <h4 class="text-sm font-medium text-zinc-500">No templates assigned</h4>
                    <p class="text-xs text-zinc-400 mt-1">Click "Add Template" to assign a certificate to this class</p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($assignedCertificates as $certificate)
                        <div class="group relative rounded-lg border {{ $defaultCertificate && $defaultCertificate->id === $certificate->id ? 'border-blue-300 dark:border-blue-600 bg-blue-50/50 dark:bg-blue-900/10' : 'border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800' }} p-4 transition-colors hover:border-zinc-300 dark:hover:border-zinc-600">
                            <div class="flex items-start gap-3">
                                <div class="flex size-9 shrink-0 items-center justify-center rounded-md {{ $defaultCertificate && $defaultCertificate->id === $certificate->id ? 'bg-blue-100 dark:bg-blue-900/40' : 'bg-zinc-100 dark:bg-zinc-700' }}">
                                    <flux:icon name="document-text" class="size-4 {{ $defaultCertificate && $defaultCertificate->id === $certificate->id ? 'text-blue-600 dark:text-blue-400' : 'text-zinc-500 dark:text-zinc-400' }}" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h4 class="text-sm font-medium text-zinc-700 dark:text-zinc-300 truncate">{{ $certificate->name }}</h4>
                                        @if($defaultCertificate && $defaultCertificate->id === $certificate->id)
                                            <flux:badge color="blue" size="sm">Default</flux:badge>
                                        @endif
                                    </div>
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                        {{ $certificate->formatted_size }}
                                    </span>
                                </div>
                            </div>
                            <div class="mt-3 flex items-center justify-between pt-3 border-t border-zinc-100 dark:border-zinc-700/50">
                                <div class="flex gap-2">
                                    <flux:button variant="subtle" size="sm" wire:click="openPreviewModal({{ $certificate->id }})" icon="eye">
                                        Preview
                                    </flux:button>
                                    <flux:button variant="subtle" size="sm" href="{{ route('certificates.edit', $certificate) }}" icon="pencil">
                                        Edit
                                    </flux:button>
                                </div>
                                @if($this->class->certificates->contains('id', $certificate->id))
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        wire:click="unassignCertificate({{ $certificate->id }})"
                                        wire:confirm="Remove this certificate from the class?"
                                        icon="x-mark"
                                        tooltip="Remove from class"
                                    />
                                @else
                                    <flux:badge color="zinc" size="sm">From Course</flux:badge>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <!-- Section Tabs -->
    <div class="flex items-center gap-1 border-b border-zinc-200 dark:border-zinc-700 mb-4">
        <button
            wire:click="$set('activeSection', 'certificates')"
            class="pb-2.5 px-3 text-sm font-medium border-b-2 transition-colors {{ $activeSection === 'certificates' ? 'border-zinc-900 dark:border-zinc-100 text-zinc-900 dark:text-zinc-100' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}"
        >
            Issued Certificates
        </button>
        <button
            wire:click="$set('activeSection', 'send-logs')"
            class="pb-2.5 px-3 text-sm font-medium border-b-2 transition-colors {{ $activeSection === 'send-logs' ? 'border-zinc-900 dark:border-zinc-100 text-zinc-900 dark:text-zinc-100' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}"
        >
            Send Logs
            @php
                $totalSendLogs = \App\Models\CertificateLog::whereHas('certificateIssue', fn ($q) => $q->where('class_id', $class->id))
                    ->whereIn('action', ['sent_email', 'sent_whatsapp', 'sent_waba'])
                    ->count();
            @endphp
            @if($totalSendLogs > 0)
                <flux:badge size="sm" class="ml-1">{{ $totalSendLogs }}</flux:badge>
            @endif
        </button>
    </div>

    <!-- Issued Certificates List -->
    @if($activeSection === 'certificates')
    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700">
        <div class="border-b border-zinc-200 dark:border-zinc-700 px-5 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Issued Certificates</h3>
                @if($issuedCertificates->total() > 0)
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">{{ $issuedCertificates->total() }} {{ Str::plural('certificate', $issuedCertificates->total()) }} issued</p>
                @endif
            </div>
            <div class="flex items-center gap-2">
                <flux:input
                    wire:model.live.debounce.300ms="searchStudent"
                    placeholder="Search student..."
                    icon="magnifying-glass"
                    size="sm"
                />
                <div class="w-40 shrink-0">
                    <flux:select wire:model.live="filterStatus" size="sm">
                        <option value="all">All Status</option>
                        <option value="issued">Issued</option>
                        <option value="revoked">Revoked</option>
                    </flux:select>
                </div>
                @if($issuedCertificates->total() > 0)
                    <flux:button
                        variant="outline"
                        size="sm"
                        wire:click="regenerateAllPdfs"
                        wire:confirm="Regenerate ALL issued certificate PDFs for this class? This will update them with the latest template design."
                        wire:loading.attr="disabled"
                        wire:target="regenerateAllPdfs"
                    >
                        <div class="flex items-center justify-center">
                            <flux:icon name="arrow-path" class="w-4 h-4 mr-1" />
                            <span wire:loading.remove wire:target="regenerateAllPdfs">Regenerate All</span>
                            <span wire:loading wire:target="regenerateAllPdfs">Regenerating...</span>
                        </div>
                    </flux:button>
                @endif
            </div>
        </div>

        <div class="px-5 py-4">
            {{-- Bulk Action Bar --}}
            @if($issuedCertificates->isNotEmpty())
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3 px-1">
                    <div class="flex items-center gap-3">
                        <flux:checkbox wire:model.live="selectAllIssued" />
                        <span class="text-sm font-medium text-zinc-600 dark:text-zinc-400">
                            @if(count($selectedIssueIds) > 0)
                                {{ count($selectedIssueIds) }} {{ Str::plural('certificate', count($selectedIssueIds)) }} selected
                            @else
                                Select all on this page
                            @endif
                        </span>
                    </div>

                    @if(count($selectedIssueIds) > 0)
                        <div class="flex items-center gap-2">
                            <flux:button
                                variant="outline"
                                size="sm"
                                wire:click="bulkRevokeCertificates"
                                wire:confirm="Revoke all selected issued certificates?"
                                wire:loading.attr="disabled"
                                wire:target="bulkRevokeCertificates"
                            >
                                <div class="flex items-center justify-center">
                                    <flux:icon name="x-circle" class="w-4 h-4 mr-1 text-red-500" />
                                    Revoke
                                </div>
                            </flux:button>

                            <flux:button
                                variant="outline"
                                size="sm"
                                wire:click="bulkReinstateCertificates"
                                wire:confirm="Reinstate all selected revoked certificates?"
                                wire:loading.attr="disabled"
                                wire:target="bulkReinstateCertificates"
                            >
                                <div class="flex items-center justify-center">
                                    <flux:icon name="arrow-path" class="w-4 h-4 mr-1 text-emerald-500" />
                                    Reinstate
                                </div>
                            </flux:button>

                            <flux:button
                                variant="primary"
                                size="sm"
                                wire:click="bulkDownloadCertificates"
                                wire:loading.attr="disabled"
                                wire:target="bulkDownloadCertificates"
                            >
                                <div class="flex items-center justify-center">
                                    <flux:icon name="arrow-down-tray" class="w-4 h-4 mr-1" />
                                    <span wire:loading.remove wire:target="bulkDownloadCertificates">Download ZIP</span>
                                    <span wire:loading wire:target="bulkDownloadCertificates">Preparing...</span>
                                </div>
                            </flux:button>

                            <flux:button
                                variant="outline"
                                size="sm"
                                wire:click="bulkRegeneratePdfs"
                                wire:confirm="Regenerate PDFs for selected certificates? This will update them with the latest template design."
                                wire:loading.attr="disabled"
                                wire:target="bulkRegeneratePdfs"
                            >
                                <div class="flex items-center justify-center">
                                    <flux:icon name="arrow-path" class="w-4 h-4 mr-1 text-orange-500" />
                                    <span wire:loading.remove wire:target="bulkRegeneratePdfs">Regenerate</span>
                                    <span wire:loading wire:target="bulkRegeneratePdfs">Regenerating...</span>
                                </div>
                            </flux:button>

                            <flux:button
                                variant="outline"
                                size="sm"
                                wire:click="openBulkSendModal"
                                wire:loading.attr="disabled"
                                wire:target="openBulkSendModal"
                            >
                                <div class="flex items-center justify-center">
                                    <flux:icon name="paper-airplane" class="w-4 h-4 mr-1 text-blue-500" />
                                    <span wire:loading.remove wire:target="openBulkSendModal">Send</span>
                                    <span wire:loading wire:target="openBulkSendModal">Loading...</span>
                                </div>
                            </flux:button>

                            <flux:button
                                variant="ghost"
                                size="sm"
                                wire:click="$set('selectedIssueIds', [])"
                                icon="x-mark"
                                tooltip="Clear selection"
                            />
                        </div>
                    @endif
                </div>
            @endif

            @if($issuedCertificates->isEmpty())
                <div class="text-center py-8">
                    <flux:icon name="document-check" class="w-8 h-8 text-zinc-300 dark:text-zinc-600 mx-auto mb-2" />
                    <h4 class="text-sm font-medium text-zinc-500">No certificates issued yet</h4>
                    <p class="text-xs text-zinc-400 mt-1">Issue certificates to students using the button above</p>
                </div>
            @else
                <div class="overflow-x-auto -mx-5">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-zinc-50 dark:bg-zinc-800">
                                <th class="pl-3 pr-2 py-2 w-10"></th>
                                <th class="px-3 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Student</th>
                                <th class="px-3 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Certificate</th>
                                <th class="px-3 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Number</th>
                                <th class="px-3 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Issue Date</th>
                                <th class="px-3 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Status</th>
                                <th class="px-3 py-2 text-center text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Sent</th>
                                <th class="px-3 py-2 text-right text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-zinc-900 divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach($issuedCertificates as $issue)
                                <tr wire:key="issued-cert-{{ $issue->id }}" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                    <td class="pl-3 pr-2 py-2 w-10">
                                        <flux:checkbox wire:model.live="selectedIssueIds" value="{{ $issue->id }}" />
                                    </td>
                                    <td class="px-3 py-2">
                                        @if($issue->student)
                                            <div class="space-y-1">
                                                {{-- Editable Student Name --}}
                                                @if($editingNameIssueId === $issue->id)
                                                    <div class="flex items-center gap-1">
                                                        <flux:input
                                                            wire:model="editingName"
                                                            wire:keydown.enter="saveStudentName"
                                                            wire:keydown.escape="cancelEditingName"
                                                            size="sm"
                                                            class="!py-0.5 !text-sm max-w-[180px]"
                                                            autofocus
                                                        />
                                                        <flux:button variant="ghost" size="sm" wire:click="saveStudentName" icon="check" class="!p-1 text-emerald-500" />
                                                        <flux:button variant="ghost" size="sm" wire:click="cancelEditingName" icon="x-mark" class="!p-1 text-zinc-400" />
                                                    </div>
                                                @else
                                                    <div class="flex items-center gap-1.5 group/name">
                                                        <a href="{{ route('students.show', $issue->student) }}" class="text-sm font-medium text-zinc-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                                            {{ $issue->student->user?->name ?? 'Unknown Student' }}
                                                        </a>
                                                        <button wire:click="startEditingName({{ $issue->id }})" class="opacity-0 group-hover/name:opacity-100 transition-opacity" title="Edit name">
                                                            <flux:icon name="pencil-square" class="size-3.5 text-zinc-400 hover:text-blue-500" />
                                                        </button>
                                                    </div>
                                                @endif

                                                {{-- Phone Number with Call & WhatsApp --}}
                                                @php $phone = $issue->student->phone_number; @endphp
                                                @if($phone)
                                                    <div class="flex items-center gap-1.5">
                                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $phone }}</span>
                                                        <a href="tel:{{ $phone }}" class="inline-flex" title="Call">
                                                            <flux:icon name="phone" class="size-3.5 text-blue-500 hover:text-blue-600" />
                                                        </a>
                                                        <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $phone) }}" target="_blank" class="inline-flex" title="WhatsApp">
                                                            <svg class="size-3.5 text-emerald-500 hover:text-emerald-600" viewBox="0 0 24 24" fill="currentColor">
                                                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                                            </svg>
                                                        </a>
                                                    </div>
                                                @else
                                                    <span class="text-xs text-zinc-400">No phone</span>
                                                @endif

                                                {{-- Email --}}
                                                @php $email = $issue->student->user?->email; @endphp
                                                @if($email)
                                                    <div class="flex items-center gap-1.5">
                                                        <flux:icon name="envelope" class="size-3 text-zinc-400" />
                                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $email }}</span>
                                                    </div>
                                                @else
                                                    <div class="flex items-center gap-1.5">
                                                        <flux:icon name="envelope" class="size-3 text-red-400" />
                                                        <span class="text-xs text-red-500">No email</span>
                                                    </div>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-sm text-zinc-400">Unknown Student</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $issue->certificate?->name ?? 'Unknown Certificate' }}</span>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <code class="text-xs font-mono text-zinc-500 dark:text-zinc-400 bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded tabular-nums">{{ $issue->certificate_number }}</code>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $issue->issue_date->format('M d, Y') }}</span>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        @if($issue->status === 'issued')
                                            <flux:badge color="green" size="sm">Issued</flux:badge>
                                        @else
                                            <flux:badge color="red" size="sm">{{ ucfirst($issue->status) }}</flux:badge>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-center">
                                        @php
                                            $sendLogs = $issue->logs->whereIn('action', ['sent_email', 'sent_whatsapp', 'sent_waba']);
                                            $hasSent = $sendLogs->isNotEmpty();
                                        @endphp
                                        @if($hasSent)
                                            <button wire:click="openLogModal({{ $issue->id }})" class="inline-flex items-center gap-1 group">
                                                <div class="flex items-center gap-0.5">
                                                    @if($sendLogs->where('action', 'sent_email')->isNotEmpty())
                                                        <flux:icon name="envelope" class="size-3.5 text-blue-500" />
                                                    @endif
                                                    @if($sendLogs->where('action', 'sent_whatsapp')->isNotEmpty() || $sendLogs->where('action', 'sent_waba')->isNotEmpty())
                                                        <svg class="size-3.5 text-emerald-500" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                                    @endif
                                                </div>
                                                <span class="text-xs text-zinc-500 group-hover:text-blue-600 transition-colors">{{ $sendLogs->count() }}x</span>
                                            </button>
                                        @else
                                            <span class="text-xs text-zinc-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            @if($issue->hasFile())
                                                <flux:button variant="ghost" size="sm" href="{{ $issue->getFileUrl() }}" target="_blank" icon="eye" />
                                                <flux:button variant="ghost" size="sm" href="{{ $issue->getDownloadUrl() }}" icon="arrow-down-tray" />
                                                @if($issue->isIssued())
                                                    <flux:tooltip content="Send Certificate">
                                                        <flux:button variant="ghost" size="sm" wire:click="openSendModal({{ $issue->id }})" icon="paper-airplane" />
                                                    </flux:tooltip>
                                                @endif
                                            @endif
                                            <flux:tooltip content="{{ $issue->hasFile() ? 'Regenerate PDF' : 'Generate PDF' }}">
                                                <flux:button
                                                    variant="ghost"
                                                    size="sm"
                                                    wire:click="regeneratePdf({{ $issue->id }})"
                                                    wire:confirm="{{ $issue->hasFile() ? 'Regenerate this certificate PDF?' : 'Generate the PDF for this certificate?' }}"
                                                    icon="{{ $issue->hasFile() ? 'arrow-path' : 'document-arrow-down' }}"
                                                />
                                            </flux:tooltip>
                                            @if($issue->canBeRevoked())
                                                <flux:button
                                                    variant="ghost"
                                                    size="sm"
                                                    wire:click="revokeCertificate({{ $issue->id }})"
                                                    wire:confirm="Are you sure you want to revoke this certificate?"
                                                    icon="x-circle"
                                                />
                                            @elseif($issue->canBeReinstated())
                                                <flux:button
                                                    variant="ghost"
                                                    size="sm"
                                                    wire:click="reinstateCertificate({{ $issue->id }})"
                                                    wire:confirm="Reinstate this certificate? It will be marked as issued again."
                                                    icon="arrow-path"
                                                    tooltip="Reinstate"
                                                />
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $issuedCertificates->links() }}
                </div>
            @endif
        </div>
    </div>
    @endif

    <!-- Send Logs Section -->
    @if($activeSection === 'send-logs')
    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700">
        <div class="border-b border-zinc-200 dark:border-zinc-700 px-5 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Send Logs</h3>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">History of all certificate deliveries for this class</p>
            </div>
            <div class="flex items-center gap-2">
                <flux:input
                    wire:model.live.debounce.300ms="logSearch"
                    placeholder="Search student..."
                    icon="magnifying-glass"
                    size="sm"
                />
                <div class="w-40 shrink-0">
                    <flux:select wire:model.live="logActionFilter" size="sm">
                        <option value="all">All Channels</option>
                        <option value="sent_email">Email</option>
                        <option value="sent_whatsapp">WhatsApp (Onsend)</option>
                        <option value="sent_waba">WhatsApp (WABA)</option>
                    </flux:select>
                </div>
            </div>
        </div>

        <div class="px-5 py-4">

            @if($this->sendLogs->isEmpty())
                <div class="text-center py-8">
                    <flux:icon name="paper-airplane" class="w-8 h-8 text-zinc-300 dark:text-zinc-600 mx-auto mb-2" />
                    <h4 class="text-sm font-medium text-zinc-500">No send logs yet</h4>
                    <p class="text-xs text-zinc-400 mt-1">Logs will appear here when certificates are sent to students</p>
                </div>
            @else
                <div class="overflow-x-auto -mx-5">
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-zinc-50 dark:bg-zinc-800">
                                <th class="px-3 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Student</th>
                                <th class="px-3 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Certificate</th>
                                <th class="px-3 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Channel</th>
                                <th class="px-3 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Status</th>
                                <th class="px-3 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Sent By</th>
                                <th class="px-3 py-2 text-left text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-zinc-900 divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach($this->sendLogs as $log)
                                <tr wire:key="send-log-{{ $log->id }}" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                    <td class="px-3 py-2">
                                        <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ $log->certificateIssue?->student?->user?->name ?? 'Unknown' }}</span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $log->certificateIssue?->certificate?->name ?? '-' }}</span>
                                    </td>
                                    <td class="px-3 py-2">
                                        @if($log->action === 'sent_email')
                                            <flux:badge color="blue" size="sm">Email</flux:badge>
                                        @elseif($log->action === 'sent_whatsapp')
                                            <flux:badge color="green" size="sm">WhatsApp</flux:badge>
                                        @elseif($log->action === 'sent_waba')
                                            <flux:badge color="emerald" size="sm">WABA</flux:badge>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        @php $logMeta = $log->metadata ?? []; @endphp
                                        @if(($logMeta['status'] ?? '') === 'sent')
                                            <flux:badge color="green" size="sm">Sent</flux:badge>
                                        @elseif(($logMeta['status'] ?? '') === 'delivered')
                                            <flux:badge color="blue" size="sm">Delivered</flux:badge>
                                        @elseif(($logMeta['status'] ?? '') === 'read')
                                            <flux:badge color="indigo" size="sm">Read</flux:badge>
                                        @elseif(($logMeta['status'] ?? '') === 'failed')
                                            <div>
                                                @if(!empty($logMeta['error']))
                                                    <button type="button" x-on:click="
                                                        $dispatch('set-send-error', { error: @js($logMeta['error']), student: @js($log->certificateIssue?->student?->user?->name ?? 'Unknown'), channel: @js($log->action), date: @js($log->created_at->format('M d, Y H:i')) });
                                                        $flux.modal('send-error-detail').show();
                                                    " class="cursor-pointer">
                                                        <flux:badge color="red" size="sm">Failed</flux:badge>
                                                    </button>
                                                @else
                                                    <flux:badge color="red" size="sm">Failed</flux:badge>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-xs text-zinc-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $log->user?->name ?? 'System' }}</span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $log->created_at->format('M d, Y H:i') }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $this->sendLogs->links() }}
                </div>
            @endif
        </div>
    </div>
    @endif

    <!-- Send Error Detail Modal -->
    <div x-data="{ errorMsg: '', studentName: '', channelName: '', errorDate: '' }"
         @set-send-error.window="errorMsg = $event.detail.error; studentName = $event.detail.student; channelName = $event.detail.channel === 'sent_waba' ? 'WhatsApp (WABA)' : ($event.detail.channel === 'sent_whatsapp' ? 'WhatsApp' : 'Email'); errorDate = $event.detail.date">
        <flux:modal name="send-error-detail" class="max-w-md">
            <div class="space-y-4">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Send Failed</h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">Details about the failed delivery</p>
                </div>

                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Student</span>
                        <span class="text-sm text-zinc-900 dark:text-white" x-text="studentName"></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Channel</span>
                        <span class="text-sm text-zinc-900 dark:text-white" x-text="channelName"></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Date</span>
                        <span class="text-sm text-zinc-900 dark:text-white" x-text="errorDate"></span>
                    </div>

                    <flux:separator />

                    <div>
                        <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Error Message</span>
                        <div class="mt-1.5 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-3">
                            <p class="text-sm text-red-700 dark:text-red-300 break-words" x-text="errorMsg"></p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <flux:modal.close>
                        <flux:button variant="ghost">Close</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        </flux:modal>
    </div>

    <!-- Certificate Log Modal -->
    <flux:modal wire:model="showLogModal" class="max-w-lg">
        <div class="space-y-4">
            <div>
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Certificate Send History</h3>
                @if($this->logIssue)
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">{{ $this->logIssue->student?->user?->name ?? 'Unknown' }} — {{ $this->logIssue->certificate?->name ?? '' }}</p>
                @endif
            </div>

            <flux:separator />

            @if($this->logIssue && $this->logIssue->logs->isNotEmpty())
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    @foreach($this->logIssue->logs as $log)
                        <div class="flex items-start gap-3 p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/50">
                            <div class="flex-shrink-0 mt-0.5">
                                @if($log->action === 'sent_email')
                                    <flux:icon name="envelope" class="size-4 text-blue-500" />
                                @elseif(in_array($log->action, ['sent_whatsapp', 'sent_waba']))
                                    <svg class="size-4 text-emerald-500" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                @elseif($log->action === 'issued')
                                    <flux:icon name="check-circle" class="size-4 text-green-500" />
                                @elseif($log->action === 'revoked')
                                    <flux:icon name="x-circle" class="size-4 text-red-500" />
                                @elseif($log->action === 'downloaded')
                                    <flux:icon name="arrow-down-tray" class="size-4 text-indigo-500" />
                                @elseif($log->action === 'viewed')
                                    <flux:icon name="eye" class="size-4 text-blue-500" />
                                @else
                                    <flux:icon name="clock" class="size-4 text-zinc-400" />
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ $log->formatted_action }}</span>
                                        @if($log->metadata)
                                            @php $meta = $log->metadata; @endphp
                                            @if(($meta['status'] ?? '') === 'sent')
                                                <flux:badge color="green" size="sm">Sent</flux:badge>
                                            @elseif(($meta['status'] ?? '') === 'delivered')
                                                <flux:badge color="blue" size="sm">Delivered</flux:badge>
                                            @elseif(($meta['status'] ?? '') === 'read')
                                                <flux:badge color="indigo" size="sm">Read</flux:badge>
                                            @elseif(($meta['status'] ?? '') === 'failed')
                                                <flux:badge color="red" size="sm">Failed</flux:badge>
                                            @endif
                                        @endif
                                    </div>
                                    <span class="text-xs text-zinc-400">{{ $log->created_at->diffForHumans() }}</span>
                                </div>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-xs text-zinc-500">{{ $log->user?->name ?? 'System' }}</span>
                                    <span class="text-xs text-zinc-400">{{ $log->created_at->format('M d, Y H:i') }}</span>
                                </div>
                                @if($log->metadata)
                                    @php $meta = $log->metadata; @endphp
                                    <div class="flex items-center gap-2 mt-1 text-xs text-zinc-400">
                                        @if(!empty($meta['email']))
                                            <span>{{ $meta['email'] }}</span>
                                        @endif
                                        @if(!empty($meta['phone']))
                                            <span>{{ $meta['phone'] }}</span>
                                        @endif
                                        @if(!empty($meta['template']))
                                            <span>Template: {{ $meta['template'] }}</span>
                                        @endif
                                        @if(!empty($meta['error']))
                                            <span class="text-red-500">{{ $meta['error'] }}</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-6">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">No logs found for this certificate</p>
                </div>
            @endif

            <div class="flex justify-end pt-2">
                <flux:button variant="ghost" wire:click="closeLogModal">Close</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Assign Certificate Modal -->
    <flux:modal wire:model="showAssignModal" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Add Certificate Template</h3>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">Assign a certificate template to this class</p>
            </div>

            <flux:separator />

            <div class="space-y-4">
                <flux:field>
                    <flux:label>Certificate Template</flux:label>
                    @if($availableCertificates->isEmpty())
                        <div class="text-center py-8 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                            <flux:icon name="document-text" class="w-8 h-8 text-zinc-300 dark:text-zinc-600 mx-auto mb-2" />
                            <p class="text-xs text-zinc-400">All active certificates are already assigned</p>
                        </div>
                    @else
                        <flux:select wire:model="assignCertificateId">
                            <option value="">Choose a certificate...</option>
                            @foreach($availableCertificates as $cert)
                                <option value="{{ $cert->id }}">{{ $cert->name }} ({{ $cert->formatted_size }})</option>
                            @endforeach
                        </flux:select>
                    @endif
                    <flux:error name="assignCertificateId" />
                </flux:field>

                <flux:checkbox wire:model="assignAsDefault" label="Set as default certificate for this class" />
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button variant="ghost" wire:click="closeAssignModal">
                    Cancel
                </flux:button>
                <flux:button variant="primary" wire:click="assignCertificate" :disabled="$availableCertificates->isEmpty()" icon="plus">
                    Assign
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Certificate Preview Modal -->
    <flux:modal wire:model="showPreviewModal" class="max-w-5xl">
        @if($previewCertificate)
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $previewCertificate->name }}</h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                            {{ $previewCertificate->formatted_size }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:button variant="subtle" size="sm" href="{{ route('certificates.edit', $previewCertificate) }}" icon="pencil">
                            Edit Template
                        </flux:button>
                        <flux:button variant="subtle" size="sm" href="{{ route('certificates.preview', $previewCertificate) }}" icon="arrow-top-right-on-square">
                            Full Page
                        </flux:button>
                    </div>
                </div>

                <flux:separator />

                <!-- Certificate Canvas -->
                <div class="bg-zinc-100 dark:bg-zinc-900 rounded-lg p-6 overflow-auto">
                    <div class="flex justify-center">
                        <div
                            x-data="{ scale: 1 }"
                            x-init="
                                const updateScale = () => {
                                    const containerWidth = $el.parentElement.clientWidth - 48;
                                    const canvasWidth = {{ $previewCertificate->width }};
                                    scale = Math.min(1, containerWidth / canvasWidth);
                                };
                                updateScale();
                                window.addEventListener('resize', updateScale);
                            "
                            :style="`transform: scale(${scale}); transform-origin: top center;`"
                        >
                            <div
                                class="relative bg-white shadow-lg"
                                style="width: {{ $previewCertificate->width }}px; height: {{ $previewCertificate->height }}px; background-color: {{ $previewCertificate->background_color }};"
                            >
                                @if($previewCertificate->background_image)
                                    <img
                                        src="{{ Storage::url($previewCertificate->background_image) }}"
                                        alt="Background"
                                        class="absolute inset-0 w-full h-full"
                                        style="pointer-events: none; object-fit: fill;"
                                    />
                                @endif

                                @foreach($previewCertificate->elements ?? [] as $element)
                                    <div
                                        class="absolute"
                                        style="
                                            left: {{ $element['x'] }}px;
                                            top: {{ $element['y'] }}px;
                                            width: {{ $element['width'] }}px;
                                            height: {{ $element['height'] }}px;
                                            transform: rotate({{ $element['rotation'] ?? 0 }}deg);
                                            opacity: {{ $element['opacity'] ?? 1 }};
                                        "
                                    >
                                        @if($element['type'] === 'text')
                                            <div
                                                style="
                                                    font-size: {{ $element['fontSize'] ?? 16 }}px;
                                                    font-weight: {{ $element['fontWeight'] ?? 'normal' }};
                                                    font-style: {{ $element['fontStyle'] ?? 'normal' }};
                                                    text-decoration: {{ $element['textDecoration'] ?? 'none' }};
                                                    color: {{ $element['color'] ?? '#000000' }};
                                                    text-align: {{ $element['textAlign'] ?? 'left' }};
                                                    font-family: {{ $element['fontFamily'] ?? 'Arial, sans-serif' }};
                                                    line-height: {{ $element['lineHeight'] ?? 1.2 }};
                                                    letter-spacing: {{ $element['letterSpacing'] ?? 0 }}px;
                                                    white-space: pre-wrap;
                                                    word-wrap: break-word;
                                                "
                                            >{{ $element['content'] }}</div>
                                        @elseif($element['type'] === 'dynamic')
                                            <div
                                                style="
                                                    font-size: {{ $element['fontSize'] ?? 16 }}px;
                                                    font-weight: {{ $element['fontWeight'] ?? 'normal' }};
                                                    font-style: {{ $element['fontStyle'] ?? 'normal' }};
                                                    text-decoration: {{ $element['textDecoration'] ?? 'none' }};
                                                    color: {{ $element['color'] ?? '#000000' }};
                                                    text-align: {{ $element['textAlign'] ?? 'left' }};
                                                    font-family: {{ $element['fontFamily'] ?? 'Arial, sans-serif' }};
                                                    line-height: {{ $element['lineHeight'] ?? 1.2 }};
                                                    letter-spacing: {{ $element['letterSpacing'] ?? 0 }}px;
                                                    white-space: pre-wrap;
                                                    word-wrap: break-word;
                                                "
                                            >{{ $element['prefix'] ?? '' }}{{ $previewData[$element['field']] ?? '' }}{{ $element['suffix'] ?? '' }}</div>
                                        @elseif($element['type'] === 'image')
                                            @if(!empty($element['src']))
                                                <img
                                                    src="{{ Storage::url($element['src']) }}"
                                                    alt="{{ $element['alt'] ?? 'Image' }}"
                                                    style="width: 100%; height: 100%; object-fit: {{ $element['objectFit'] ?? 'contain' }};"
                                                />
                                            @endif
                                        @elseif($element['type'] === 'shape')
                                            <div
                                                style="
                                                    width: 100%;
                                                    height: 100%;
                                                    background-color: {{ $element['fillColor'] ?? 'transparent' }};
                                                    border: {{ $element['borderWidth'] ?? 0 }}px {{ $element['borderStyle'] ?? 'solid' }} {{ $element['borderColor'] ?? '#000000' }};
                                                    @if(($element['shape'] ?? 'rectangle') === 'circle') border-radius: 50%; @endif
                                                "
                                            ></div>
                                        @elseif($element['type'] === 'qr')
                                            <div class="flex items-center justify-center w-full h-full">
                                                <div class="text-center">
                                                    <flux:icon name="qr-code" class="size-10 mx-auto mb-1 text-zinc-400" />
                                                    <span class="text-xs text-zinc-500">QR Code</span>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sample Data Info -->
                <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-zinc-400">
                    <span>Sample data:</span>
                    @foreach($previewData as $key => $value)
                        <span><span class="text-zinc-500">{{ str_replace('_', ' ', ucfirst($key)) }}:</span> {{ $value }}</span>
                    @endforeach
                </div>
            </div>
        @endif
    </flux:modal>

    <!-- Bulk Issue Modal -->
    <flux:modal wire:model="showBulkIssueModal" class="max-w-2xl">
        <div class="space-y-6">
            <div>
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Issue Certificates</h3>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">Select a template and choose which students should receive certificates</p>
            </div>

            <flux:separator />

            <div class="space-y-5">
                <flux:field>
                    <flux:label>Certificate Template</flux:label>
                    <flux:select wire:model="selectedCertificateId">
                        <option value="">Choose a certificate...</option>
                        @foreach($assignedCertificates as $cert)
                            <option value="{{ $cert->id }}">{{ $cert->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:error name="selectedCertificateId" />
                </flux:field>

                <flux:field>
                    <div class="flex items-center justify-between mb-2">
                        <flux:label>Students ({{ count($selectedStudentIds) }} selected)</flux:label>
                        <flux:checkbox wire:model.live="selectAll" label="Select all" />
                    </div>
                    <flux:input wire:model.live.debounce.300ms="modalStudentSearch" placeholder="Search student..." icon="magnifying-glass" size="sm" class="mb-2" />
                    <div class="max-h-60 overflow-y-auto border border-zinc-200 dark:border-zinc-700 rounded-lg divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($eligibleStudents as $student)
                            @if($student && $student->user)
                                @php
                                    $alreadyIssued = in_array($student->id, $issuedStudentIds);
                                    $matchesSearch = empty($modalStudentSearch) || str_contains(strtolower($student->user->name), strtolower($modalStudentSearch)) || str_contains(strtolower($student->student_id ?? ''), strtolower($modalStudentSearch));
                                @endphp
                                @if($matchesSearch)
                                <label class="flex items-center gap-3 cursor-pointer px-3 py-2.5 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors {{ $alreadyIssued ? 'bg-emerald-50/50 dark:bg-emerald-900/10' : '' }}">
                                    <input
                                        type="checkbox"
                                        wire:model="selectedStudentIds"
                                        value="{{ $student->id }}"
                                        class="rounded border-zinc-300 dark:border-zinc-600 text-blue-600 focus:ring-blue-500"
                                    >
                                    <div class="flex-1 min-w-0 flex items-center gap-2">
                                        <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ $student->user->name }}</span>
                                        <span class="text-xs text-zinc-400">{{ $student->student_id }}</span>
                                        @if($alreadyIssued)
                                            <flux:badge color="green" size="sm">Issued</flux:badge>
                                        @endif
                                    </div>
                                </label>
                                @endif
                            @endif
                        @endforeach
                    </div>
                    <flux:error name="selectedStudentIds" />
                </flux:field>

                <flux:checkbox wire:model="skipExisting" label="Skip students who already have certificates" />
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button variant="ghost" wire:click="closeBulkIssueModal">
                    Cancel
                </flux:button>
                <flux:button variant="primary" wire:click="bulkIssueCertificates" icon="document-plus">
                    Issue {{ count($selectedStudentIds) > 0 ? count($selectedStudentIds) : '' }} {{ Str::plural('Certificate', max(count($selectedStudentIds), 2)) }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Send Certificate Modal -->
    <flux:modal wire:model="showSendModal" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                    {{ $isBulkSend ? 'Send Certificates' : 'Send Certificate' }}
                </h3>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                    {{ $isBulkSend ? count($sendIssueIds) . ' ' . Str::plural('certificate', count($sendIssueIds)) . ' will be sent' : 'Send this certificate to the student' }}
                </p>
            </div>

            <flux:separator />

            {{-- Recipients Preview --}}
            <div>
                <flux:label>Recipients</flux:label>
                <div class="mt-2 max-h-40 overflow-y-auto rounded-lg border border-zinc-200 dark:border-zinc-700 divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach($this->sendRecipients as $recipient)
                        <div wire:key="send-recipient-{{ $recipient['issue_id'] }}" class="flex items-center justify-between px-3 py-2 text-sm">
                            <div class="min-w-0">
                                <span class="font-medium text-zinc-900 dark:text-white">{{ $recipient['name'] }}</span>
                                <span class="text-zinc-400 ml-1 text-xs">{{ $recipient['certificate_number'] }}</span>
                                <div class="flex items-center gap-3 mt-0.5">
                                    @if($recipient['has_email'])
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $recipient['email'] }}</span>
                                    @else
                                        <span class="text-xs text-red-500">No email address</span>
                                    @endif
                                    @if($recipient['has_phone'])
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $recipient['phone'] }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0 ml-2">
                                @if($recipient['has_email'])
                                    <flux:icon name="envelope" class="size-4 text-emerald-500" />
                                @else
                                    <flux:tooltip content="No email address">
                                        <flux:icon name="envelope" class="size-4 text-red-400" />
                                    </flux:tooltip>
                                @endif
                                @if($recipient['has_phone'])
                                    <flux:icon name="phone" class="size-4 text-emerald-500" />
                                @else
                                    <flux:tooltip content="No phone number">
                                        <flux:icon name="phone" class="size-4 text-zinc-300 dark:text-zinc-600" />
                                    </flux:tooltip>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Channel Selection --}}
            <flux:field>
                <flux:label>Delivery Channel</flux:label>
                <flux:radio.group wire:model.live="sendChannel">
                    <flux:radio value="email" label="Email (PDF attachment)" />
                    <flux:radio value="whatsapp" label="WhatsApp (PDF document)" />
                    <flux:radio value="both" label="Both Email & WhatsApp" />
                </flux:radio.group>
                <flux:error name="sendChannel" />
            </flux:field>

            {{-- WhatsApp Provider Sub-option --}}
            @if(in_array($sendChannel, ['whatsapp', 'both']))
                <flux:field>
                    <flux:label>WhatsApp Provider</flux:label>
                    <flux:radio.group wire:model.live="whatsappProvider">
                        <flux:radio value="onsend" label="Onsend (Free-form message)" />
                        <flux:radio value="waba" label="WABA Official (Template message)" />
                    </flux:radio.group>
                </flux:field>

                @if($whatsappProvider === 'waba')
                    {{-- WABA Template Picker --}}
                    <flux:field>
                        <flux:label>WhatsApp Template</flux:label>
                        <flux:select wire:model.live="selectedWabaTemplateId" placeholder="Select a template...">
                            @foreach($this->wabaTemplates as $tpl)
                                <flux:select.option value="{{ $tpl['id'] }}">
                                    {{ $tpl['name'] }} ({{ $tpl['language'] }}) — {{ $tpl['status'] }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="selectedWabaTemplateId" />

                        @if(empty($this->wabaTemplates))
                            <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                                No templates found. Create one in <a href="{{ route('admin.whatsapp.templates') }}" class="underline" target="_blank">WhatsApp Templates</a>.
                            </p>
                        @elseif(!collect($this->wabaTemplates)->contains('status', 'APPROVED'))
                            <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">
                                No approved templates yet. Templates must be approved by Meta before sending.
                            </p>
                        @endif
                    </flux:field>

                    {{-- Template Preview --}}
                    @if($selectedWabaTemplateId)
                        @php
                            $selectedTemplate = collect($this->wabaTemplates)->firstWhere('id', (int) $selectedWabaTemplateId);
                            $bodyComponent = collect($selectedTemplate['components'] ?? [])->firstWhere('type', 'BODY');
                        @endphp
                        @if($bodyComponent)
                            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 bg-zinc-50 dark:bg-zinc-800/50">
                                <span class="text-[11px] font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">Template Preview</span>
                                <p class="text-sm text-zinc-700 dark:text-zinc-300 whitespace-pre-line">{!! e($bodyComponent['text'] ?? '') !!}</p>
                                @if(!empty($selectedTemplate['variable_mappings']['body'] ?? []))
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @foreach($selectedTemplate['variable_mappings']['body'] as $num => $field)
                                            @php $varLabel = '{'.'{'.$num.'}'.'}'; @endphp
                                            <flux:badge size="sm" color="blue">
                                                {{ $varLabel }} → {{ str_replace('_', ' ', $field) }}
                                            </flux:badge>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endif
                    @endif
                @endif
            @endif

            {{-- Email warning if recipients missing email --}}
            @if(in_array($sendChannel, ['email', 'both']))
                @php $missingEmail = collect($this->sendRecipients)->where('has_email', false); @endphp
                @if($missingEmail->isNotEmpty())
                    <flux:callout variant="warning">
                        <flux:callout.heading>Missing Email Address</flux:callout.heading>
                        <flux:callout.text>{{ $missingEmail->count() }} {{ Str::plural('recipient', $missingEmail->count()) }} {{ $missingEmail->count() === 1 ? 'has' : 'have' }} no email address and will be skipped: {{ $missingEmail->pluck('name')->join(', ') }}</flux:callout.text>
                    </flux:callout>
                @endif
            @endif

            {{-- WhatsApp warning if not enabled (only for Onsend) --}}
            @if(in_array($sendChannel, ['whatsapp', 'both']) && $whatsappProvider === 'onsend')
                @php $whatsAppEnabled = app(WhatsAppService::class)->isEnabled(); @endphp
                @if(!$whatsAppEnabled)
                    <flux:callout variant="warning">
                        <flux:callout.heading>WhatsApp Not Configured</flux:callout.heading>
                        <flux:callout.text>WhatsApp service is not enabled. Configure it in Settings to send via WhatsApp.</flux:callout.text>
                    </flux:callout>
                @endif
            @endif

            {{-- Missing phone warning --}}
            @if(in_array($sendChannel, ['whatsapp', 'both']))
                @php $missingPhone = collect($this->sendRecipients)->where('has_phone', false); @endphp
                @if($missingPhone->isNotEmpty())
                    <flux:callout variant="warning">
                        <flux:callout.heading>Missing Phone Number</flux:callout.heading>
                        <flux:callout.text>{{ $missingPhone->count() }} {{ Str::plural('recipient', $missingPhone->count()) }} {{ $missingPhone->count() === 1 ? 'has' : 'have' }} no phone number and will be skipped: {{ $missingPhone->pluck('name')->join(', ') }}</flux:callout.text>
                    </flux:callout>
                @endif
            @endif

            {{-- Message (hidden when WABA-only WhatsApp, shown for email or onsend) --}}
            @if(!($whatsappProvider === 'waba' && $sendChannel === 'whatsapp'))
                <flux:field>
                    <flux:label>Message</flux:label>
                    <flux:textarea wire:model="sendMessage" rows="6" placeholder="Enter the message to send with the certificate..." />
                    <flux:error name="sendMessage" />
                    <p class="text-xs text-zinc-400 dark:text-zinc-500 mt-1">
                        @if($sendChannel === 'both' && $whatsappProvider === 'waba')
                            This message will be used for the email body. WhatsApp will use the selected template.
                        @else
                            This message will be included in the email body and/or WhatsApp text message. The certificate PDF will be attached automatically.
                        @endif
                    </p>
                </flux:field>
            @endif

            {{-- Action Buttons --}}
            <div class="flex justify-end gap-3 pt-2">
                <flux:button variant="ghost" wire:click="closeSendModal">
                    Cancel
                </flux:button>
                <flux:button
                    variant="primary"
                    wire:click="sendCertificates"
                    wire:loading.attr="disabled"
                    wire:target="sendCertificates"
                    icon="paper-airplane"
                >
                    <span wire:loading.remove wire:target="sendCertificates">
                        Send {{ count($sendIssueIds) > 1 ? count($sendIssueIds) . ' ' . Str::plural('Certificate', count($sendIssueIds)) : 'Certificate' }}
                    </span>
                    <span wire:loading wire:target="sendCertificates">Sending...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Toast Notification -->
    <div
        x-data="{ show: false, message: '', type: 'success' }"
        x-on:notify.window="
            show = true;
            message = $event.detail[0]?.message || $event.detail.message || 'Operation successful';
            type = $event.detail[0]?.type || $event.detail.type || 'success';
            setTimeout(() => show = false, 5000)
        "
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform translate-y-2"
        x-transition:enter-end="opacity-100 transform translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 transform translate-y-0"
        x-transition:leave-end="opacity-0 transform translate-y-2"
        class="fixed bottom-4 right-4 z-50"
        style="display: none;"
    >
        <div
            x-show="type === 'success'"
            class="flex items-center gap-2 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-lg shadow-lg"
        >
            <flux:icon.check-circle class="w-5 h-5 text-green-600" />
            <span x-text="message"></span>
        </div>
        <div
            x-show="type === 'warning'"
            class="flex items-center gap-2 px-4 py-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg shadow-lg"
        >
            <flux:icon.exclamation-triangle class="w-5 h-5 text-amber-600" />
            <span x-text="message"></span>
        </div>
        <div
            x-show="type === 'error'"
            class="flex items-center gap-2 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-lg shadow-lg"
        >
            <flux:icon.exclamation-circle class="w-5 h-5 text-red-600" />
            <span x-text="message"></span>
        </div>
    </div>
</div>
