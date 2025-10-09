<?php

use App\Models\Certificate;
use App\Models\CertificateIssue;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public ?int $certificateFilter = null;

    public string $sortBy = 'latest';

    public function with(): array
    {
        $query = CertificateIssue::query()
            ->with(['certificate', 'student.user', 'enrollment.class.course', 'issuedBy'])
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->where('certificate_number', 'like', "%{$this->search}%")
                        ->orWhereHas('student.user', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                        ->orWhereHas('certificate', fn ($q) => $q->where('name', 'like', "%{$this->search}%"));
                });
            })
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->certificateFilter, fn ($q) => $q->where('certificate_id', $this->certificateFilter));

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
            'certificates' => Certificate::all(),
            'stats' => [
                'total' => CertificateIssue::count(),
                'issued' => CertificateIssue::where('status', 'issued')->count(),
                'revoked' => CertificateIssue::where('status', 'revoked')->count(),
            ],
        ];
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

        // Log the download
        $issue->logAction('downloaded', auth()->user());

        return \Storage::disk('public')->download($issue->file_path, $issue->certificate_number.'.pdf');
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

        $issue->revoke(auth()->user());

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Certificate revoked successfully.',
        ]);
    }

    public function deleteCertificate(int $id): void
    {
        $issue = CertificateIssue::findOrFail($id);

        // Delete the PDF file
        if (\Storage::exists($issue->file_path)) {
            \Storage::delete($issue->file_path);
        }

        $issue->delete();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Certificate deleted successfully.',
        ]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCertificateFilter(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'certificateFilter', 'sortBy']);
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

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:text variant="sm" class="text-gray-500 dark:text-gray-400">Total Issued</flux:text>
                    <flux:heading size="xl" class="mt-1">{{ number_format($stats['total']) }}</flux:heading>
                </div>
                <flux:icon name="document-text" class="w-12 h-12 text-gray-400" />
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:text variant="sm" class="text-gray-500 dark:text-gray-400">Active</flux:text>
                    <flux:heading size="xl" class="mt-1">{{ number_format($stats['issued']) }}</flux:heading>
                </div>
                <flux:icon name="check-circle" class="w-12 h-12 text-green-500" />
            </div>
        </flux:card>

        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:text variant="sm" class="text-gray-500 dark:text-gray-400">Revoked</flux:text>
                    <flux:heading size="xl" class="mt-1">{{ number_format($stats['revoked']) }}</flux:heading>
                </div>
                <flux:icon name="x-circle" class="w-12 h-12 text-red-500" />
            </div>
        </flux:card>
    </div>

    <!-- Filters -->
    <flux:card class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Search -->
            <div>
                <flux:field>
                    <flux:label>Search</flux:label>
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Certificate number, student name..."
                        icon="magnifying-glass"
                    />
                </flux:field>
            </div>

            <!-- Status Filter -->
            <div>
                <flux:field>
                    <flux:label>Status</flux:label>
                    <flux:select wire:model.live="statusFilter">
                        <option value="all">All Statuses</option>
                        <option value="issued">Issued</option>
                        <option value="revoked">Revoked</option>
                    </flux:select>
                </flux:field>
            </div>

            <!-- Certificate Filter -->
            <div>
                <flux:field>
                    <flux:label>Certificate</flux:label>
                    <flux:select wire:model.live="certificateFilter">
                        <option value="">All Certificates</option>
                        @foreach($certificates as $certificate)
                            <option value="{{ $certificate->id }}">{{ $certificate->name }}</option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>

            <!-- Sort By -->
            <div>
                <flux:field>
                    <flux:label>Sort By</flux:label>
                    <flux:select wire:model.live="sortBy">
                        <option value="latest">Latest First</option>
                        <option value="oldest">Oldest First</option>
                        <option value="student">Student Name</option>
                        <option value="certificate">Certificate Name</option>
                    </flux:select>
                </flux:field>
            </div>
        </div>

        <div class="mt-4 flex items-center justify-between">
            <flux:text variant="sm" class="text-gray-500 dark:text-gray-400">
                Showing {{ $issues->count() }} of {{ $issues->total() }} certificate(s)
            </flux:text>
            <flux:button variant="ghost" size="sm" wire:click="resetFilters" icon="arrow-path">
                Reset Filters
            </flux:button>
        </div>
    </flux:card>

    <!-- Issued Certificates List -->
    <flux:card>
        @if($issues->isEmpty())
            <div class="text-center py-12">
                <flux:icon name="document-text" class="w-16 h-16 mx-auto mb-4 text-gray-400" />
                <flux:heading size="lg" class="mb-2">No certificates found</flux:heading>
                <flux:text class="text-gray-500 dark:text-gray-400 mb-4">
                    @if($search || $statusFilter !== 'all' || $certificateFilter)
                        Try adjusting your filters or search query.
                    @else
                        Start by issuing a certificate to a student.
                    @endif
                </flux:text>
                @if(!$search && $statusFilter === 'all' && !$certificateFilter)
                    <flux:button variant="primary" href="{{ route('certificates.issue') }}" icon="document-plus">
                        Issue First Certificate
                    </flux:button>
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="border-b border-gray-200 dark:border-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">
                                Certificate Number
                            </th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">
                                Student
                            </th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">
                                Certificate
                            </th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">
                                Enrollment
                            </th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">
                                Issued Date
                            </th>
                            <th class="px-4 py-3 text-left text-sm font-medium text-gray-500 dark:text-gray-400">
                                Status
                            </th>
                            <th class="px-4 py-3 text-right text-sm font-medium text-gray-500 dark:text-gray-400">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($issues as $issue)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="px-4 py-4">
                                    <flux:text class="font-medium">{{ $issue->certificate_number }}</flux:text>
                                </td>
                                <td class="px-4 py-4">
                                    <div>
                                        <a href="{{ route('students.show', $issue->student) }}" class="font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 hover:underline">
                                            {{ $issue->student->name }}
                                        </a>
                                        <flux:text variant="sm" class="text-gray-500 dark:text-gray-400">
                                            {{ $issue->student->email }}
                                        </flux:text>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <flux:text>{{ $issue->certificate->name }}</flux:text>
                                </td>
                                <td class="px-4 py-4">
                                    @if($issue->enrollment)
                                        <div>
                                            <flux:text variant="sm">{{ $issue->enrollment->class->title }}</flux:text>
                                            <flux:text variant="sm" class="text-gray-500 dark:text-gray-400">
                                                {{ $issue->enrollment->class->course->title }}
                                            </flux:text>
                                        </div>
                                    @else
                                        <flux:text variant="sm" class="text-gray-400">-</flux:text>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    <flux:text variant="sm">{{ $issue->issue_date->format('M d, Y') }}</flux:text>
                                    <flux:text variant="sm" class="text-gray-500 dark:text-gray-400">
                                        {{ $issue->issue_date->format('h:i A') }}
                                    </flux:text>
                                </td>
                                <td class="px-4 py-4">
                                    <flux:badge :variant="$issue->status === 'issued' ? 'success' : 'danger'">
                                        {{ ucfirst($issue->status) }}
                                    </flux:badge>
                                </td>
                                <td class="px-4 py-4">
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
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="mt-4">
                {{ $issues->links() }}
            </div>
        @endif
    </flux:card>
</div>
