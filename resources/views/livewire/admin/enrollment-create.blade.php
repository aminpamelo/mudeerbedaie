<?php

use App\Models\Student;
use App\Models\Course;
use App\Models\Enrollment;
use App\Services\StripeService;
use Livewire\Volt\Component;

new class extends Component {
    public $student_id = '';
    public $course_id = '';
    public $status = 'enrolled';
    public $enrollment_date = '';
    public $start_date = '';
    public $end_date = '';
    public $enrollment_fee = '';
    public $notes = '';

    public function mount(): void
    {
        $this->enrollment_date = today()->format('Y-m-d');
    }

    public function with(): array
    {
        return [
            'students' => Student::where('status', 'active')->with('user')->get(),
            'courses' => Course::where('status', 'active')->get(),
        ];
    }

    public function create(): void
    {
        $this->validate([
            'student_id' => 'required|exists:students,id',
            'course_id' => 'required|exists:courses,id',
            'status' => 'required|in:enrolled,active,pending',
            'enrollment_date' => 'required|date',
            'start_date' => 'nullable|date|after_or_equal:enrollment_date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'enrollment_fee' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Check if student is already enrolled in this course with an active status
        $existingEnrollment = Enrollment::where('student_id', $this->student_id)
            ->where('course_id', $this->course_id)
            ->whereIn('status', ['enrolled', 'active', 'pending'])
            ->first();

        if ($existingEnrollment) {
            $this->addError('course_id', 'Student is already enrolled in this course.');
            return;
        }

        // Get course fee if enrollment fee not specified
        $course = Course::with('feeSettings')->find($this->course_id);
        $enrollmentFee = $this->enrollment_fee ?: $course->feeSettings->fee_amount ?? 0;

        $enrollment = Enrollment::create([
            'student_id' => $this->student_id,
            'course_id' => $this->course_id,
            'enrolled_by' => auth()->id(),
            'status' => $this->status,
            'enrollment_date' => $this->enrollment_date,
            'start_date' => $this->start_date ?: null,
            'end_date' => $this->end_date ?: null,
            'enrollment_fee' => $enrollmentFee,
            'notes' => $this->notes ?: null,
        ]);

        // Auto-create Stripe subscription if course has recurring billing
        try {
            if ($course->feeSettings && 
                $course->feeSettings->billing_cycle !== 'one_time' && 
                $course->feeSettings->stripe_price_id) {
                
                // Note: This creates a subscription that requires payment method setup
                // In a real implementation, you'd need to collect payment method first
                session()->flash('info', 'Enrollment created. Student will need to set up payment method for recurring billing.');
                
                // TODO: Implement payment method collection flow
                // This would typically redirect to a payment setup page
            }
        } catch (\Exception $e) {
            // Log but don't fail enrollment creation
            \Log::warning('Failed to create subscription for enrollment', [
                'enrollment_id' => $enrollment->id,
                'error' => $e->getMessage()
            ]);
        }

        session()->flash('success', 'Student enrolled successfully!');
        
        $this->redirect(route('enrollments.show', $enrollment));
    }

    public function getCourseInfo()
    {
        if (!$this->course_id) {
            return null;
        }

        return Course::with('feeSettings')->find($this->course_id);
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">New Enrollment</flux:heading>
            <flux:text class="mt-2">Enroll a student in a course</flux:text>
        </div>
    </div>

    <div class="mt-6 space-y-8">
        <!-- Student and Course Selection -->
        <flux:card>
            <flux:heading size="lg">Student and Course</flux:heading>
            
            <div class="mt-6 space-y-6">
                <flux:select wire:model.live="student_id" label="Student" placeholder="Select a student" required>
                    @foreach($students as $student)
                        <flux:select.option value="{{ $student->id }}">
                            {{ $student->user->name }} ({{ $student->student_id }})
                        </flux:select.option>
                    @endforeach
                </flux:select>

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
            </div>
        </flux:card>

        <!-- Enrollment Details -->
        <flux:card>
            <flux:heading size="lg">Enrollment Details</flux:heading>
            
            <div class="mt-6 space-y-6">
                <flux:select wire:model="status" label="Initial Status" required>
                    <flux:select.option value="enrolled">Enrolled</flux:select.option>
                    <flux:select.option value="active">Active</flux:select.option>
                    <flux:select.option value="pending">Pending</flux:select.option>
                </flux:select>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <flux:input type="date" wire:model="enrollment_date" label="Enrollment Date" required />
                    <flux:input type="date" wire:model="start_date" label="Start Date" />
                    <flux:input type="date" wire:model="end_date" label="End Date" />
                </div>

                <flux:input 
                    type="number" 
                    step="0.01" 
                    wire:model="enrollment_fee" 
                    label="Enrollment Fee (MYR)" 
                    placeholder="Leave empty to use course default fee" />

                @if($this->getCourseInfo() && $this->getCourseInfo()->feeSettings && !$enrollment_fee)
                    <p class="text-sm text-gray-500">
                        Default course fee: RM {{ number_format($this->getCourseInfo()->feeSettings->fee_amount, 2) }}
                    </p>
                @endif

                <flux:textarea wire:model="notes" label="Notes" placeholder="Any additional notes about this enrollment..." rows="3" />
            </div>
        </flux:card>

        @if($student_id && $course_id)
            <!-- Enrollment Summary -->
            <flux:card>
                <flux:heading size="lg">Enrollment Summary</flux:heading>
                
                <div class="mt-6">
                    @php
                        $selectedStudent = $students->find($student_id);
                        $selectedCourse = $courses->find($course_id);
                    @endphp
                    
                    @if($selectedStudent && $selectedCourse)
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h4 class="text-sm font-medium text-gray-900">Student</h4>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-600">{{ $selectedStudent->user->name }}</p>
                                    <p class="text-sm text-gray-500">{{ $selectedStudent->user->email }}</p>
                                    <p class="text-sm text-gray-500">ID: {{ $selectedStudent->student_id }}</p>
                                </div>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-900">Course</h4>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-600">{{ $selectedCourse->name }}</p>
                                    <p class="text-sm text-gray-500">{{ $selectedCourse->description ?: 'No description' }}</p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </flux:card>
        @endif

        <div class="flex justify-between">
            <flux:button variant="ghost" href="{{ route('enrollments.index') }}">Cancel</flux:button>
            <flux:button wire:click="create" variant="primary">Create Enrollment</flux:button>
        </div>
    </div>
</div>