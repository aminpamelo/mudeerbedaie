<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\ClassSession;
use App\Models\ClassAttendance;

new #[Layout('components.layouts.teacher')] class extends Component {
    public ClassSession $session;
    public string $sessionNotes = '';
    public bool $showAttendanceModal = false;
    public ?int $selectedStudentId = null;
    public string $attendanceStatus = 'present';
    public bool $showStartConfirmation = false;
    
    public function mount(ClassSession $session)
    {
        $this->session = $session->load(['class.course', 'class.teacher', 'attendances.student.user']);
        $this->sessionNotes = $this->session->teacher_notes ?? '';
    }
    
    public function updateNotes()
    {
        $this->session->update([
            'teacher_notes' => $this->sessionNotes
        ]);

        session()->flash('success', 'Session notes updated successfully.');
    }

    public function closeStartConfirmation()
    {
        $this->showStartConfirmation = false;
    }

    public function requestStartSession()
    {
        $this->showStartConfirmation = true;
    }

    public function confirmStartSession()
    {
        $this->startSession();
        $this->closeStartConfirmation();
    }

    public function startSession()
    {
        if ($this->session->isScheduled()) {
            $this->session->markAsOngoing();
            $this->session->refresh();
            session()->flash('success', 'Session started successfully.');
        }
    }
    
    public function completeSession()
    {
        if ($this->session->isOngoing()) {
            $this->session->markCompleted($this->sessionNotes);
            $this->session->refresh();
            session()->flash('success', 'Session completed successfully.');
        }
    }
    
    public function markAsNoShow()
    {
        if ($this->session->isScheduled() || $this->session->isOngoing()) {
            $this->session->markAsNoShow($this->sessionNotes);
            $this->session->refresh();
            session()->flash('success', 'Session marked as no-show.');
        }
    }
    
    public function cancelSession()
    {
        if ($this->session->isScheduled()) {
            $this->session->cancel();
            $this->session->refresh();
            session()->flash('success', 'Session cancelled.');
        }
    }
    
    public function showAttendanceModal($studentId)
    {
        $this->selectedStudentId = $studentId;
        $attendance = $this->session->attendances->firstWhere('student_id', $studentId);
        $this->attendanceStatus = $attendance ? $attendance->status : 'present';
        $this->showAttendanceModal = true;
    }
    
    public function updateAttendance()
    {
        if ($this->selectedStudentId) {
            $this->session->updateStudentAttendance($this->selectedStudentId, $this->attendanceStatus);
            $this->session->refresh();
            $this->showAttendanceModal = false;
            session()->flash('success', 'Attendance updated successfully.');
        }
    }
    
    public function getStatusBadgeColor()
    {
        return match ($this->session->status) {
            'scheduled' => 'blue',
            'ongoing' => 'yellow',
            'completed' => 'green',
            'cancelled' => 'red',
            'no_show' => 'orange',
            'rescheduled' => 'purple',
            default => 'gray'
        };
    }
}; ?>

