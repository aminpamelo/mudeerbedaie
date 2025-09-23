<?php

use App\Models\Student;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
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
    public $payment_method_type = 'automatic';

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
            'payment_method_type' => 'required|in:automatic,manual',
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
            'payment_method_type' => $this->payment_method_type,
            'manual_payment_required' => $this->payment_method_type === 'manual',
        ]);

        // Handle recurring billing based on payment method type
        try {
            if ($course->feeSettings && 
                $course->feeSettings->billing_cycle !== 'one_time' && 
                $course->feeSettings->stripe_price_id) {
                
                if ($this->payment_method_type === 'automatic') {
                    session()->flash('info', 'Enrollment created. Student will need to set up payment method for recurring billing.');
                } else {
                    // For manual payments, create first payment order
                    $this->createManualPaymentOrder($enrollment, $course);
                    session()->flash('info', 'Enrollment created with manual payment. First payment order has been generated.');
                }
            }
        } catch (\Exception $e) {
            // Log but don't fail enrollment creation
            \Log::warning('Failed to setup payment for enrollment', [
                'enrollment_id' => $enrollment->id,
                'payment_method_type' => $this->payment_method_type,
                'error' => $e->getMessage()
            ]);
        }

        $successMessage = $this->payment_method_type === 'manual' 
            ? 'Student enrolled successfully! Manual payment is required to activate the enrollment.'
            : 'Student enrolled successfully!';
        
        session()->flash('success', $successMessage);
        
        $this->redirect(route('enrollments.show', $enrollment));
    }

    public function getCourseInfo()
    {
        if (!$this->course_id) {
            return null;
        }

        return Course::with('feeSettings')->find($this->course_id);
    }

    private function createManualPaymentOrder(Enrollment $enrollment, Course $course): void
    {
        // Create the first payment order for manual payment
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
                'description' =>"Manual payment for {$course->name} enrollment",
            ],
        ]);

        // Create order item for the course fee
        $order->items()->create([
            'description' =>"Course Fee - {$course->name}",
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

        <!-- Payment Method Selection -->
        <flux:card>
            <flux:heading size="lg">Payment Method</flux:heading>
            
            <div class="mt-6 space-y-6">
                <flux:radio.group wire:model.live="payment_method_type" label="How will the student pay?" required>
                    <flux:radio value="automatic" label="Automatic (Card Payment)" 
                                description="Student will set up a credit/debit card for automatic recurring payments" />
                    <flux:radio value="manual" label="Manual Payment" 
                                description="Student will pay manually via bank transfer, cash, or other methods" />
                </flux:radio.group>

                @if($payment_method_type === 'manual')
                    <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg">
                        <div class="flex items-start">
                            <flux:icon icon="information-circle" class="w-5 h-5 text-amber-600 mr-3 mt-0.5" />
                            <div>
                                <flux:text class="text-amber-800 font-medium">Manual Payment Selected</flux:text>
                                <ul class="mt-2 text-sm text-amber-700 space-y-1">
                                    <li>• A payment order will be generated for the student</li>
                                    <li>• Student will receive payment instructions</li>
                                    <li>• Admin must approve payment before enrollment is activated</li>
                                    <li>• Future payments can be manual or switched to automatic</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                @elseif($payment_method_type === 'automatic')
                    <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <div class="flex items-start">
                            <flux:icon icon="credit-card" class="w-5 h-5 text-blue-600 mr-3 mt-0.5" />
                            <div>
                                <flux:text class="text-blue-800 font-medium">Automatic Payment Selected</flux:text>
                                <flux:text class="mt-2 text-sm text-blue-700">
                                    Student will need to set up a payment method after enrollment is created for automatic recurring billing.
                                </flux:text>
                            </div>
                        </div>
                    </div>
                @endif
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