<?php

use App\Models\Certificate;
use App\Models\CertificateIssue;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\CertificatePdfGenerator;
use Livewire\Volt\Component;

new class extends Component
{
    public ?int $certificateId = null;

    public ?int $studentId = null;

    public ?int $enrollmentId = null;

    public string $searchStudents = '';

    public string $notes = '';

    public array $additionalData = [];

    public function mount(): void
    {
        // Check if certificate ID is passed via query parameter
        if (request()->has('certificate')) {
            $this->certificateId = (int) request()->get('certificate');
        }

        // Check if student ID is passed via query parameter
        if (request()->has('student')) {
            $this->studentId = (int) request()->get('student');
        }

        // Check if enrollment ID is passed via query parameter
        if (request()->has('enrollment')) {
            $this->enrollmentId = (int) request()->get('enrollment');

            // Auto-populate student and certificate from enrollment
            if ($this->enrollmentId) {
                $enrollment = Enrollment::find($this->enrollmentId);
                if ($enrollment) {
                    $this->studentId = $enrollment->student_id;

                    // Try to get default certificate for the class or course
                    $defaultCertificate = Certificate::query()
                        ->where('status', 'active')
                        ->where(function ($q) use ($enrollment) {
                            $q->whereHas('classes', fn ($q) => $q->where('class_id', $enrollment->class_id)->where('is_default', true))
                                ->orWhereHas('courses', fn ($q) => $q->where('course_id', $enrollment->class->course_id)->where('is_default', true));
                        })
                        ->first();

                    if ($defaultCertificate) {
                        $this->certificateId = $defaultCertificate->id;
                    }
                }
            }
        }
    }

    public function with(): array
    {
        $students = Student::query()
            ->with('user')
            ->when($this->searchStudents, fn ($q) => $q->whereHas('user', fn ($q) => $q->where('name', 'like', "%{$this->searchStudents}%")))
            ->limit(50)
            ->get();

        $certificates = Certificate::active()->get();

        $enrollments = [];
        if ($this->studentId) {
            $enrollments = Enrollment::where('student_id', $this->studentId)
                ->with(['class.course'])
                ->get();
        }

        return [
            'students' => $students,
            'certificates' => $certificates,
            'enrollments' => $enrollments,
        ];
    }

    public function selectStudent(int $studentId): void
    {
        $this->studentId = $studentId;
        $this->searchStudents = '';
        $this->enrollmentId = null;
    }

    public function clearStudent(): void
    {
        $this->studentId = null;
        $this->searchStudents = '';
        $this->enrollmentId = null;
    }

    public function updatedStudentId(): void
    {
        $this->enrollmentId = null;
    }

    public function issueCertificate(): void
    {
        $this->validate([
            'certificateId' => 'required|exists:certificates,id',
            'studentId' => 'required|exists:students,id',
        ]);

        $certificate = Certificate::findOrFail($this->certificateId);
        $student = Student::findOrFail($this->studentId);
        $enrollment = $this->enrollmentId ? Enrollment::find($this->enrollmentId) : null;

        // Check if certificate is active
        if (! $certificate->isActive()) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Cannot issue a certificate that is not active.',
            ]);

            return;
        }

        // Check if already issued
        $existingIssue = CertificateIssue::where('certificate_id', $certificate->id)
            ->where('student_id', $student->id)
            ->when($enrollment, fn ($q) => $q->where('enrollment_id', $enrollment->id))
            ->where('status', 'issued')
            ->first();

        if ($existingIssue) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'This certificate has already been issued to this student for this enrollment.',
            ]);

            return;
        }

        // Generate certificate PDF
        $pdfGenerator = new CertificatePdfGenerator;
        $filePath = $pdfGenerator->generate($certificate, $student, $enrollment, $this->additionalData);

        // Create certificate issue record
        $issue = CertificateIssue::create([
            'certificate_id' => $certificate->id,
            'student_id' => $student->id,
            'enrollment_id' => $enrollment?->id,
            'certificate_number' => CertificateIssue::generateCertificateNumber(),
            'issued_by' => auth()->id(),
            'issued_at' => now(),
            'file_path' => $filePath,
            'data_snapshot' => $pdfGenerator->prepareData($certificate, $student, $enrollment, $this->additionalData),
            'status' => 'issued',
            'notes' => $this->notes,
        ]);

        // Log the issuance
        $issue->logAction('issued', auth()->user());

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Certificate issued successfully!',
        ]);

        $this->redirect(route('certificates.issued'));
    }
} ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Issue Certificate</flux:heading>
            <flux:text class="mt-2">Issue a certificate to a student</flux:text>
        </div>
        <div class="flex items-center gap-3">
            <flux:button variant="outline" href="{{ route('certificates.issued') }}" icon="clipboard-document-check">
                View Issued
            </flux:button>
            <flux:button variant="outline" href="{{ route('certificates.index') }}" icon="arrow-left">
                Back to List
            </flux:button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Issue Form -->
        <div class="lg:col-span-2">
            <flux:card>
                <flux:heading size="lg" class="mb-6">Certificate Details</flux:heading>

                <div class="space-y-6">
                    <!-- Certificate Selection -->
                    <div>
                        <flux:field>
                            <flux:label>Certificate Template *</flux:label>
                            <flux:select wire:model.live="certificateId">
                                <option value="">Select a certificate template...</option>
                                @foreach($certificates as $certificate)
                                    <option value="{{ $certificate->id }}">
                                        {{ $certificate->name }} ({{ $certificate->formatted_size }})
                                    </option>
                                @endforeach
                            </flux:select>
                            <flux:error name="certificateId" />
                        </flux:field>

                        @if($certificateId)
                            <div class="mt-2">
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    href="{{ route('certificates.preview', $certificateId) }}"
                                    target="_blank"
                                    icon="eye"
                                >
                                    Preview Certificate
                                </flux:button>
                            </div>
                        @endif
                    </div>

                    <!-- Student Selection with Autocomplete -->
                    <div>
                        <flux:field>
                            <flux:label>Search and Select Student *</flux:label>
                            <div class="relative">
                                <flux:input
                                    wire:model.live.debounce.300ms="searchStudents"
                                    placeholder="Type student name to search..."
                                    icon="magnifying-glass"
                                />

                                @if($searchStudents && $students->isNotEmpty())
                                    <div class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                        @foreach($students as $student)
                                            <button
                                                type="button"
                                                wire:click="selectStudent({{ $student->id }})"
                                                class="w-full px-4 py-3 text-left hover:bg-gray-50 dark:hover:bg-gray-700 focus:bg-gray-50 dark:focus:bg-gray-700 focus:outline-none border-b border-gray-100 dark:border-gray-700 last:border-b-0"
                                            >
                                                <div class="font-medium text-gray-900 dark:text-gray-100">
                                                    {{ $student->name }}
                                                </div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    {{ $student->email }} • {{ $student->student_id }}
                                                </div>
                                            </button>
                                        @endforeach
                                    </div>
                                @endif

                                @if($searchStudents && $students->isEmpty())
                                    <div class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg p-4">
                                        <div class="text-center text-gray-500 dark:text-gray-400">
                                            <flux:icon name="magnifying-glass" class="w-8 h-8 mx-auto mb-2" />
                                            <div class="text-sm">No students found matching "{{ $searchStudents }}"</div>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            @if($studentId)
                                @php
                                    $selectedStudent = $students->firstWhere('id', $studentId) ?? Student::find($studentId);
                                @endphp
                                @if($selectedStudent)
                                    <div class="mt-2 flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                                        <div>
                                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ $selectedStudent->name }}</div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $selectedStudent->email }}</div>
                                        </div>
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            wire:click="clearStudent"
                                            icon="x-mark"
                                            square
                                        />
                                    </div>
                                @endif
                            @endif

                            <flux:error name="studentId" />
                        </flux:field>
                    </div>

                    <!-- Enrollment Selection (Optional) -->
                    @if($studentId && $enrollments->isNotEmpty())
                        <div>
                            <flux:field>
                                <flux:label>Related Enrollment (Optional)</flux:label>
                                <flux:select wire:model="enrollmentId">
                                    <option value="">No specific enrollment</option>
                                    @foreach($enrollments as $enrollment)
                                        <option value="{{ $enrollment->id }}">
                                            {{ $enrollment->class->title }} ({{ $enrollment->class->course->title }})
                                        </option>
                                    @endforeach
                                </flux:select>
                                <flux:text variant="sm" class="text-gray-500 dark:text-gray-400 mt-1">
                                    Select an enrollment to auto-populate certificate data
                                </flux:text>
                            </flux:field>
                        </div>
                    @endif

                    <!-- Notes -->
                    <div>
                        <flux:field>
                            <flux:label>Notes (Optional)</flux:label>
                            <flux:textarea
                                wire:model="notes"
                                rows="3"
                                placeholder="Add any notes about this certificate issuance..."
                            />
                        </flux:field>
                    </div>

                    <!-- Submit -->
                    <div class="flex items-center gap-3 pt-4">
                        <flux:button variant="primary" wire:click="issueCertificate">
                            Issue Certificate
                        </flux:button>
                        <flux:button variant="outline" href="{{ route('certificates.bulk-issue') }}">
                            Bulk Issue Instead
                        </flux:button>
                    </div>
                </div>
            </flux:card>
        </div>

        <!-- Preview & Info -->
        <div class="space-y-6">
            <!-- Selected Certificate Info -->
            @if($certificateId)
                @php
                    $selectedCertificate = Certificate::find($certificateId);
                @endphp

                @if($selectedCertificate)
                    <flux:card>
                        <flux:heading size="lg" class="mb-4">Selected Certificate</flux:heading>

                        <div class="space-y-3">
                            <div>
                                <flux:text variant="sm" class="font-medium text-gray-500 dark:text-gray-400">Name</flux:text>
                                <flux:text>{{ $selectedCertificate->name }}</flux:text>
                            </div>

                            @if($selectedCertificate->description)
                                <div>
                                    <flux:text variant="sm" class="font-medium text-gray-500 dark:text-gray-400">Description</flux:text>
                                    <flux:text>{{ $selectedCertificate->description }}</flux:text>
                                </div>
                            @endif

                            <div>
                                <flux:text variant="sm" class="font-medium text-gray-500 dark:text-gray-400">Size</flux:text>
                                <flux:text>{{ $selectedCertificate->formatted_size }}</flux:text>
                            </div>

                            <div>
                                <flux:text variant="sm" class="font-medium text-gray-500 dark:text-gray-400">Total Issues</flux:text>
                                <flux:badge variant="neutral">{{ $selectedCertificate->issues()->count() }}</flux:badge>
                            </div>
                        </div>
                    </flux:card>
                @endif
            @endif

            <!-- Selected Student Info -->
            @if($studentId)
                @php
                    $selectedStudent = Student::find($studentId);
                @endphp

                @if($selectedStudent)
                    <flux:card>
                        <flux:heading size="lg" class="mb-4">Selected Student</flux:heading>

                        <div class="space-y-3">
                            <div>
                                <flux:text variant="sm" class="font-medium text-gray-500 dark:text-gray-400">Name</flux:text>
                                <flux:text>{{ $selectedStudent->name }}</flux:text>
                            </div>

                            <div>
                                <flux:text variant="sm" class="font-medium text-gray-500 dark:text-gray-400">Email</flux:text>
                                <flux:text>{{ $selectedStudent->email }}</flux:text>
                            </div>

                            <div>
                                <flux:text variant="sm" class="font-medium text-gray-500 dark:text-gray-400">Student ID</flux:text>
                                <flux:text>{{ $selectedStudent->student_id }}</flux:text>
                            </div>

                            <div>
                                <flux:text variant="sm" class="font-medium text-gray-500 dark:text-gray-400">Certificates Received</flux:text>
                                <flux:badge variant="neutral">
                                    {{ $selectedStudent->certificateIssues()->where('status', 'issued')->count() }}
                                </flux:badge>
                            </div>
                        </div>
                    </flux:card>
                @endif
            @endif

            <!-- Help Card -->
            <flux:card>
                <flux:heading size="lg" class="mb-4">
                    <flux:icon name="information-circle" class="w-5 h-5 inline-block mr-1" />
                    How it Works
                </flux:heading>

                <div class="space-y-3">
                    <div>
                        <flux:text variant="sm" class="font-medium">1. Select Certificate</flux:text>
                        <flux:text variant="sm" class="text-gray-500 dark:text-gray-400">
                            Choose an active certificate template
                        </flux:text>
                    </div>

                    <div>
                        <flux:text variant="sm" class="font-medium">2. Select Student</flux:text>
                        <flux:text variant="sm" class="text-gray-500 dark:text-gray-400">
                            Search and select the student to receive the certificate
                        </flux:text>
                    </div>

                    <div>
                        <flux:text variant="sm" class="font-medium">3. Choose Enrollment (Optional)</flux:text>
                        <flux:text variant="sm" class="text-gray-500 dark:text-gray-400">
                            Link to a specific course enrollment for auto-populated data
                        </flux:text>
                    </div>

                    <div>
                        <flux:text variant="sm" class="font-medium">4. Issue Certificate</flux:text>
                        <flux:text variant="sm" class="text-gray-500 dark:text-gray-400">
                            PDF will be generated and saved automatically
                        </flux:text>
                    </div>
                </div>
            </flux:card>
        </div>
    </div>
</div>
