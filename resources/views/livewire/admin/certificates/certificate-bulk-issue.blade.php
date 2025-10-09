<?php

use App\Models\Certificate;
use App\Models\ClassModel;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\CertificateIssue;
use App\Services\CertificatePdfGenerator;
use Livewire\Volt\Component;

new class extends Component {
    public ?int $certificateId = null;

    public string $issueType = 'class';

    public ?int $classId = null;

    public ?int $courseId = null;

    public array $selectedStudentIds = [];

    public bool $selectAll = false;

    public string $notes = '';

    public bool $skipExisting = true;

    public int $issuedCount = 0;

    public int $skippedCount = 0;

    public function with(): array
    {
        $certificates = Certificate::active()->get();

        $classes = ClassModel::with('course')->get();

        $courses = Course::all();

        $students = collect([]);
        if ($this->issueType === 'class' && $this->classId) {
            $students = Enrollment::where('class_id', $this->classId)
                ->with('student')
                ->get()
                ->pluck('student');
        } elseif ($this->issueType === 'course' && $this->courseId) {
            $students = Enrollment::whereHas('class', fn ($q) => $q->where('course_id', $this->courseId))
                ->with('student')
                ->get()
                ->pluck('student')
                ->unique('id');
        }

        return [
            'certificates' => $certificates,
            'classes' => $classes,
            'courses' => $courses,
            'students' => $students,
        ];
    }

    public function updatedIssueType(): void
    {
        $this->classId = null;
        $this->courseId = null;
        $this->selectedStudentIds = [];
        $this->selectAll = false;
    }

    public function updatedClassId(): void
    {
        $this->selectedStudentIds = [];
        $this->selectAll = false;
    }

    public function updatedCourseId(): void
    {
        $this->selectedStudentIds = [];
        $this->selectAll = false;
    }

    public function updatedSelectAll($value): void
    {
        if ($value) {
            $this->selectedStudentIds = $this->getAvailableStudentIds();
        } else {
            $this->selectedStudentIds = [];
        }
    }

    private function getAvailableStudentIds(): array
    {
        if ($this->issueType === 'class' && $this->classId) {
            return Enrollment::where('class_id', $this->classId)
                ->pluck('student_id')
                ->toArray();
        } elseif ($this->issueType === 'course' && $this->courseId) {
            return Enrollment::whereHas('class', fn ($q) => $q->where('course_id', $this->courseId))
                ->pluck('student_id')
                ->unique()
                ->toArray();
        }

        return [];
    }

    public function bulkIssueCertificates(): void
    {
        $this->validate([
            'certificateId' => 'required|exists:certificates,id',
            'selectedStudentIds' => 'required|array|min:1',
            'selectedStudentIds.*' => 'exists:students,id',
        ]);

        $certificate = Certificate::findOrFail($this->certificateId);

        if (! $certificate->isActive()) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Cannot issue a certificate that is not active.',
            ]);

            return;
        }

        $pdfGenerator = new CertificatePdfGenerator();
        $this->issuedCount = 0;
        $this->skippedCount = 0;

        foreach ($this->selectedStudentIds as $studentId) {
            $student = \App\Models\Student::find($studentId);

            if (! $student) {
                continue;
            }

            // Get the enrollment for this student
            $enrollment = null;
            if ($this->issueType === 'class' && $this->classId) {
                $enrollment = Enrollment::where('class_id', $this->classId)
                    ->where('student_id', $studentId)
                    ->first();
            } elseif ($this->issueType === 'course' && $this->courseId) {
                $enrollment = Enrollment::whereHas('class', fn ($q) => $q->where('course_id', $this->courseId))
                    ->where('student_id', $studentId)
                    ->first();
            }

            // Check if already issued
            $existingIssue = CertificateIssue::where('certificate_id', $certificate->id)
                ->where('student_id', $student->id)
                ->when($enrollment, fn ($q) => $q->where('enrollment_id', $enrollment->id))
                ->where('status', 'issued')
                ->first();

            if ($existingIssue && $this->skipExisting) {
                $this->skippedCount++;

                continue;
            }

            // Generate certificate PDF
            $filePath = $pdfGenerator->generate($certificate, $student, $enrollment);

            // Create certificate issue record
            $issue = CertificateIssue::create([
                'certificate_id' => $certificate->id,
                'student_id' => $student->id,
                'enrollment_id' => $enrollment?->id,
                'certificate_number' => CertificateIssue::generateCertificateNumber(),
                'issued_by' => auth()->id(),
                'issued_at' => now(),
                'file_path' => $filePath,
                'data_snapshot' => $pdfGenerator->prepareData($certificate, $student, $enrollment),
                'status' => 'issued',
                'notes' => $this->notes,
            ]);

            // Log the issuance
            $issue->logAction('issued', auth()->user());

            $this->issuedCount++;
        }

        $message = "Successfully issued {$this->issuedCount} certificate(s).";
        if ($this->skippedCount > 0) {
            $message .= " Skipped {$this->skippedCount} student(s) with existing certificates.";
        }

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $message,
        ]);

        $this->redirect(route('certificates.issued'));
    }
} ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Bulk Issue Certificates</flux:heading>
            <flux:text class="mt-2">Issue certificates to multiple students at once</flux:text>
        </div>
        <div class="flex items-center gap-3">
            <flux:button variant="outline" href="{{ route('certificates.issue') }}" icon="document-plus">
                Single Issue
            </flux:button>
            <flux:button variant="outline" href="{{ route('certificates.index') }}" icon="arrow-left">
                Back to List
            </flux:button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Bulk Issue Form -->
        <div class="lg:col-span-2">
            <flux:card>
                <flux:heading size="lg" class="mb-6">Bulk Issue Configuration</flux:heading>

                <div class="space-y-6">
                    <!-- Certificate Selection -->
                    <div>
                        <flux:field>
                            <flux:label>Certificate Template *</flux:label>
                            <flux:select wire:model.live="certificateId">
                                <option value="">Select a certificate template...</option>
                                @foreach($certificates as $certificate)
                                    <option value="{{ $certificate->id }}">
                                        {{ $certificate->name }}
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

                    <!-- Issue Type -->
                    <div>
                        <flux:text variant="sm" class="font-medium mb-2">Issue To *</flux:text>
                        <flux:radio.group wire:model.live="issueType">
                            <flux:radio value="class" label="All students in a specific class" />
                            <flux:radio value="course" label="All students in a specific course" />
                        </flux:radio.group>
                    </div>

                    <!-- Class Selection -->
                    @if($issueType === 'class')
                        <div>
                            <flux:field>
                                <flux:label>Select Class *</flux:label>
                                <flux:select wire:model.live="classId">
                                    <option value="">Choose a class...</option>
                                    @foreach($classes as $class)
                                        <option value="{{ $class->id }}">
                                            {{ $class->title }} ({{ $class->course->name }})
                                        </option>
                                    @endforeach
                                </flux:select>
                            </flux:field>
                        </div>
                    @endif

                    <!-- Course Selection -->
                    @if($issueType === 'course')
                        <div>
                            <flux:field>
                                <flux:label>Select Course *</flux:label>
                                <flux:select wire:model.live="courseId">
                                    <option value="">Choose a course...</option>
                                    @foreach($courses as $course)
                                        <option value="{{ $course->id }}">
                                            {{ $course->name }}
                                        </option>
                                    @endforeach
                                </flux:select>
                            </flux:field>
                        </div>
                    @endif

                    <!-- Student Selection -->
                    @if($students->isNotEmpty())
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <flux:text variant="sm" class="font-medium">Select Students *</flux:text>
                                <flux:checkbox wire:model.live="selectAll" label="Select All ({{ $students->count() }})" />
                            </div>

                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg max-h-64 overflow-y-auto">
                                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($students as $student)
                                        <label class="flex items-center p-3 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                wire:model="selectedStudentIds"
                                                value="{{ $student->id }}"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            />
                                            <div class="ml-3">
                                                <flux:text>{{ $student->name }}</flux:text>
                                                <flux:text variant="sm" class="text-gray-500 dark:text-gray-400">
                                                    {{ $student->email }}
                                                </flux:text>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <flux:text variant="sm" class="text-gray-500 dark:text-gray-400 mt-2">
                                {{ count($selectedStudentIds) }} student(s) selected
                            </flux:text>

                            <flux:error name="selectedStudentIds" />
                        </div>
                    @endif

                    <!-- Options -->
                    <div>
                        <flux:checkbox wire:model="skipExisting" label="Skip students who already have this certificate" />
                    </div>

                    <!-- Notes -->
                    <div>
                        <flux:field>
                            <flux:label>Notes (Optional)</flux:label>
                            <flux:textarea
                                wire:model="notes"
                                rows="3"
                                placeholder="Add any notes about this bulk certificate issuance..."
                            />
                        </flux:field>
                    </div>

                    <!-- Submit -->
                    <div class="flex items-center gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <flux:button
                            variant="primary"
                            wire:click="bulkIssueCertificates"
                            :disabled="count($selectedStudentIds) === 0"
                        >
                            Issue {{ count($selectedStudentIds) }} Certificate(s)
                        </flux:button>
                    </div>
                </div>
            </flux:card>
        </div>

        <!-- Info & Help -->
        <div class="space-y-6">
            <!-- Selected Info -->
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

            <!-- Statistics -->
            @if($students->isNotEmpty())
                <flux:card>
                    <flux:heading size="lg" class="mb-4">Bulk Issue Summary</flux:heading>

                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <flux:text variant="sm" class="text-gray-500 dark:text-gray-400">Total Students</flux:text>
                            <flux:badge variant="neutral">{{ $students->count() }}</flux:badge>
                        </div>

                        <div class="flex justify-between items-center">
                            <flux:text variant="sm" class="text-gray-500 dark:text-gray-400">Selected Students</flux:text>
                            <flux:badge variant="primary">{{ count($selectedStudentIds) }}</flux:badge>
                        </div>

                        @if($skipExisting && $certificateId)
                            @php
                                $existingCount = \App\Models\CertificateIssue::where('certificate_id', $certificateId)
                                    ->whereIn('student_id', $selectedStudentIds)
                                    ->where('status', 'issued')
                                    ->count();
                            @endphp

                            @if($existingCount > 0)
                                <div class="flex justify-between items-center">
                                    <flux:text variant="sm" class="text-gray-500 dark:text-gray-400">Will Skip (Already Issued)</flux:text>
                                    <flux:badge variant="warning">{{ $existingCount }}</flux:badge>
                                </div>

                                <div class="flex justify-between items-center">
                                    <flux:text variant="sm" class="text-gray-500 dark:text-gray-400">Will Issue</flux:text>
                                    <flux:badge variant="success">{{ count($selectedStudentIds) - $existingCount }}</flux:badge>
                                </div>
                            @endif
                        @endif
                    </div>
                </flux:card>
            @endif

            <!-- Help Card -->
            <flux:card>
                <flux:heading size="lg" class="mb-4">
                    <flux:icon name="information-circle" class="w-5 h-5 inline-block mr-1" />
                    Bulk Issue Process
                </flux:heading>

                <div class="space-y-3">
                    <div>
                        <flux:text variant="sm" class="font-medium">1. Select Certificate</flux:text>
                        <flux:text variant="sm" class="text-gray-500 dark:text-gray-400">
                            Choose the certificate template to issue
                        </flux:text>
                    </div>

                    <div>
                        <flux:text variant="sm" class="font-medium">2. Choose Target</flux:text>
                        <flux:text variant="sm" class="text-gray-500 dark:text-gray-400">
                            Select a class or course to issue certificates to
                        </flux:text>
                    </div>

                    <div>
                        <flux:text variant="sm" class="font-medium">3. Select Students</flux:text>
                        <flux:text variant="sm" class="text-gray-500 dark:text-gray-400">
                            Choose which students will receive the certificate
                        </flux:text>
                    </div>

                    <div>
                        <flux:text variant="sm" class="font-medium">4. Issue Certificates</flux:text>
                        <flux:text variant="sm" class="text-gray-500 dark:text-gray-400">
                            PDFs will be generated automatically for all selected students
                        </flux:text>
                    </div>
                </div>

                <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                    <flux:text variant="sm" class="text-blue-700 dark:text-blue-300">
                        <flux:icon name="light-bulb" class="w-4 h-4 inline-block mr-1" />
                        Tip: Enable "Skip existing" to avoid duplicate certificates
                    </flux:text>
                </div>
            </flux:card>
        </div>
    </div>
</div>
