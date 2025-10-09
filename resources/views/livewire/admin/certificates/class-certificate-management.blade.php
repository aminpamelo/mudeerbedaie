<?php

use App\Models\ClassModel;
use App\Models\Certificate;
use App\Models\CertificateIssue;
use App\Services\CertificateService;
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

    public function mount(ClassModel $class): void
    {
        $this->class = $class->load(['certificates', 'course.certificates', 'activeStudents.student.user']);
    }

    public function with(): array
    {
        $defaultCertificate = $this->class->getDefaultCertificate();

        $assignedCertificates = collect($this->class->certificates)
            ->merge($this->class->course->certificates)
            ->unique('id');

        $stats = $this->class->getCertificateIssuanceStats($defaultCertificate);

        $issuedCertificates = CertificateIssue::where('class_id', $this->class->id)
            ->with(['certificate', 'student.user', 'issuedBy'])
            ->when($this->filterStatus !== 'all', fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->searchStudent, function ($q) {
                $q->whereHas('student.user', function ($query) {
                    $query->where('name', 'like', "%{$this->searchStudent}%");
                });
            })
            ->latest()
            ->paginate(10);

        $eligibleStudents = $this->class->activeStudents()
            ->with('student.user')
            ->get()
            ->pluck('student');

        return [
            'defaultCertificate' => $defaultCertificate,
            'assignedCertificates' => $assignedCertificates,
            'stats' => $stats,
            'issuedCertificates' => $issuedCertificates,
            'eligibleStudents' => $eligibleStudents,
        ];
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
}; ?>

