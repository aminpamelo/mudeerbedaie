<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Student;
use Livewire\Volt\Component;

new class extends Component
{
    // Step control
    public int $currentStep = 1;

    // Step 1: Student selection
    public array $selectedStudents = [];

    public string $studentSearch = '';

    // Step 2: Course & settings
    public string $course_id = '';

    public string $enrollment_fee = '';

    public string $payment_method_type = 'automatic';

    public string $enrollment_date = '';

    public string $start_date = '';

    public string $end_date = '';

    public string $status = 'enrolled';

    // Step 3: PIC & notes
    public string $enrolled_by = '';

    public string $notes = '';

    // Processing
    public array $validationErrors = [];

    public array $results = [];

    public function mount(): void
    {
        $this->enrollment_date = today()->format('Y-m-d');
        $this->enrolled_by = auth()->id();
    }

    public function with(): array
    {
        $studentsQuery = Student::where('status', 'active')->with('user');

        // Search by name, phone, or student_id
        if ($this->studentSearch && $this->currentStep === 1) {
            $search = $this->studentSearch;
            $studentsQuery->where(function ($query) use ($search) {
                $query->where('phone', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        return [
            'students' => $studentsQuery->get(),
            'courses' => Course::where('status', 'active')->get(),
            'pics' => \App\Models\User::whereIn('role', ['admin', 'staff'])
                ->orderBy('name')
                ->get(),
        ];
    }

    public function toggleStudent(int $studentId): void
    {
        if (in_array($studentId, $this->selectedStudents)) {
            $this->selectedStudents = array_values(array_diff($this->selectedStudents, [$studentId]));
        } else {
            $this->selectedStudents[] = $studentId;
        }
    }

    public function selectAllStudents(): void
    {
        $this->selectedStudents = Student::where('status', 'active')
            ->when($this->studentSearch, function ($query) {
                $search = $this->studentSearch;
                $query->where(function ($q) use ($search) {
                    $q->where('phone', 'like', "%{$search}%")
                        ->orWhere('student_id', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->pluck('id')
            ->toArray();
    }

    public function deselectAllStudents(): void
    {
        $this->selectedStudents = [];
    }

    public function goToStep(int $step): void
    {
        // Validate before moving forward
        if ($step > $this->currentStep) {
            if ($this->currentStep === 1 && count($this->selectedStudents) === 0) {
                $this->addError('selectedStudents', 'Please select at least one student.');

                return;
            }

            if ($this->currentStep === 2) {
                $this->validate([
                    'course_id' => 'required|exists:courses,id',
                    'enrollment_date' => 'required|date',
                    'start_date' => 'nullable|date|after_or_equal:enrollment_date',
                    'end_date' => 'nullable|date|after_or_equal:start_date',
                    'enrollment_fee' => 'nullable|numeric|min:0',
                    'status' => 'required|in:enrolled,active,pending',
                    'payment_method_type' => 'required|in:automatic,manual',
                ]);
            }

            if ($this->currentStep === 3) {
                $this->validate([
                    'enrolled_by' => 'required|exists:users,id',
                    'notes' => 'nullable|string|max:1000',
                ]);
            }
        }

        $this->currentStep = $step;
    }

    public function nextStep(): void
    {
        $this->goToStep($this->currentStep + 1);
    }

    public function previousStep(): void
    {
        $this->goToStep($this->currentStep - 1);
    }

    public function checkDuplicates(): array
    {
        if (! $this->course_id || count($this->selectedStudents) === 0) {
            return [];
        }

        return Enrollment::whereIn('student_id', $this->selectedStudents)
            ->where('course_id', $this->course_id)
            ->whereIn('status', ['enrolled', 'active', 'pending'])
            ->pluck('student_id')
            ->toArray();
    }

    public function getCourseInfo()
    {
        if (! $this->course_id) {
            return null;
        }

        return Course::with('feeSettings')->find($this->course_id);
    }

    public function getSelectedStudentsData()
    {
        return Student::with('user')
            ->whereIn('id', $this->selectedStudents)
            ->get();
    }

    public function createBulkEnrollments(): void
    {
        // Final validation
        $this->validate([
            'selectedStudents' => 'required|array|min:1',
            'course_id' => 'required|exists:courses,id',
            'enrolled_by' => 'required|exists:users,id',
            'status' => 'required|in:enrolled,active,pending',
            'enrollment_date' => 'required|date',
            'start_date' => 'nullable|date|after_or_equal:enrollment_date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'enrollment_fee' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'payment_method_type' => 'required|in:automatic,manual',
        ]);

        // Get course and fee
        $course = Course::with('feeSettings')->find($this->course_id);
        $enrollmentFee = $this->enrollment_fee ?: $course->feeSettings->fee_amount ?? 0;

        // Check duplicates
        $duplicates = $this->checkDuplicates();
        $studentsToEnroll = array_diff($this->selectedStudents, $duplicates);

        $this->results = [
            'success' => [],
            'skipped' => [],
            'errors' => [],
        ];

        // Process each student
        foreach ($studentsToEnroll as $studentId) {
            try {
                \DB::beginTransaction();

                $enrollment = Enrollment::create([
                    'student_id' => $studentId,
                    'course_id' => $this->course_id,
                    'enrolled_by' => $this->enrolled_by,
                    'status' => $this->status,
                    'enrollment_date' => $this->enrollment_date,
                    'start_date' => $this->start_date ?: null,
                    'end_date' => $this->end_date ?: null,
                    'enrollment_fee' => $enrollmentFee,
                    'notes' => $this->notes ?: null,
                    'payment_method_type' => $this->payment_method_type,
                    'manual_payment_required' => $this->payment_method_type === 'manual',
                ]);

                // Handle manual payment order creation
                if ($this->payment_method_type === 'manual' &&
                    $course->feeSettings &&
                    $course->feeSettings->billing_cycle !== 'one_time' &&
                    $course->feeSettings->stripe_price_id) {
                    $this->createManualPaymentOrder($enrollment, $course);
                }

                $this->results['success'][] = $studentId;

                \DB::commit();
            } catch (\Exception $e) {
                \DB::rollBack();
                $this->results['errors'][] = [
                    'student_id' => $studentId,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Add duplicates to skipped
        foreach ($duplicates as $duplicateId) {
            $this->results['skipped'][] = [
                'student_id' => $duplicateId,
                'reason' => 'Already enrolled in this course',
            ];
        }

        // Show results
        $successCount = count($this->results['success']);
        $skippedCount = count($this->results['skipped']);
        $errorCount = count($this->results['errors']);

        if ($successCount > 0) {
            session()->flash('success', "Successfully enrolled {$successCount} student(s)!");
        }

        if ($skippedCount > 0) {
            session()->flash('info', "{$skippedCount} student(s) skipped (already enrolled).");
        }

        if ($errorCount > 0) {
            session()->flash('error', "{$errorCount} student(s) failed to enroll. Check details below.");
        }

        // Move to results step
        $this->currentStep = 5;
    }

    private function createManualPaymentOrder(Enrollment $enrollment, Course $course): void
    {
        $order = Order::create([
            'enrollment_id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'course_id' => $enrollment->course_id,
            'amount' => $enrollment->enrollment_fee,
            'currency' => 'MYR',
            'status' => Order::STATUS_PENDING,
            'billing_reason' => Order::REASON_MANUAL,
            'payment_method' => Order::PAYMENT_METHOD_MANUAL,
            'period_start' => now(),
            'period_end' => $course->feeSettings->billing_cycle === 'monthly'
                ? now()->addMonth()
                : now()->addYear(),
            'metadata' => [
                'payment_method_type' => 'manual',
                'created_by' => auth()->id(),
                'description' => "Manual payment for {$course->name} enrollment",
            ],
        ]);

        $order->items()->create([
            'description' => "Course Fee - {$course->name}",
            'quantity' => 1,
            'unit_price' => $enrollment->enrollment_fee,
            'total_price' => $enrollment->enrollment_fee,
            'metadata' => [
                'course_id' => $course->id,
                'course_name' => $course->name,
                'billing_cycle' => $course->feeSettings->billing_cycle ?? 'monthly',
            ],
        ]);
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Bulk Enrollment</flux:heading>
            <flux:text class="mt-2">Enroll multiple students in a course</flux:text>
        </div>
        <flux:button variant="ghost" href="{{ route('enrollments.index') }}">
            <div class="flex items-center">
                <flux:icon name="x-mark" class="w-4 h-4 mr-1" />
                Cancel
            </div>
        </flux:button>
    </div>

    <!-- Progress Steps -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            @foreach([1 => 'Students', 2 => 'Course', 3 => 'Details', 4 => 'Review'] as $step => $label)
                <div class="flex items-center {{ $step < 4 ? 'flex-1' : '' }}">
                    <div class="flex items-center">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full {{ $currentStep >= $step ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-600' }}">
                            {{ $step }}
                        </div>
                        <div class="ml-3">
                            <div class="text-sm font-medium {{ $currentStep >= $step ? 'text-blue-600' : 'text-gray-500' }}">
                                Step {{ $step }}
                            </div>
                            <div class="text-xs text-gray-500">{{ $label }}</div>
                        </div>
                    </div>
                    @if($step < 4)
                        <div class="flex-1 h-0.5 mx-4 {{ $currentStep > $step ? 'bg-blue-600' : 'bg-gray-200' }}"></div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <!-- Step 1: Choose Students -->
    @if($currentStep === 1)
        <flux:card>
            <div class="mb-6">
                <flux:heading size="lg">Select Students</flux:heading>
                <flux:text class="mt-2">Choose students to enroll in the course</flux:text>
            </div>

            <div class="space-y-6">
                <div class="flex items-center justify-between gap-4">
                    <flux:input
                        wire:model.live.debounce.300ms="studentSearch"
                        placeholder="Search by name, phone, or student ID..."
                        class="flex-1"
                    />
                    <div class="flex gap-2">
                        <flux:button variant="ghost" size="sm" wire:click="selectAllStudents">
                            Select All
                        </flux:button>
                        <flux:button variant="ghost" size="sm" wire:click="deselectAllStudents">
                            Clear
                        </flux:button>
                    </div>
                </div>

                @if(count($selectedStudents) > 0)
                    <div class="p-3 bg-blue-50 rounded-lg">
                        <flux:text class="text-blue-800 font-medium">
                            {{ count($selectedStudents) }} student(s) selected
                        </flux:text>
                    </div>
                @endif

                @error('selectedStudents')
                    <div class="p-3 bg-red-50 rounded-lg">
                        <flux:text class="text-red-800">{{ $message }}</flux:text>
                    </div>
                @enderror

                <div class="border rounded-lg divide-y max-h-96 overflow-y-auto">
                    @forelse($students as $student)
                        <div
                            class="flex items-center p-4 hover:bg-gray-50 cursor-pointer transition-colors {{ in_array($student->id, $selectedStudents) ? 'bg-blue-50 border-l-4 border-blue-500' : '' }}"
                            wire:click="toggleStudent({{ $student->id }})"
                        >
                            <input
                                type="checkbox"
                                class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                @checked(in_array($student->id, $selectedStudents))
                                onclick="return false;"
                            />
                            <div class="ml-3 flex-1">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ $student->user?->name ?? 'N/A' }}
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{ $student->student_id }} • {{ $student->phone ?: $student->user?->phone ?? 'No phone' }}
                                </div>
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ $student->enrollments()->count() }} enrollments
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-gray-500">
                            No students found
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <flux:button variant="primary" wire:click="nextStep">
                    Continue to Course Selection
                </flux:button>
            </div>
        </flux:card>
    @endif

    <!-- Step 2: Course & Settings -->
    @if($currentStep === 2)
        <flux:card>
            <div class="mb-6">
                <flux:heading size="lg">Course & Enrollment Settings</flux:heading>
                <flux:text class="mt-2">Configure course and enrollment details</flux:text>
            </div>

            <div class="space-y-6">
                <flux:select wire:model.live="course_id" label="Course" placeholder="Select a course" required>
                    @foreach($courses as $course)
                        <flux:select.option value="{{ $course->id }}">
                            {{ $course->name }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                @if($this->getCourseInfo())
                    <div class="p-4 bg-blue-50 rounded-lg">
                        <h4 class="text-sm font-medium text-blue-900">Course Information</h4>
                        <div class="mt-2 text-sm text-blue-700">
                            <p><strong>Description:</strong> {{ $this->getCourseInfo()->description ?: 'No description available' }}</p>
                            @if($this->getCourseInfo()->feeSettings)
                                <p><strong>Course Fee:</strong> RM {{ number_format($this->getCourseInfo()->feeSettings->fee_amount, 2) }}</p>
                                <p><strong>Billing Cycle:</strong> {{ ucfirst($this->getCourseInfo()->feeSettings->billing_cycle) }}</p>
                            @endif
                        </div>
                    </div>
                @endif

                <flux:separator />

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <flux:select wire:model="status" label="Initial Status" required>
                        <flux:select.option value="enrolled">Enrolled</flux:select.option>
                        <flux:select.option value="active">Active</flux:select.option>
                        <flux:select.option value="pending">Pending</flux:select.option>
                    </flux:select>

                    <flux:input
                        type="number"
                        step="0.01"
                        wire:model="enrollment_fee"
                        label="Enrollment Fee (MYR)"
                        placeholder="Leave empty to use course default"
                    />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <flux:input type="date" wire:model="enrollment_date" label="Enrollment Date" required />
                    <flux:input type="date" wire:model="start_date" label="Start Date" />
                    <flux:input type="date" wire:model="end_date" label="End Date" />
                </div>

                <flux:separator />

                <flux:radio.group wire:model.live="payment_method_type" label="Payment Method" required>
                    <flux:radio value="automatic" label="Automatic (Card Payment)"
                                description="Students will set up credit/debit card for automatic recurring payments" />
                    <flux:radio value="manual" label="Manual Payment"
                                description="Students will pay manually via bank transfer, cash, or other methods" />
                </flux:radio.group>

                @if($payment_method_type === 'manual')
                    <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg">
                        <flux:text class="text-amber-800 font-medium">Manual Payment Selected</flux:text>
                        <flux:text class="mt-2 text-sm text-amber-700">
                            Payment orders will be generated for each student. Admins must approve payments before enrollments are activated.
                        </flux:text>
                    </div>
                @endif
            </div>

            <div class="mt-6 flex justify-between">
                <flux:button variant="ghost" wire:click="previousStep">
                    <div class="flex items-center">
                        <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                        Back
                    </div>
                </flux:button>
                <flux:button variant="primary" wire:click="nextStep">
                    Continue to Details
                </flux:button>
            </div>
        </flux:card>
    @endif

    <!-- Step 3: PIC & Notes -->
    @if($currentStep === 3)
        <flux:card>
            <div class="mb-6">
                <flux:heading size="lg">Person in Charge & Notes</flux:heading>
                <flux:text class="mt-2">Assign responsibility and add optional notes</flux:text>
            </div>

            <div class="space-y-6">
                <flux:select wire:model="enrolled_by" label="Person in Charge (PIC)" required>
                    @foreach($pics as $pic)
                        <flux:select.option value="{{ $pic->id }}">{{ $pic->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:textarea
                    wire:model="notes"
                    label="Notes (Optional)"
                    placeholder="Any additional notes about these enrollments..."
                    rows="4"
                />

                <div class="p-4 bg-gray-50 rounded-lg">
                    <flux:text class="text-gray-700 text-sm">
                        These notes will be applied to all {{ count($selectedStudents) }} enrollment(s).
                    </flux:text>
                </div>
            </div>

            <div class="mt-6 flex justify-between">
                <flux:button variant="ghost" wire:click="previousStep">
                    <div class="flex items-center">
                        <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                        Back
                    </div>
                </flux:button>
                <flux:button variant="primary" wire:click="nextStep">
                    Review Enrollments
                </flux:button>
            </div>
        </flux:card>
    @endif

    <!-- Step 4: Review & Confirm -->
    @if($currentStep === 4)
        <flux:card>
            <div class="mb-6">
                <flux:heading size="lg">Review & Confirm</flux:heading>
                <flux:text class="mt-2">Verify all details before creating enrollments</flux:text>
            </div>

            <div class="space-y-6">
                <!-- Summary Stats -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="p-4 bg-blue-50 rounded-lg">
                        <div class="text-2xl font-bold text-blue-900">{{ count($selectedStudents) }}</div>
                        <div class="text-sm text-blue-700">Total Students</div>
                    </div>
                    <div class="p-4 bg-green-50 rounded-lg">
                        <div class="text-2xl font-bold text-green-900">{{ count($selectedStudents) - count($this->checkDuplicates()) }}</div>
                        <div class="text-sm text-green-700">Will be Enrolled</div>
                    </div>
                    <div class="p-4 bg-yellow-50 rounded-lg">
                        <div class="text-2xl font-bold text-yellow-900">{{ count($this->checkDuplicates()) }}</div>
                        <div class="text-sm text-yellow-700">Will be Skipped</div>
                    </div>
                </div>

                <!-- Duplicate Warnings -->
                @if(count($this->checkDuplicates()) > 0)
                    <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <flux:heading size="sm" class="text-yellow-900">Duplicate Enrollments Detected</flux:heading>
                        <flux:text class="mt-2 text-sm text-yellow-700">
                            {{ count($this->checkDuplicates()) }} student(s) are already enrolled in this course and will be skipped.
                        </flux:text>
                    </div>
                @endif

                <!-- Course Details -->
                <div>
                    <flux:heading size="sm">Course Details</flux:heading>
                    <div class="mt-3 p-4 bg-gray-50 rounded-lg">
                        @php
                            $selectedCourse = $courses->find($course_id);
                        @endphp
                        @if($selectedCourse)
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-600">Course:</span>
                                    <span class="font-medium ml-2">{{ $selectedCourse->name }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Fee:</span>
                                    <span class="font-medium ml-2">RM {{ number_format($enrollment_fee ?: $selectedCourse->feeSettings->fee_amount ?? 0, 2) }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Status:</span>
                                    <span class="font-medium ml-2">{{ ucfirst($status) }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Payment Method:</span>
                                    <span class="font-medium ml-2">{{ ucfirst($payment_method_type) }}</span>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Student List -->
                <div>
                    <flux:heading size="sm">Students to Enroll</flux:heading>
                    <div class="mt-3 border rounded-lg divide-y max-h-64 overflow-y-auto">
                        @foreach($this->getSelectedStudentsData() as $student)
                            @php
                                $isDuplicate = in_array($student->id, $this->checkDuplicates());
                            @endphp
                            <div class="p-3 flex items-center justify-between {{ $isDuplicate ? 'bg-yellow-50' : '' }}">
                                <div class="flex-1">
                                    <div class="text-sm font-medium">{{ $student->user?->name ?? 'N/A' }}</div>
                                    <div class="text-xs text-gray-500">{{ $student->student_id }}</div>
                                </div>
                                @if($isDuplicate)
                                    <flux:badge color="yellow" size="sm">Will Skip</flux:badge>
                                @else
                                    <flux:badge color="green" size="sm">Will Enroll</flux:badge>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-between">
                <flux:button variant="ghost" wire:click="previousStep">
                    <div class="flex items-center">
                        <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                        Back
                    </div>
                </flux:button>
                <flux:button
                    variant="primary"
                    wire:click="createBulkEnrollments"
                    :disabled="count($selectedStudents) - count($this->checkDuplicates()) === 0"
                >
                    Create {{ count($selectedStudents) - count($this->checkDuplicates()) }} Enrollment(s)
                </flux:button>
            </div>
        </flux:card>
    @endif

    <!-- Step 5: Results -->
    @if($currentStep === 5)
        <flux:card>
            <div class="mb-6">
                <flux:heading size="lg">Enrollment Results</flux:heading>
                <flux:text class="mt-2">Summary of bulk enrollment process</flux:text>
            </div>

            <div class="space-y-6">
                <!-- Results Summary -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="p-4 bg-green-50 rounded-lg">
                        <div class="text-2xl font-bold text-green-900">{{ count($results['success'] ?? []) }}</div>
                        <div class="text-sm text-green-700">Successfully Enrolled</div>
                    </div>
                    <div class="p-4 bg-yellow-50 rounded-lg">
                        <div class="text-2xl font-bold text-yellow-900">{{ count($results['skipped'] ?? []) }}</div>
                        <div class="text-sm text-yellow-700">Skipped</div>
                    </div>
                    <div class="p-4 bg-red-50 rounded-lg">
                        <div class="text-2xl font-bold text-red-900">{{ count($results['errors'] ?? []) }}</div>
                        <div class="text-sm text-red-700">Failed</div>
                    </div>
                </div>

                <!-- Detailed Results -->
                @if(count($results['errors'] ?? []) > 0)
                    <div>
                        <flux:heading size="sm" class="text-red-900">Failed Enrollments</flux:heading>
                        <div class="mt-3 border border-red-200 rounded-lg divide-y max-h-64 overflow-y-auto">
                            @foreach($results['errors'] as $error)
                                @php
                                    $student = Student::with('user')->find($error['student_id']);
                                @endphp
                                <div class="p-3 bg-red-50">
                                    <div class="text-sm font-medium text-red-900">{{ $student->user?->name ?? 'N/A' }}</div>
                                    <div class="text-xs text-red-700">{{ $error['error'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(count($results['skipped'] ?? []) > 0)
                    <div>
                        <flux:heading size="sm" class="text-yellow-900">Skipped Enrollments</flux:heading>
                        <div class="mt-3 border border-yellow-200 rounded-lg divide-y max-h-64 overflow-y-auto">
                            @foreach($results['skipped'] as $skipped)
                                @php
                                    $student = Student::with('user')->find($skipped['student_id']);
                                @endphp
                                <div class="p-3 bg-yellow-50">
                                    <div class="text-sm font-medium text-yellow-900">{{ $student->user?->name ?? 'N/A' }}</div>
                                    <div class="text-xs text-yellow-700">{{ $skipped['reason'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="mt-6 flex justify-between">
                <flux:button variant="ghost" href="{{ route('enrollments.index') }}">
                    Back to Enrollments
                </flux:button>
                <flux:button variant="primary" href="{{ route('enrollments.bulk-create') }}">
                    Create Another Bulk Enrollment
                </flux:button>
            </div>
        </flux:card>
    @endif
</div>