<div>
    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex items-center gap-2 text-sm text-gray-500  mb-2">
            <flux:link href="{{ route('teacher.sessions.index') }}" class="hover:text-gray-700 :text-gray-300">Sessions</flux:link>
            <flux:icon name="chevron-right" class="w-4 h-4" />
            <span>{{ $session->class->title }} - {{ $session->session_date->format('M d, Y') }}</span>
        </div>
        
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">Session Details</flux:heading>
                <flux:text class="mt-2">{{ $session->class->title }} - {{ $session->session_date->format('M d, Y') }} at {{ $session->session_time->format('g:i A') }}</flux:text>
            </div>
            <flux:badge color="{{ $this->getStatusBadgeColor() }}" size="lg">
                {{ ucfirst($session->status) }}
            </flux:badge>
        </div>
    </div>

    @if(session('success'))
        <flux:card class="p-4 mb-6 bg-green-50 /20 border-green-200">
            <flux:text class="text-green-800">{{ session('success') }}</flux:text>
        </flux:card>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Session Overview -->
            <flux:card class="p-6">
                <flux:heading size="lg" class="mb-4">Session Overview</flux:heading>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <flux:text class="text-sm text-gray-500  mb-1">Class</flux:text>
                        <flux:text size="lg">{{ $session->class->title }}</flux:text>
                    </div>
                    
                    <div>
                        <flux:text class="text-sm text-gray-500  mb-1">Course</flux:text>
                        <flux:text size="lg">{{ $session->class->course->name }}</flux:text>
                    </div>
                    
                    <div>
                        <flux:text class="text-sm text-gray-500  mb-1">Date & Time</flux:text>
                        <flux:text size="lg">{{ $session->formatted_date_time }}</flux:text>
                    </div>
                    
                    <div>
                        <flux:text class="text-sm text-gray-500  mb-1">Duration</flux:text>
                        <flux:text size="lg">{{ $session->formatted_duration }}</flux:text>
                    </div>
                    
                    @if($session->isOngoing())
                        <div>
                            <flux:text class="text-sm text-gray-500  mb-1">Elapsed Time</flux:text>
                            <flux:text size="lg" class="text-blue-600">{{ $session->formatted_elapsed_time }}</flux:text>
                        </div>
                    @endif
                    
                    @if($session->completed_at)
                        <div>
                            <flux:text class="text-sm text-gray-500  mb-1">Completed At</flux:text>
                            <flux:text size="lg">{{ $session->completed_at->format('M d, Y g:i A') }}</flux:text>
                        </div>
                    @endif
                    
                    @if($session->allowance_amount)
                        <div>
                            <flux:text class="text-sm text-gray-500  mb-1">Teacher Allowance</flux:text>
                            <flux:text size="lg" class="text-green-600  font-semibold">
                                RM{{ number_format($session->allowance_amount, 2) }}
                            </flux:text>
                        </div>
                    @endif
                </div>
            </flux:card>

            <!-- Session Notes -->
            <flux:card class="p-6">
                <flux:heading size="lg" class="mb-4">Session Notes</flux:heading>
                
                <div class="space-y-4">
                    <flux:textarea 
                        wire:model="sessionNotes" 
                        placeholder="Add notes about this session..."
                        rows="4"
                        class="w-full"
                    />
                    
                    <div class="flex justify-end">
                        <flux:button wire:click="updateNotes" variant="primary">
                            <flux:icon name="document-text" class="w-4 h-4 mr-1" />
                            Update Notes
                        </flux:button>
                    </div>
                </div>
            </flux:card>

            <!-- Student Attendance -->
            <flux:card class="p-6">
                <flux:heading size="lg" class="mb-4">Student Attendance</flux:heading>
                
                @if($session->attendances->count() > 0)
                    <div class="space-y-3">
                        @foreach($session->attendances as $attendance)
                            <div class="flex items-center justify-between p-4 bg-gray-50 /50 rounded-lg">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-gray-300  rounded-full flex items-center justify-center">
                                        <flux:icon name="user" class="w-5 h-5 text-gray-600" />
                                    </div>
                                    <div>
                                        <flux:text size="sm" class="font-medium">{{ $attendance->student->user->name }}</flux:text>
                                        <flux:text size="xs" class="text-gray-500">{{ $attendance->student->user->email }}</flux:text>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-3">
                                    @if($attendance->status === 'present')
                                        <flux:badge color="green" size="sm">Present</flux:badge>
                                    @elseif($attendance->status === 'late')
                                        <flux:badge color="yellow" size="sm">Late</flux:badge>
                                    @elseif($attendance->status === 'absent')
                                        <flux:badge color="red" size="sm">Absent</flux:badge>
                                    @else
                                        <flux:badge color="gray" size="sm">{{ ucfirst($attendance->status) }}</flux:badge>
                                    @endif
                                    
                                    @if($session->status === 'ongoing' || $session->status === 'scheduled')
                                        <flux:button 
                                            size="sm" 
                                            variant="ghost"
                                            wire:click="showAttendanceModal({{ $attendance->student_id }})"
                                        >
                                            <flux:icon name="pencil" class="w-4 h-4" />
                                        </flux:button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <flux:text class="text-gray-500">No attendance records available.</flux:text>
                @endif
            </flux:card>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Quick Actions -->
            <flux:card class="p-6">
                <flux:heading size="lg" class="mb-4">Quick Actions</flux:heading>
                
                <div class="space-y-3">
                    @if($session->status === 'scheduled')
                        <flux:button wire:click="requestStartSession" variant="primary" class="w-full">
                            <flux:icon name="play" class="w-4 h-4 mr-2" />
                            Start Session
                        </flux:button>
                        
                        <flux:button wire:click="markAsNoShow" variant="ghost" class="w-full">
                            <flux:icon name="user-minus" class="w-4 h-4 mr-2" />
                            Mark as No-Show
                        </flux:button>
                        
                        <flux:button wire:click="cancelSession" variant="ghost" class="w-full">
                            <flux:icon name="x-circle" class="w-4 h-4 mr-2" />
                            Cancel Session
                        </flux:button>
                    @endif
                    
                    @if($session->status === 'ongoing')
                        <flux:button wire:click="completeSession" variant="primary" class="w-full">
                            <flux:icon name="check-circle" class="w-4 h-4 mr-2" />
                            Complete Session
                        </flux:button>
                        
                        <flux:button wire:click="markAsNoShow" variant="ghost" class="w-full">
                            <flux:icon name="user-minus" class="w-4 h-4 mr-2" />
                            Mark as No-Show
                        </flux:button>
                    @endif
                    
                    <flux:button href="{{ route('teacher.classes.show', $session->class) }}" variant="ghost" class="w-full">
                        <flux:icon name="academic-cap" class="w-4 h-4 mr-2" />
                        View Class Details
                    </flux:button>
                </div>
            </flux:card>

            <!-- Session Timeline -->
            <flux:card class="p-6">
                <flux:heading size="lg" class="mb-4">Session Timeline</flux:heading>
                
                <div class="space-y-4">
                    <div class="flex items-start space-x-3">
                        <div class="w-2 h-2 bg-blue-500 rounded-full mt-2"></div>
                        <div>
                            <flux:text size="sm" class="font-medium">Session Scheduled</flux:text>
                            <flux:text size="xs" class="text-gray-500">{{ $session->created_at->format('M d, Y g:i A') }}</flux:text>
                        </div>
                    </div>
                    
                    @if($session->started_at)
                        <div class="flex items-start space-x-3">
                            <div class="w-2 h-2 bg-yellow-500 rounded-full mt-2"></div>
                            <div>
                                <flux:text size="sm" class="font-medium">Session Started</flux:text>
                                <flux:text size="xs" class="text-gray-500">{{ $session->started_at->format('M d, Y g:i A') }}</flux:text>
                            </div>
                        </div>
                    @endif
                    
                    @if($session->completed_at)
                        <div class="flex items-start space-x-3">
                            <div class="w-2 h-2 bg-green-500 rounded-full mt-2"></div>
                            <div>
                                <flux:text size="sm" class="font-medium">Session Completed</flux:text>
                                <flux:text size="xs" class="text-gray-500">{{ $session->completed_at->format('M d, Y g:i A') }}</flux:text>
                            </div>
                        </div>
                    @endif
                </div>
            </flux:card>
        </div>
    </div>

    <!-- Attendance Modal -->
    @if($showAttendanceModal)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <flux:card class="p-6 max-w-md w-full mx-4">
                <flux:heading size="lg" class="mb-4">Update Attendance</flux:heading>
                
                <div class="space-y-4">
                    <div>
                        <flux:text class="text-sm text-gray-500  mb-2">Attendance Status</flux:text>
                        <flux:select wire:model="attendanceStatus" class="w-full">
                            <option value="present">Present</option>
                            <option value="late">Late</option>
                            <option value="absent">Absent</option>
                        </flux:select>
                    </div>
                    
                    <div class="flex justify-end gap-3">
                        <flux:button wire:click="$set('showAttendanceModal', false)" variant="ghost">
                            Cancel
                        </flux:button>
                        <flux:button wire:click="updateAttendance" variant="primary">
                            Update Attendance
                        </flux:button>
                    </div>
                </div>
            </flux:card>
        </div>
    @endif

    <!-- Start Session Confirmation Modal -->
    <flux:modal wire:model="showStartConfirmation" class="max-w-md">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="flex-shrink-0 w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                    <flux:icon name="play" class="w-6 h-6 text-blue-600" />
                </div>
                <div>
                    <flux:heading size="lg">Start Session?</flux:heading>
                    <flux:text size="sm" class="text-gray-600">Are you ready to begin this session?</flux:text>
                </div>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <flux:text size="sm" class="text-blue-800">
                    Once you start the session, the timer will begin and you'll be able start your sessions.
                </flux:text>
            </div>

            <div class="flex gap-3 justify-end">
                <flux:button wire:click="closeStartConfirmation" variant="ghost">
                    Cancel
                </flux:button>
                <flux:button wire:click="confirmStartSession" variant="primary">
                    <div class="flex items-center justify-center gap-2">
                        <flux:icon name="play" class="w-4 h-4" />
                        Yes, Start Session
                    </div>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>