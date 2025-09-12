<?php

use Livewire\Volt\Component;
use App\Models\ClassSession;

new class extends Component {
    public ClassSession $session;
    
    public function mount(ClassSession $session)
    {
        $this->session = $session->load(['class.course', 'class.teacher.user', 'attendances.student.user', 'verifier']);
    }
    
    public function verifySession()
    {
        try {
            $this->session->verify(auth()->user());
            $this->session->refresh();
            session()->flash('success', 'Session has been verified successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to verify session: ' . $e->getMessage());
        }
    }
    
    public function unverifySession()
    {
        try {
            $this->session->unverify();
            $this->session->refresh();
            session()->flash('success', 'Session verification has been removed.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to unverify session: ' . $e->getMessage());
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
        <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-2">
            <flux:link href="{{ route('admin.sessions.index') }}" class="hover:text-gray-700 dark:hover:text-gray-300">Sessions</flux:link>
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
        <flux:card class="p-4 mb-6 bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800">
            <flux:text class="text-green-800 dark:text-green-200">{{ session('success') }}</flux:text>
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
                        <flux:text class="text-sm text-gray-500 dark:text-gray-400 mb-1">Class</flux:text>
                        <flux:text size="lg">{{ $session->class->title }}</flux:text>
                    </div>
                    
                    <div>
                        <flux:text class="text-sm text-gray-500 dark:text-gray-400 mb-1">Course</flux:text>
                        <flux:text size="lg">{{ $session->class->course->name }}</flux:text>
                    </div>
                    
                    <div>
                        <flux:text class="text-sm text-gray-500 dark:text-gray-400 mb-1">Teacher</flux:text>
                        <flux:text size="lg">
                            @if($session->class->teacher)
                                {{ $session->class->teacher->user->name }}
                            @else
                                <span class="text-gray-400">No teacher assigned</span>
                            @endif
                        </flux:text>
                    </div>
                    
                    <div>
                        <flux:text class="text-sm text-gray-500 dark:text-gray-400 mb-1">Date & Time</flux:text>
                        <flux:text size="lg">{{ $session->formatted_date_time }}</flux:text>
                    </div>
                    
                    <div>
                        <flux:text class="text-sm text-gray-500 dark:text-gray-400 mb-1">Duration</flux:text>
                        <flux:text size="lg">{{ $session->formatted_duration }}</flux:text>
                    </div>
                    
                    @if($session->topic)
                        <div>
                            <flux:text class="text-sm text-gray-500 dark:text-gray-400 mb-1">Topic</flux:text>
                            <flux:text size="lg">{{ $session->topic }}</flux:text>
                        </div>
                    @endif
                    
                    @if($session->isOngoing())
                        <div>
                            <flux:text class="text-sm text-gray-500 dark:text-gray-400 mb-1">Elapsed Time</flux:text>
                            <flux:text size="lg" class="text-blue-600 dark:text-blue-400">{{ $session->formatted_elapsed_time }}</flux:text>
                        </div>
                    @endif
                    
                    @if($session->completed_at)
                        <div>
                            <flux:text class="text-sm text-gray-500 dark:text-gray-400 mb-1">Completed At</flux:text>
                            <flux:text size="lg">{{ $session->completed_at->format('M d, Y g:i A') }}</flux:text>
                        </div>
                    @endif
                    
                    @if($session->allowance_amount)
                        <div>
                            <flux:text class="text-sm text-gray-500 dark:text-gray-400 mb-1">Teacher Allowance</flux:text>
                            <flux:text size="lg" class="text-green-600 dark:text-green-400 font-semibold">
                                RM{{ number_format($session->allowance_amount, 2) }}
                            </flux:text>
                        </div>
                    @endif
                    
                    @if($session->verified_at)
                        <div>
                            <flux:text class="text-sm text-gray-500 dark:text-gray-400 mb-1">Verification Status</flux:text>
                            <div class="flex items-center gap-2">
                                <flux:badge color="green" size="sm">Verified</flux:badge>
                                <flux:text size="sm" class="text-gray-600 dark:text-gray-400">
                                    {{ $session->verified_at->format('M d, Y g:i A') }}
                                </flux:text>
                            </div>
                        </div>
                        
                        <div>
                            <flux:text class="text-sm text-gray-500 dark:text-gray-400 mb-1">Verified By</flux:text>
                            <flux:text size="lg">
                                {{ $session->verifier ? $session->verifier->name : 'Unknown User' }}
                                @if($session->verifier_role)
                                    <span class="text-sm text-gray-500 dark:text-gray-400">({{ ucfirst($session->verifier_role) }})</span>
                                @endif
                            </flux:text>
                        </div>
                    @elseif($session->status === 'completed' && $session->allowance_amount)
                        <div>
                            <flux:text class="text-sm text-gray-500 dark:text-gray-400 mb-1">Verification Status</flux:text>
                            <flux:badge color="amber" size="sm">Pending Verification</flux:badge>
                        </div>
                    @endif
                </div>
            </flux:card>

            <!-- Teacher Notes -->
            @if($session->teacher_notes)
                <flux:card class="p-6">
                    <flux:heading size="lg" class="mb-4">Teacher Notes</flux:heading>
                    <flux:text class="whitespace-pre-wrap">{{ $session->teacher_notes }}</flux:text>
                </flux:card>
            @endif

            <!-- Student Attendance -->
            <flux:card class="p-6">
                <flux:heading size="lg" class="mb-4">Student Attendance</flux:heading>
                
                @if($session->attendances->count() > 0)
                    <div class="space-y-3">
                        @foreach($session->attendances as $attendance)
                            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 bg-gray-300 dark:bg-gray-600 rounded-full flex items-center justify-center">
                                        <flux:icon name="user" class="w-5 h-5 text-gray-600 dark:text-gray-300" />
                                    </div>
                                    <div>
                                        <flux:text size="sm" class="font-medium">{{ $attendance->student->user->name }}</flux:text>
                                        <flux:text size="xs" class="text-gray-500 dark:text-gray-400">{{ $attendance->student->user->email }}</flux:text>
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
                                    
                                    @if($attendance->checked_in_at)
                                        <flux:text size="xs" class="text-gray-500 dark:text-gray-400">
                                            {{ $attendance->checked_in_at->format('g:i A') }}
                                        </flux:text>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    
                    <!-- Attendance Summary -->
                    <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        @php
                            $totalStudents = $session->attendances->count();
                            $presentCount = $session->attendances->whereIn('status', ['present', 'late'])->count();
                            $absentCount = $session->attendances->where('status', 'absent')->count();
                            $attendanceRate = $totalStudents > 0 ? round(($presentCount / $totalStudents) * 100) : 0;
                        @endphp
                        <flux:heading size="sm" class="mb-2">Attendance Summary</flux:heading>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                            <div>
                                <div class="text-lg font-semibold text-blue-600 dark:text-blue-400">{{ $totalStudents }}</div>
                                <div class="text-gray-600 dark:text-gray-400">Total Students</div>
                            </div>
                            <div>
                                <div class="text-lg font-semibold text-green-600 dark:text-green-400">{{ $presentCount }}</div>
                                <div class="text-gray-600 dark:text-gray-400">Present/Late</div>
                            </div>
                            <div>
                                <div class="text-lg font-semibold text-red-600 dark:text-red-400">{{ $absentCount }}</div>
                                <div class="text-gray-600 dark:text-gray-400">Absent</div>
                            </div>
                            <div>
                                <div class="text-lg font-semibold text-purple-600 dark:text-purple-400">{{ $attendanceRate }}%</div>
                                <div class="text-gray-600 dark:text-gray-400">Attendance Rate</div>
                            </div>
                        </div>
                    </div>
                @else
                    <flux:text class="text-gray-500 dark:text-gray-400">No attendance records available.</flux:text>
                @endif
            </flux:card>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Quick Actions -->
            <flux:card class="p-6">
                <flux:heading size="lg" class="mb-4">Quick Actions</flux:heading>
                
                <div class="space-y-3">
                    @if($session->status === 'completed' && $session->allowance_amount && !$session->verified_at)
                        <flux:button wire:click="verifySession" variant="primary" class="w-full">
                            <flux:icon name="check-badge" class="w-4 h-4 mr-2" />
                            Verify Session for Payroll
                        </flux:button>
                    @elseif($session->verified_at)
                        <flux:button wire:click="unverifySession" variant="ghost" class="w-full">
                            <flux:icon name="x-mark" class="w-4 h-4 mr-2" />
                            Remove Verification
                        </flux:button>
                    @endif
                    
                    <flux:button href="{{ route('classes.show', $session->class) }}" variant="ghost" class="w-full">
                        <flux:icon name="academic-cap" class="w-4 h-4 mr-2" />
                        View Class Details
                    </flux:button>
                    
                    @if($session->class->teacher)
                        <flux:button href="{{ route('teachers.show', $session->class->teacher) }}" variant="ghost" class="w-full">
                            <flux:icon name="user" class="w-4 h-4 mr-2" />
                            View Teacher Profile
                        </flux:button>
                    @endif
                    
                    <flux:button href="{{ route('admin.sessions.index') }}" variant="ghost" class="w-full">
                        <flux:icon name="arrow-left" class="w-4 h-4 mr-2" />
                        Back to Sessions List
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
                            <flux:text size="xs" class="text-gray-500 dark:text-gray-400">{{ $session->created_at->format('M d, Y g:i A') }}</flux:text>
                        </div>
                    </div>
                    
                    @if($session->started_at)
                        <div class="flex items-start space-x-3">
                            <div class="w-2 h-2 bg-yellow-500 rounded-full mt-2"></div>
                            <div>
                                <flux:text size="sm" class="font-medium">Session Started</flux:text>
                                <flux:text size="xs" class="text-gray-500 dark:text-gray-400">{{ $session->started_at->format('M d, Y g:i A') }}</flux:text>
                            </div>
                        </div>
                    @endif
                    
                    @if($session->completed_at)
                        <div class="flex items-start space-x-3">
                            <div class="w-2 h-2 bg-green-500 rounded-full mt-2"></div>
                            <div>
                                <flux:text size="sm" class="font-medium">Session Completed</flux:text>
                                <flux:text size="xs" class="text-gray-500 dark:text-gray-400">{{ $session->completed_at->format('M d, Y g:i A') }}</flux:text>
                            </div>
                        </div>
                    @endif
                    
                    @if($session->status === 'cancelled')
                        <div class="flex items-start space-x-3">
                            <div class="w-2 h-2 bg-red-500 rounded-full mt-2"></div>
                            <div>
                                <flux:text size="sm" class="font-medium">Session Cancelled</flux:text>
                                <flux:text size="xs" class="text-gray-500 dark:text-gray-400">{{ $session->updated_at->format('M d, Y g:i A') }}</flux:text>
                            </div>
                        </div>
                    @endif
                    
                    @if($session->verified_at)
                        <div class="flex items-start space-x-3">
                            <div class="w-2 h-2 bg-emerald-500 rounded-full mt-2"></div>
                            <div>
                                <flux:text size="sm" class="font-medium">Session Verified</flux:text>
                                <flux:text size="xs" class="text-gray-500 dark:text-gray-400">{{ $session->verified_at->format('M d, Y g:i A') }}</flux:text>
                                @if($session->verifier)
                                    <flux:text size="xs" class="text-gray-500 dark:text-gray-400">by {{ $session->verifier->name }}</flux:text>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </flux:card>

            <!-- Class Information -->
            <flux:card class="p-6">
                <flux:heading size="lg" class="mb-4">Class Information</flux:heading>
                
                <div class="space-y-3">
                    <div>
                        <flux:text class="text-sm text-gray-500 dark:text-gray-400">Class Title</flux:text>
                        <flux:text size="sm" class="font-medium">{{ $session->class->title }}</flux:text>
                    </div>
                    
                    <div>
                        <flux:text class="text-sm text-gray-500 dark:text-gray-400">Course</flux:text>
                        <flux:text size="sm" class="font-medium">{{ $session->class->course->name }}</flux:text>
                    </div>
                    
                    @if($session->class->description)
                        <div>
                            <flux:text class="text-sm text-gray-500 dark:text-gray-400">Description</flux:text>
                            <flux:text size="sm" class="text-gray-700 dark:text-gray-300">{{ Str::limit($session->class->description, 100) }}</flux:text>
                        </div>
                    @endif
                    
                    @if($session->class->capacity)
                        <div>
                            <flux:text class="text-sm text-gray-500 dark:text-gray-400">Class Capacity</flux:text>
                            <flux:text size="sm" class="font-medium">{{ $session->class->capacity }} students</flux:text>
                        </div>
                    @endif
                </div>
            </flux:card>
        </div>
    </div>
</div>