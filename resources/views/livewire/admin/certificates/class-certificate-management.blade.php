<?php

use App\Models\ClassModel;
use App\Models\Certificate;
use App\Models\CertificateIssue;
use App\Services\CertificateService;
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
            ->with(['certificate', 'student.user', 'issuedBy'])
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
    }

    public function closeBulkIssueModal(): void
    {
        $this->showBulkIssueModal = false;
        $this->selectedStudentIds = [];
        $this->selectAll = false;
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
                ->paginate(10)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->toArray();
        } else {
            $this->selectedIssueIds = [];
        }
    }

    public function updatedSelectedIssueIds(): void
    {
        $currentPageIds = $this->getFilteredIssuedQuery()
            ->paginate(10)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->toArray();

        $this->selectAllIssued = count($this->selectedIssueIds) > 0
            && count(array_intersect($this->selectedIssueIds, $currentPageIds)) === count($currentPageIds);
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
        <div class="relative overflow-hidden rounded-xl bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 p-4">
            <div class="flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700">
                    <flux:icon name="users" class="size-5 text-zinc-500 dark:text-zinc-400" />
                </div>
                <div class="min-w-0">
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400 truncate">Total Students</flux:text>
                    <p class="text-xl font-semibold text-zinc-900 dark:text-white tabular-nums">{{ $stats['total_students'] }}</p>
                </div>
            </div>
        </div>

        <div class="relative overflow-hidden rounded-xl bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 p-4">
            <div class="flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-900/30">
                    <flux:icon name="document-check" class="size-5 text-emerald-600 dark:text-emerald-400" />
                </div>
                <div class="min-w-0">
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400 truncate">Issued</flux:text>
                    <p class="text-xl font-semibold text-emerald-600 dark:text-emerald-400 tabular-nums">{{ $stats['issued_count'] }}</p>
                </div>
            </div>
        </div>

        <div class="relative overflow-hidden rounded-xl bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 p-4">
            <div class="flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 dark:bg-amber-900/30">
                    <flux:icon name="clock" class="size-5 text-amber-600 dark:text-amber-400" />
                </div>
                <div class="min-w-0">
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400 truncate">Pending</flux:text>
                    <p class="text-xl font-semibold text-amber-600 dark:text-amber-400 tabular-nums">{{ $stats['pending_count'] }}</p>
                </div>
            </div>
        </div>

        <div class="relative overflow-hidden rounded-xl bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 p-4">
            <div class="flex items-center gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/30">
                    <flux:icon name="chart-bar" class="size-5 text-blue-600 dark:text-blue-400" />
                </div>
                <div class="min-w-0">
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400 truncate">Completion</flux:text>
                    <p class="text-xl font-semibold text-zinc-900 dark:text-white tabular-nums">{{ $stats['completion_rate'] }}%</p>
                </div>
            </div>
            {{-- Progress bar --}}
            <div class="absolute bottom-0 inset-x-0 h-1 bg-zinc-100 dark:bg-zinc-700">
                <div class="h-full bg-blue-500 transition-all duration-500" style="width: {{ min($stats['completion_rate'], 100) }}%"></div>
            </div>
        </div>
    </div>

    <!-- Assigned Certificates -->
    <flux:card>
        <div class="p-5">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <flux:heading size="lg">Templates</flux:heading>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">Certificate templates assigned to this class</flux:text>
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

            @if($assignedCertificates->isEmpty())
                <div class="text-center py-10">
                    <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800 mb-3">
                        <flux:icon name="document-text" class="size-6 text-zinc-400" />
                    </div>
                    <flux:heading size="sm" class="text-zinc-600 dark:text-zinc-300">No templates assigned</flux:heading>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Click "Add Template" to assign a certificate to this class</flux:text>
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
                                        <flux:heading size="sm" class="truncate">{{ $certificate->name }}</flux:heading>
                                        @if($defaultCertificate && $defaultCertificate->id === $certificate->id)
                                            <flux:badge color="blue" size="sm">Default</flux:badge>
                                        @endif
                                    </div>
                                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                        {{ $certificate->formatted_size }}
                                    </flux:text>
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
    </flux:card>

    <!-- Issued Certificates List -->
    <flux:card>
        <div class="p-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                <div>
                    <flux:heading size="lg">Issued Certificates</flux:heading>
                    @if($issuedCertificates->total() > 0)
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">{{ $issuedCertificates->total() }} {{ Str::plural('certificate', $issuedCertificates->total()) }} issued</flux:text>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <flux:input
                        wire:model.live.debounce.300ms="searchStudent"
                        placeholder="Search student..."
                        icon="magnifying-glass"
                        size="sm"
                    />
                    <flux:select wire:model.live="filterStatus" size="sm">
                        <option value="all">All Status</option>
                        <option value="issued">Issued</option>
                        <option value="revoked">Revoked</option>
                    </flux:select>
                </div>
            </div>

            {{-- Bulk Action Bar --}}
            @if($issuedCertificates->isNotEmpty())
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3 px-1">
                    <div class="flex items-center gap-3">
                        <flux:checkbox wire:model.live="selectAllIssued" />
                        <flux:text size="sm" class="font-medium text-zinc-600 dark:text-zinc-400">
                            @if(count($selectedIssueIds) > 0)
                                {{ count($selectedIssueIds) }} {{ Str::plural('certificate', count($selectedIssueIds)) }} selected
                            @else
                                Select all on this page
                            @endif
                        </flux:text>
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
                <div class="text-center py-12">
                    <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800 mb-3">
                        <flux:icon name="document-check" class="size-6 text-zinc-400" />
                    </div>
                    <flux:heading size="sm" class="text-zinc-600 dark:text-zinc-300">No certificates issued yet</flux:heading>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Issue certificates to students using the button above</flux:text>
                </div>
            @else
                <div class="overflow-x-auto -mx-5">
                    <table class="min-w-full">
                        <thead>
                            <tr class="border-y border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50">
                                <th class="pl-5 pr-2 py-2.5 w-10"></th>
                                <th class="px-5 py-2.5 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Student</th>
                                <th class="px-5 py-2.5 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Certificate</th>
                                <th class="px-5 py-2.5 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Number</th>
                                <th class="px-5 py-2.5 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Issue Date</th>
                                <th class="px-5 py-2.5 text-left text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Status</th>
                                <th class="px-5 py-2.5 text-right text-xs font-medium text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach($issuedCertificates as $issue)
                                <tr wire:key="issued-cert-{{ $issue->id }}" class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30 transition-colors">
                                    <td class="pl-5 pr-2 py-3 w-10">
                                        <flux:checkbox wire:model.live="selectedIssueIds" value="{{ $issue->id }}" />
                                    </td>
                                    <td class="px-5 py-3">
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
                                            </div>
                                        @else
                                            <span class="text-sm text-zinc-400">Unknown Student</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $issue->certificate?->name ?? 'Unknown Certificate' }}</span>
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        <code class="text-xs font-mono text-zinc-500 dark:text-zinc-400 bg-zinc-100 dark:bg-zinc-800 px-1.5 py-0.5 rounded">{{ $issue->certificate_number }}</code>
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $issue->issue_date->format('M d, Y') }}</span>
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        @if($issue->status === 'issued')
                                            <flux:badge color="green" size="sm">Issued</flux:badge>
                                        @else
                                            <flux:badge color="red" size="sm">{{ ucfirst($issue->status) }}</flux:badge>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            @if($issue->hasFile())
                                                <flux:button variant="ghost" size="sm" href="{{ $issue->getFileUrl() }}" target="_blank" icon="eye" />
                                                <flux:button variant="ghost" size="sm" href="{{ $issue->getDownloadUrl() }}" icon="arrow-down-tray" />
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

                <div class="mt-4 px-5">
                    {{ $issuedCertificates->links() }}
                </div>
            @endif
        </div>
    </flux:card>

    <!-- Assign Certificate Modal -->
    <flux:modal wire:model="showAssignModal" class="max-w-lg">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Add Certificate Template</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Assign a certificate template to this class</flux:text>
            </div>

            <flux:separator />

            <div class="space-y-4">
                <flux:field>
                    <flux:label>Certificate Template</flux:label>
                    @if($availableCertificates->isEmpty())
                        <div class="text-center py-6 border border-zinc-200 dark:border-zinc-700 rounded-lg">
                            <flux:icon name="document-text" class="size-8 mx-auto mb-2 text-zinc-400" />
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">All active certificates are already assigned</flux:text>
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
                        <flux:heading size="lg">{{ $previewCertificate->name }}</flux:heading>
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">
                            {{ $previewCertificate->formatted_size }}
                        </flux:text>
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
                <flux:heading size="lg">Issue Certificates</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Select a template and choose which students should receive certificates</flux:text>
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
                    <div class="max-h-60 overflow-y-auto border border-zinc-200 dark:border-zinc-700 rounded-lg divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach($eligibleStudents as $student)
                            @if($student && $student->user)
                                @php $alreadyIssued = in_array($student->id, $issuedStudentIds); @endphp
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
</div>