<div>
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <flux:card>
            <div class="p-4">
                <flux:text class="text-sm text-gray-500 dark:text-gray-400">Total Students</flux:text>
                <flux:heading size="lg" class="mt-1">{{ $stats['total_students'] }}</flux:heading>
            </div>
        </flux:card>

        <flux:card>
            <div class="p-4">
                <flux:text class="text-sm text-gray-500 dark:text-gray-400">Certificates Issued</flux:text>
                <flux:heading size="lg" class="mt-1 text-green-600">{{ $stats['issued_count'] }}</flux:heading>
            </div>
        </flux:card>

        <flux:card>
            <div class="p-4">
                <flux:text class="text-sm text-gray-500 dark:text-gray-400">Pending</flux:text>
                <flux:heading size="lg" class="mt-1 text-yellow-600">{{ $stats['pending_count'] }}</flux:heading>
            </div>
        </flux:card>

        <flux:card>
            <div class="p-4">
                <flux:text class="text-sm text-gray-500 dark:text-gray-400">Completion Rate</flux:text>
                <flux:heading size="lg" class="mt-1">{{ $stats['completion_rate'] }}%</flux:heading>
            </div>
        </flux:card>
    </div>

    <!-- Assigned Certificates -->
    <flux:card class="mb-6">
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <flux:heading size="lg">Assigned Certificates</flux:heading>
                    <flux:text class="mt-1">Certificate templates available for this class</flux:text>
                </div>
                <flux:button variant="primary" wire:click="openBulkIssueModal" icon="document-plus">
                    Issue Certificates
                </flux:button>
            </div>

            @if($assignedCertificates->isEmpty())
                <div class="text-center py-8">
                    <flux:icon name="document-text" class="w-12 h-12 mx-auto mb-3 text-gray-400" />
                    <flux:text class="text-gray-500 dark:text-gray-400">No certificates assigned yet</flux:text>
                    <flux:text class="text-sm text-gray-500 dark:text-gray-400 mt-1">Assign certificates in the certificate management section</flux:text>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($assignedCertificates as $certificate)
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 {{ $defaultCertificate && $defaultCertificate->id === $certificate->id ? 'ring-2 ring-blue-500' : '' }}">
                            <div class="flex items-start justify-between mb-2">
                                <flux:heading size="sm">{{ $certificate->name }}</flux:heading>
                                @if($defaultCertificate && $defaultCertificate->id === $certificate->id)
                                    <flux:badge variant="primary" size="sm">Default</flux:badge>
                                @endif
                            </div>
                            <flux:text class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $certificate->formatted_size }}
                            </flux:text>
                            <div class="mt-3 flex gap-2">
                                <flux:button variant="outline" size="sm" href="{{ route('certificates.preview', $certificate) }}" icon="eye">
                                    Preview
                                </flux:button>
                                <flux:button variant="outline" size="sm" href="{{ route('certificates.edit', $certificate) }}" icon="pencil">
                                    Edit
                                </flux:button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </flux:card>

    <!-- Issued Certificates List -->
    <flux:card>
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="lg">Issued Certificates</flux:heading>
                <div class="flex items-center gap-3">
                    <flux:input
                        wire:model.live.debounce.300ms="searchStudent"
                        placeholder="Search student..."
                        icon="magnifying-glass"
                        class="w-64"
                    />
                    <flux:select wire:model.live="filterStatus" class="w-40">
                        <option value="all">All Status</option>
                        <option value="issued">Issued</option>
                        <option value="revoked">Revoked</option>
                    </flux:select>
                </div>
            </div>

            @if($issuedCertificates->isEmpty())
                <div class="text-center py-8">
                    <flux:icon name="document-check" class="w-12 h-12 mx-auto mb-3 text-gray-400" />
                    <flux:text class="text-gray-500 dark:text-gray-400">No certificates issued yet</flux:text>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Certificate</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Number</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Issue Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($issuedCertificates as $issue)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div>
                                                <a href="{{ route('students.show', $issue->student) }}" class="font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 hover:underline">
                                                    {{ $issue->student->user->name }}
                                                </a>
                                                <flux:text class="text-xs text-gray-500">{{ $issue->student->student_id }}</flux:text>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <flux:text class="text-sm">{{ $issue->certificate->name }}</flux:text>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <flux:text class="text-sm font-mono">{{ $issue->certificate_number }}</flux:text>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <flux:text class="text-sm">{{ $issue->issue_date->format('M d, Y') }}</flux:text>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <flux:badge :variant="$issue->status === 'issued' ? 'success' : 'danger'">
                                            {{ ucfirst($issue->status) }}
                                        </flux:badge>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                        <div class="flex items-center gap-2">
                                            @if($issue->hasFile())
                                                <flux:button variant="ghost" size="sm" href="{{ $issue->getFileUrl() }}" target="_blank" icon="eye">
                                                    View
                                                </flux:button>
                                                <flux:button variant="ghost" size="sm" href="{{ $issue->getDownloadUrl() }}" icon="arrow-down-tray">
                                                    Download
                                                </flux:button>
                                            @endif
                                            @if($issue->canBeRevoked())
                                                <flux:button
                                                    variant="ghost"
                                                    size="sm"
                                                    wire:click="revokeCertificate({{ $issue->id }})"
                                                    wire:confirm="Are you sure you want to revoke this certificate?"
                                                    icon="x-circle"
                                                >
                                                    Revoke
                                                </flux:button>
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
    </flux:card>

    <!-- Bulk Issue Modal -->
    <flux:modal wire:model="showBulkIssueModal" class="max-w-2xl">
        <div class="pb-4 border-b border-gray-200 mb-4 pt-8">
            <flux:heading size="lg">Issue Certificates to Students</flux:heading>
            <flux:text class="mt-2">Select students and certificate template to issue</flux:text>
        </div>

        <div class="space-y-4">
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
                    <flux:label>Select Students</flux:label>
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" wire:model.live="selectAll" class="mr-2">
                        <span class="text-sm">Select All</span>
                    </label>
                </div>
                <div class="max-h-64 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg p-3 space-y-2">
                    @foreach($eligibleStudents as $student)
                        <label class="flex items-center cursor-pointer p-2 hover:bg-gray-50 dark:hover:bg-gray-800 rounded">
                            <input
                                type="checkbox"
                                wire:model="selectedStudentIds"
                                value="{{ $student->id }}"
                                class="mr-3"
                            >
                            <div class="flex-1">
                                <flux:text class="font-medium">{{ $student->user->name }}</flux:text>
                                <flux:text class="text-xs text-gray-500">{{ $student->student_id }}</flux:text>
                            </div>
                        </label>
                    @endforeach
                </div>
                <flux:error name="selectedStudentIds" />
            </flux:field>

            <flux:field>
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" wire:model="skipExisting" class="mr-2">
                    <span class="text-sm">Skip students who already have certificates</span>
                </label>
            </flux:field>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <flux:button variant="ghost" wire:click="closeBulkIssueModal">
                Cancel
            </flux:button>
            <flux:button variant="primary" wire:click="bulkIssueCertificates">
                Issue Certificates
            </flux:button>
        </div>
    </flux:modal>
</div>
