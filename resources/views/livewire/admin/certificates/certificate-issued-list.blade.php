<?php

use App\Models\Certificate;
use App\Models\CertificateIssue;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public ?int $certificateFilter = null;

    public string $sortBy = 'latest';

    public array $selectedIssueIds = [];

    public bool $selectAllIssued = false;

    public ?int $editingNameIssueId = null;

    public string $editingName = '';

    public function with(): array
    {
        $query = $this->baseQuery();

        $query = match ($this->sortBy) {
            'latest' => $query->latest('issue_date'),
            'oldest' => $query->oldest('issue_date'),
            'student' => $query->join('students', 'certificate_issues.student_id', '=', 'students.id')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->orderBy('users.name')
                ->select('certificate_issues.*'),
            'certificate' => $query->join('certificates', 'certificate_issues.certificate_id', '=', 'certificates.id')
                ->orderBy('certificates.name')
                ->select('certificate_issues.*'),
            default => $query->latest('issue_date'),
        };

        return [
            'issues' => $query->paginate(20),
            'certificates' => Certificate::orderBy('name')->get(['id', 'name']),
            'stats' => $this->getStats(),
        ];
    }

    private function baseQuery()
    {
        return CertificateIssue::query()
            ->with(['certificate', 'student.user', 'enrollment', 'class.course', 'issuedBy'])
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->where('certificate_number', 'like', "%{$this->search}%")
                        ->orWhereHas('student.user', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                        ->orWhereHas('certificate', fn ($q) => $q->where('name', 'like', "%{$this->search}%"));
                });
            })
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->certificateFilter, fn ($q) => $q->where('certificate_id', $this->certificateFilter));
    }

    private function getStats(): array
    {
        $counts = CertificateIssue::query()
            ->selectRaw("COUNT(*) as total")
            ->selectRaw("SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END) as issued")
            ->selectRaw("SUM(CASE WHEN status = 'revoked' THEN 1 ELSE 0 END) as revoked")
            ->first();

        return [
            'total' => (int) $counts->total,
            'issued' => (int) $counts->issued,
            'revoked' => (int) $counts->revoked,
        ];
    }

    public function updatedSelectAllIssued(bool $value): void
    {
        if ($value) {
            $this->selectedIssueIds = $this->baseQuery()
                ->latest('issue_date')
                ->paginate(20)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->toArray();
        } else {
            $this->selectedIssueIds = [];
        }
    }

    public function updatedSelectedIssueIds(): void
    {
        $currentPageIds = $this->baseQuery()
            ->latest('issue_date')
            ->paginate(20)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->toArray();

        $this->selectAllIssued = count($this->selectedIssueIds) > 0
            && count(array_intersect($this->selectedIssueIds, $currentPageIds)) === count($currentPageIds);
    }

    public function bulkRevokeCertificates(): void
    {
        if (empty($this->selectedIssueIds)) {
            return;
        }

        $issues = CertificateIssue::whereIn('id', $this->selectedIssueIds)->get();

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
            'message' => "Revoked {$revokedCount} " . Str::plural('certificate', $revokedCount) . '.',
        ]);
    }

    public function bulkReinstateCertificates(): void
    {
        if (empty($this->selectedIssueIds)) {
            return;
        }

        $issues = CertificateIssue::whereIn('id', $this->selectedIssueIds)->get();

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
            'message' => "Reinstated {$reinstatedCount} " . Str::plural('certificate', $reinstatedCount) . '.',
        ]);
    }

    public function bulkDownloadCertificates(): mixed
    {
        if (empty($this->selectedIssueIds)) {
            return null;
        }

        $issues = CertificateIssue::whereIn('id', $this->selectedIssueIds)
            ->get()
            ->filter(fn ($issue) => $issue->hasFile());

        if ($issues->isEmpty()) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'None of the selected certificates have generated PDF files.',
            ]);

            return null;
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'certs_') . '.zip';
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

        $downloadName = 'certificates-issued-' . date('Y-m-d') . '.zip';

        return response()->streamDownload(function () use ($zipContent) {
            echo $zipContent;
        }, $downloadName, [
            'Content-Type' => 'application/zip',
        ]);
    }

    public function bulkDeleteCertificates(): void
    {
        if (empty($this->selectedIssueIds)) {
            return;
        }

        $issues = CertificateIssue::whereIn('id', $this->selectedIssueIds)->get();

        $deletedCount = 0;
        foreach ($issues as $issue) {
            if ($issue->file_path && \Storage::disk('public')->exists($issue->file_path)) {
                \Storage::disk('public')->delete($issue->file_path);
            }
            $issue->delete();
            $deletedCount++;
        }

        $this->selectedIssueIds = [];
        $this->selectAllIssued = false;

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Deleted {$deletedCount} " . Str::plural('certificate', $deletedCount) . '.',
        ]);
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

        $issue = CertificateIssue::with(['student.user', 'certificate', 'enrollment'])->find($this->editingNameIssueId);
        if (! $issue?->student?->user) {
            $this->editingNameIssueId = null;
            $this->editingName = '';

            return;
        }

        $issue->student->user->update(['name' => $this->editingName]);

        $dataSnapshot = $issue->data_snapshot ?? [];
        $dataSnapshot['student_name'] = $this->editingName;
        $issue->update(['data_snapshot' => $dataSnapshot]);

        if ($issue->certificate) {
            try {
                $pdfGenerator = app(\App\Services\CertificatePdfGenerator::class);
                $issue->deleteFile();

                $data = $dataSnapshot;
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

    public function downloadCertificate(int $id): mixed
    {
        $issue = CertificateIssue::findOrFail($id);

        if (! $issue->hasFile()) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Certificate file not found.',
            ]);

            return null;
        }

        $issue->logAction('downloaded', auth()->user());

        return \Storage::disk('public')->download($issue->file_path, $issue->getDownloadFilename());
    }

    public function regeneratePdf(int $id): void
    {
        $issue = CertificateIssue::with(['certificate', 'student.user', 'enrollment'])->findOrFail($id);

        if (! $issue->certificate) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Certificate template not found.',
            ]);

            return;
        }

        try {
            $pdfGenerator = app(\App\Services\CertificatePdfGenerator::class);

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

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Certificate PDF regenerated successfully.',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to regenerate PDF: ' . $e->getMessage(),
            ]);
        }
    }

    public function revokeCertificate(int $id): void
    {
        $issue = CertificateIssue::findOrFail($id);

        if ($issue->isRevoked()) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Certificate is already revoked.',
            ]);

            return;
        }

        $issue->revoke('Revoked by admin', auth()->user());

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Certificate revoked successfully.',
        ]);
    }

    public function reinstateCertificate(int $id): void
    {
        $issue = CertificateIssue::findOrFail($id);

        if (! $issue->canBeReinstated()) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Certificate cannot be reinstated.',
            ]);

            return;
        }

        $issue->reinstate(auth()->user());

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Certificate reinstated successfully.',
        ]);
    }

    public function deleteCertificate(int $id): void
    {
        $issue = CertificateIssue::findOrFail($id);

        if ($issue->file_path && \Storage::disk('public')->exists($issue->file_path)) {
            \Storage::disk('public')->delete($issue->file_path);
        }

        $issue->delete();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Certificate deleted successfully.',
        ]);
    }

    public function updatingSearch(): void
    {
        $this->selectedIssueIds = [];
        $this->selectAllIssued = false;
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->selectedIssueIds = [];
        $this->selectAllIssued = false;
        $this->resetPage();
    }

    public function updatingCertificateFilter(): void
    {
        $this->selectedIssueIds = [];
        $this->selectAllIssued = false;
        $this->resetPage();
    }

    public function updatingSortBy(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'certificateFilter', 'sortBy']);
        $this->selectedIssueIds = [];
        $this->selectAllIssued = false;
        $this->resetPage();
    }
} ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Issued Certificates</flux:heading>
            <flux:text class="mt-2">Manage all issued certificates</flux:text>
        </div>
        <div class="flex items-center gap-3">
            <flux:button variant="outline" href="{{ route('certificates.bulk-issue') }}" icon="document-duplicate">
                Bulk Issue
            </flux:button>
            <flux:button variant="primary" href="{{ route('certificates.issue') }}" icon="document-plus">
                Issue Certificate
            </flux:button>
        </div>
    </div>

    <div class="mt-6 space-y-6">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <flux:card class="p-6">
                <div class="flex items-center">
                    <div class="rounded-md bg-blue-50 dark:bg-blue-900/30 p-3">
                        <flux:icon.document-text class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($stats['total']) }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Issued</p>
                    </div>
                </div>
            </flux:card>

            <flux:card class="p-6">
                <div class="flex items-center">
                    <div class="rounded-md bg-green-50 dark:bg-green-900/30 p-3">
                        <flux:icon.check-circle class="h-6 w-6 text-green-600 dark:text-green-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($stats['issued']) }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Active</p>
                    </div>
                </div>
            </flux:card>

            <flux:card class="p-6">
                <div class="flex items-center">
                    <div class="rounded-md bg-red-50 dark:bg-red-900/30 p-3">
                        <flux:icon.x-circle class="h-6 w-6 text-red-600 dark:text-red-400" />
                    </div>
                    <div class="ml-4">
                        <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($stats['revoked']) }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Revoked</p>
                    </div>
                </div>
            </flux:card>
        </div>

        <!-- Search and Filters + Table -->
        <flux:card>
            <div class="p-6 border-b border-gray-200 dark:border-zinc-700">
                <div class="flex flex-col gap-4">
                    <div class="flex-1">
                        <flux:input
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search by certificate number, student name, or certificate..."
                            icon="magnifying-glass"
                            autocomplete="off" />
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <flux:select wire:model.live="statusFilter" placeholder="Filter by status">
                                <flux:select.option value="">All Statuses</flux:select.option>
                                <flux:select.option value="issued">Issued</flux:select.option>
                                <flux:select.option value="revoked">Revoked</flux:select.option>
                            </flux:select>
                        </div>
                        <div>
                            <flux:select wire:model.live="certificateFilter" placeholder="Filter by certificate">
                                <flux:select.option value="">All Certificates</flux:select.option>
                                @foreach($certificates as $certificate)
                                    <flux:select.option value="{{ $certificate->id }}">{{ $certificate->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        <div>
                            <flux:select wire:model.live="sortBy" placeholder="Sort by">
                                <flux:select.option value="latest">Latest First</flux:select.option>
                                <flux:select.option value="oldest">Oldest First</flux:select.option>
                                <flux:select.option value="student">Student Name</flux:select.option>
                                <flux:select.option value="certificate">Certificate Name</flux:select.option>
                            </flux:select>
                        </div>
                        @if($search || $statusFilter || $certificateFilter || $sortBy !== 'latest')
                            <div>
                                <flux:button wire:click="clearFilters" variant="ghost" class="w-full">
                                    Clear Filters
                                </flux:button>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Active Filters Display -->
                @if($search || $statusFilter || $certificateFilter)
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Active filters:</span>

                        @if($search)
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-300">
                                <flux:icon name="magnifying-glass" class="w-3 h-3" />
                                Search: "{{ $search }}"
                                <button wire:click="$set('search', '')" class="ml-1 hover:text-blue-600 dark:hover:text-blue-400">
                                    <flux:icon name="x-mark" class="w-3 h-3" />
                                </button>
                            </span>
                        @endif

                        @if($statusFilter)
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm bg-purple-100 dark:bg-purple-900/50 text-purple-800 dark:text-purple-300">
                                <flux:icon name="funnel" class="w-3 h-3" />
                                Status: {{ ucfirst($statusFilter) }}
                                <button wire:click="$set('statusFilter', '')" class="ml-1 hover:text-purple-600 dark:hover:text-purple-400">
                                    <flux:icon name="x-mark" class="w-3 h-3" />
                                </button>
                            </span>
                        @endif

                        @if($certificateFilter)
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-sm bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-300">
                                <flux:icon name="document-text" class="w-3 h-3" />
                                Certificate: {{ $certificates->find($certificateFilter)?->name ?? 'Unknown' }}
                                <button wire:click="$set('certificateFilter', null)" class="ml-1 hover:text-amber-600 dark:hover:text-amber-400">
                                    <flux:icon name="x-mark" class="w-3 h-3" />
                                </button>
                            </span>
                        @endif

                        <button wire:click="clearFilters" class="text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 underline">
                            Clear all
                        </button>
                    </div>
                @endif
            </div>

            <!-- Bulk Action Toolbar -->
            @if(count($selectedIssueIds) > 0)
                <div class="px-6 py-3 bg-blue-50 dark:bg-blue-900/20 border-b border-blue-200 dark:border-blue-800">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm font-medium text-blue-800 dark:text-blue-200">
                            {{ count($selectedIssueIds) }} {{ Str::plural('certificate', count($selectedIssueIds)) }} selected
                        </p>
                        <div class="flex items-center gap-2">
                            <flux:button
                                variant="outline"
                                size="sm"
                                wire:click="bulkDownloadCertificates"
                                wire:loading.attr="disabled"
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
                                wire:click="bulkRevokeCertificates"
                                wire:confirm="Are you sure you want to revoke the selected certificates?"
                            >
                                <div class="flex items-center justify-center">
                                    <flux:icon name="x-circle" class="w-4 h-4 mr-1" />
                                    Revoke
                                </div>
                            </flux:button>

                            <flux:button
                                variant="outline"
                                size="sm"
                                wire:click="bulkReinstateCertificates"
                                wire:confirm="Are you sure you want to reinstate the selected certificates?"
                            >
                                <div class="flex items-center justify-center">
                                    <flux:icon name="check-circle" class="w-4 h-4 mr-1" />
                                    Reinstate
                                </div>
                            </flux:button>

                            <flux:button
                                variant="danger"
                                size="sm"
                                wire:click="bulkDeleteCertificates"
                                wire:confirm="Are you sure you want to delete the selected certificates? This action cannot be undone."
                            >
                                <div class="flex items-center justify-center">
                                    <flux:icon name="trash" class="w-4 h-4 mr-1" />
                                    Delete
                                </div>
                            </flux:button>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Results Count -->
            <div class="px-6 py-3 bg-gray-50 dark:bg-zinc-700/50 border-b border-gray-200 dark:border-zinc-700">
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    @if($search || $statusFilter || $certificateFilter)
                        Showing <span class="font-medium">{{ $issues->total() }}</span> results
                        @if($issues->total() !== $stats['total'])
                            out of <span class="font-medium">{{ $stats['total'] }}</span> certificates
                        @endif
                    @else
                        Showing <span class="font-medium">{{ $issues->total() }}</span> certificates
                    @endif
                </p>
            </div>

            <!-- Certificates Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-zinc-700">
                    <thead class="bg-gray-50 dark:bg-zinc-700/50">
                        <tr>
                            <th class="w-12 px-4 py-3">
                                <flux:checkbox wire:model.live="selectAllIssued" />
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Certificate No.</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Student</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Certificate</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Class</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Issued Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-zinc-800 divide-y divide-gray-200 dark:divide-zinc-700">
                        @forelse($issues as $issue)
                            <tr wire:key="issue-{{ $issue->id }}" class="hover:bg-gray-50 dark:hover:bg-zinc-700/50 {{ in_array((string) $issue->id, $selectedIssueIds) ? 'bg-blue-50/50 dark:bg-blue-900/10' : '' }}">
                                <td class="w-12 px-4 py-4">
                                    <flux:checkbox wire:model.live="selectedIssueIds" value="{{ $issue->id }}" />
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-mono font-medium text-gray-900 dark:text-gray-100">{{ $issue->certificate_number }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <flux:avatar size="sm" class="mr-3 shrink-0">
                                            {{ $issue->student->user?->initials() ?? '?' }}
                                        </flux:avatar>
                                        <div class="min-w-0">
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
                                                    <a href="{{ route('students.show', $issue->student) }}" class="text-sm font-medium text-gray-900 dark:text-white hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                                                        {{ $issue->student->user?->name ?? 'Unknown Student' }}
                                                    </a>
                                                    <button wire:click="startEditingName({{ $issue->id }})" class="opacity-0 group-hover/name:opacity-100 transition-opacity" title="Edit name">
                                                        <flux:icon name="pencil-square" class="size-3.5 text-zinc-400 hover:text-blue-500" />
                                                    </button>
                                                </div>
                                            @endif

                                            @php $phone = $issue->student->phone_number; @endphp
                                            @if($phone)
                                                <div class="flex items-center gap-1.5 mt-0.5">
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
                                                <span class="text-xs text-zinc-400 dark:text-zinc-500">No phone</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-gray-100">{{ $issue->certificate->name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($issue->class)
                                        <div>
                                            <div class="text-sm text-gray-900 dark:text-gray-100">{{ $issue->class->title }}</div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $issue->class->course->title ?? '-' }}</div>
                                        </div>
                                    @else
                                        <span class="text-sm text-gray-400 dark:text-gray-500">-</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-gray-100">{{ $issue->issue_date->format('M d, Y') }}</div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ $issue->issue_date->format('h:i A') }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <flux:badge size="sm" :color="$issue->status === 'issued' ? 'green' : 'red'">
                                        {{ ucfirst($issue->status) }}
                                    </flux:badge>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        @if($issue->hasFile())
                                            <flux:tooltip content="View Certificate" position="left">
                                                <flux:button
                                                    variant="ghost"
                                                    size="sm"
                                                    href="{{ $issue->getFileUrl() }}"
                                                    target="_blank"
                                                    icon="eye"
                                                    square
                                                />
                                            </flux:tooltip>

                                            <flux:tooltip content="Download" position="left">
                                                <flux:button
                                                    variant="ghost"
                                                    size="sm"
                                                    wire:click="downloadCertificate({{ $issue->id }})"
                                                    icon="arrow-down-tray"
                                                    square
                                                />
                                            </flux:tooltip>
                                        @endif

                                        <flux:tooltip content="{{ $issue->hasFile() ? 'Regenerate PDF' : 'Generate PDF' }}" position="left">
                                            <flux:button
                                                variant="ghost"
                                                size="sm"
                                                wire:click="regeneratePdf({{ $issue->id }})"
                                                wire:confirm="{{ $issue->hasFile() ? 'Regenerate this certificate PDF? The existing file will be replaced.' : 'Generate the PDF for this certificate?' }}"
                                                icon="arrow-path"
                                                square
                                            />
                                        </flux:tooltip>

                                        @if($issue->isIssued())
                                            <flux:tooltip content="Revoke Certificate" position="left">
                                                <flux:button
                                                    variant="ghost"
                                                    size="sm"
                                                    wire:click="revokeCertificate({{ $issue->id }})"
                                                    wire:confirm="Are you sure you want to revoke this certificate?"
                                                    icon="x-circle"
                                                    square
                                                />
                                            </flux:tooltip>
                                        @elseif($issue->isRevoked())
                                            <flux:tooltip content="Reinstate Certificate" position="left">
                                                <flux:button
                                                    variant="ghost"
                                                    size="sm"
                                                    wire:click="reinstateCertificate({{ $issue->id }})"
                                                    wire:confirm="Reinstate this certificate?"
                                                    icon="check-circle"
                                                    square
                                                />
                                            </flux:tooltip>
                                        @endif

                                        <flux:tooltip content="Delete Certificate" position="left">
                                            <flux:button
                                                variant="ghost"
                                                size="sm"
                                                wire:click="deleteCertificate({{ $issue->id }})"
                                                wire:confirm="Are you sure you want to delete this certificate? This action cannot be undone."
                                                icon="trash"
                                                square
                                            />
                                        </flux:tooltip>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center">
                                    <flux:icon.document-text class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500" />
                                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No certificates found</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        @if($search || $statusFilter || $certificateFilter)
                                            Try adjusting your search or filter criteria.
                                        @else
                                            Get started by issuing your first certificate.
                                        @endif
                                    </p>
                                    @if(!$search && !$statusFilter && !$certificateFilter)
                                        <div class="mt-6">
                                            <flux:button variant="primary" href="{{ route('certificates.issue') }}" icon="document-plus">
                                                Issue First Certificate
                                            </flux:button>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($issues->hasPages())
                <div class="px-6 py-4 border-t border-gray-200 dark:border-zinc-700">
                    {{ $issues->links() }}
                </div>
            @endif
        </flux:card>
    </div>
</div>
