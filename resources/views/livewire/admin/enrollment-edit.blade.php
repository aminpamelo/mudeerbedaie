<?php

use App\Models\Enrollment;
use Livewire\Volt\Component;

new class extends Component {
    public Enrollment $enrollment;
    
    public $status = '';
    public $enrollment_date = '';
    public $start_date = '';
    public $end_date = '';
    public $completion_date = '';
    public $enrollment_fee = '';
    public $notes = '';

    public function mount(): void
    {
        $this->enrollment->load(['student.user', 'course', 'enrolledBy']);
        
        $this->status = $this->enrollment->status;
        $this->enrollment_date = $this->enrollment->enrollment_date->format('Y-m-d');
        $this->start_date = $this->enrollment->start_date?->format('Y-m-d') ?? '';
        $this->end_date = $this->enrollment->end_date?->format('Y-m-d') ?? '';
        $this->completion_date = $this->enrollment->completion_date?->format('Y-m-d') ?? '';
        $this->enrollment_fee = $this->enrollment->enrollment_fee ?? '';
        $this->notes = $this->enrollment->notes ?? '';
    }

    public function update(): void
    {
        $this->validate([
            'status' => 'required|in:enrolled,active,completed,dropped,suspended,pending',
            'enrollment_date' => 'required|date',
            'start_date' => 'nullable|date|after_or_equal:enrollment_date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'completion_date' => 'nullable|date|after_or_equal:start_date',
            'enrollment_fee' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Auto-set completion date if status is changed to completed
        $completionDate = null;
        if ($this->status === 'completed' && !$this->completion_date) {
            $completionDate = today();
        } elseif ($this->completion_date) {
            $completionDate = $this->completion_date;
        }

        $this->enrollment->update([
            'status' => $this->status,
            'enrollment_date' => $this->enrollment_date,
            'start_date' => $this->start_date ?: null,
            'end_date' => $this->end_date ?: null,
            'completion_date' => $completionDate,
            'enrollment_fee' => $this->enrollment_fee ?: null,
            'notes' => $this->notes ?: null,
        ]);

        session()->flash('success', 'Enrollment updated successfully!');
        
        $this->redirect(route('enrollments.show', $this->enrollment));
    }

    public function markAsCompleted(): void
    {
        $this->status = 'completed';
        $this->completion_date = today()->format('Y-m-d');
    }

    public function markAsDropped(): void
    {
        $this->status = 'dropped';
    }
}; ?>

<div>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Edit Enrollment</flux:heading>
            <flux:text class="mt-2">{{ $enrollment->student->user->name }} - {{ $enrollment->course->name }}</flux:text>
        </div>
    </div>

    <div class="mt-6 space-y-8">
        <!-- Student and Course Information (Read-only) -->
        <flux:card>
            <flux:heading size="lg">Student and Course Information</flux:heading>
            
            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="p-4 bg-gray-50 rounded-lg">
                    <h4 class="text-sm font-medium text-gray-900">Student</h4>
                    <div class="mt-2">
                        <p class="text-sm text-gray-600">{{ $enrollment->student->user->name }}</p>
                        <p class="text-sm text-gray-500">{{ $enrollment->student->user->email }}</p>
                        <p class="text-sm text-gray-500">ID: {{ $enrollment->student->student_id }}</p>
                    </div>
                    <div class="mt-3">
                        <flux:button size="sm" variant="ghost" href="{{ route('students.show', $enrollment->student) }}">
                            View Student Profile
                        </flux:button>
                    </div>
                </div>

                <div class="p-4 bg-gray-50 rounded-lg">
                    <h4 class="text-sm font-medium text-gray-900">Course</h4>
                    <div class="mt-2">
                        <p class="text-sm text-gray-600">{{ $enrollment->course->name }}</p>
                        <p class="text-sm text-gray-500">{{ $enrollment->course->description ?: 'No description' }}</p>
                    </div>
                    <div class="mt-3">
                        <flux:button size="sm" variant="ghost" href="{{ route('courses.show', $enrollment->course) }}">
                            View Course Details
                        </flux:button>
                    </div>
                </div>
            </div>

            <div class="mt-6 p-4 bg-blue-50 rounded-lg">
                <p class="text-sm text-blue-700">
                    <strong>Note:</strong> To change the student or course, you would need to create a new enrollment and update the status of this one accordingly.
                </p>
            </div>
        </flux:card>

        <!-- Enrollment Status and Dates -->
        <flux:card>
            <flux:heading size="lg">Enrollment Details</flux:heading>
            
            <div class="mt-6 space-y-6">
                <div class="flex space-x-4">
                    <flux:select wire:model.live="status" label="Status" required class="flex-1">
                        <flux:select.option value="enrolled">Enrolled</flux:select.option>
                        <flux:select.option value="active">Active</flux:select.option>
                        <flux:select.option value="completed">Completed</flux:select.option>
                        <flux:select.option value="dropped">Dropped</flux:select.option>
                        <flux:select.option value="suspended">Suspended</flux:select.option>
                        <flux:select.option value="pending">Pending</flux:select.option>
                    </flux:select>
                    
                    <div class="flex space-x-2 pt-6">
                        <flux:button size="sm" variant="outline" wire:click="markAsCompleted">
                            Mark Completed
                        </flux:button>
                        <flux:button size="sm" variant="outline" wire:click="markAsDropped">
                            Mark Dropped
                        </flux:button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <flux:input type="date" wire:model="enrollment_date" label="Enrollment Date" required />
                    <flux:input type="date" wire:model="start_date" label="Start Date" />
                    <flux:input type="date" wire:model="end_date" label="End Date" />
                    @if($status === 'completed' || $completion_date)
                        <flux:input type="date" wire:model="completion_date" label="Completion Date" />
                    @endif
                </div>

                <flux:input 
                    type="number" 
                    step="0.01" 
                    wire:model="enrollment_fee" 
                    label="Enrollment Fee (MYR)" 
                    placeholder="0.00" />

                <flux:textarea wire:model="notes" label="Notes" placeholder="Any additional notes about this enrollment..." rows="3" />
            </div>
        </flux:card>

        <!-- Current Status Summary -->
        <flux:card>
            <flux:heading size="lg">Current Status</flux:heading>
            
            <div class="mt-6">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:badge size="lg" :class="match($status) {
                            'enrolled' => 'badge-blue',
                            'active' => 'badge-green',
                            'completed' => 'badge-emerald',
                            'dropped' => 'badge-red',
                            'suspended' => 'badge-yellow',
                            'pending' => 'badge-gray',
                            default => 'badge-gray'
                        }">
                            {{ ucfirst($status) }}
                        </flux:badge>
                    </div>
                    
                    <div class="text-right">
                        <p class="text-sm text-gray-500">Last updated: {{ $enrollment->updated_at->format('M d, Y \a\t g:i A') }}</p>
                        <p class="text-sm text-gray-500">Enrolled by: {{ $enrollment->enrolledBy->name }}</p>
                    </div>
                </div>

                @if($status === 'completed' && ($completion_date || $start_date))
                    <div class="mt-4 p-3 bg-emerald-50 rounded-lg">
                        <p class="text-sm text-emerald-700">
                            <strong>Congratulations!</strong> This student has completed the course.
                            @if($completion_date && $start_date)
                                Duration: {{ \Carbon\Carbon::parse($start_date)->diffInDays(\Carbon\Carbon::parse($completion_date)) }} days
                            @endif
                        </p>
                    </div>
                @elseif($status === 'dropped')
                    <div class="mt-4 p-3 bg-red-50 rounded-lg">
                        <p class="text-sm text-red-700">
                            <strong>Note:</strong> This student has dropped out of the course.
                        </p>
                    </div>
                @elseif($status === 'suspended')
                    <div class="mt-4 p-3 bg-yellow-50 rounded-lg">
                        <p class="text-sm text-yellow-700">
                            <strong>Warning:</strong> This enrollment is currently suspended.
                        </p>
                    </div>
                @endif
            </div>
        </flux:card>

        <div class="flex justify-between">
            <flux:button variant="ghost" href="{{ route('enrollments.show', $enrollment) }}">Cancel</flux:button>
            <flux:button wire:click="update" variant="primary">Update Enrollment</flux:button>
        </div>
    </div>
</div>