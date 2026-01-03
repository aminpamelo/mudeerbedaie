<?php

use Livewire\Volt\Component;
use App\Models\ClassSession;
use App\Models\Teacher;

new class extends Component {
    public ClassSession $session;
    public bool $showAssignModal = false;
    public ?int $selectedTeacherId = null;

    public function mount(ClassSession $session)
    {
        $this->session = $session->load(['class.course', 'class.teacher.user', 'attendances.student.user', 'verifier', 'starter', 'assignedTeacher.user']);
        $this->selectedTeacherId = $session->assigned_to;
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

    public function openAssignModal()
    {
        $this->selectedTeacherId = $this->session->assigned_to;
        $this->showAssignModal = true;
    }

    public function assignTeacher()
    {
        $teacher = $this->selectedTeacherId ? Teacher::find($this->selectedTeacherId) : null;
        $this->session->assignTeacher($teacher);
        $this->session->refresh()->load(['assignedTeacher.user']);
        $this->showAssignModal = false;
        session()->flash('success', $teacher ? 'Teacher assigned successfully.' : 'Teacher assignment removed.');
    }

    public function removeAssignment()
    {
        $this->session->assignTeacher(null);
        $this->session->refresh();
        $this->selectedTeacherId = null;
        $this->showAssignModal = false;
        session()->flash('success', 'Teacher assignment removed.');
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

    public function with(): array
    {
        return [
            'teachers' => Teacher::with('user')->get(),
        ];
    }
}; ?>

<div>
    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex items-center gap-2 text-sm text-gray-500  mb-2">
            <flux:link href="{{ route('admin.sessions.index') }}" class="hover:text-gray-700 :text-gray-300">Sessions</flux:link>
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
                        <flux:text class="text-sm text-gray-500  mb-1">Teacher</flux:text>
                        <flux:text size="lg">
                            @if($session->class->teacher)
                                {{ $session->class->teacher->user->name }}
                            @else
                                <span class="text-gray-400">No teacher assigned</span>
                            @endif
                        </flux:text>
                    </div>
                    
                    <div>
                        <flux:text class="text-sm text-gray-500  mb-1">Date & Time</flux:text>
                        <flux:text size="lg">{{ $session->formatted_date_time }}</flux:text>
                    </div>

                    <div>
                        <flux:text class="text-sm text-gray-500 mb-1">Assigned Teacher</flux:text>
                        <div class="flex items-center gap-2">
                            @if($session->assignedTeacher)
                                <flux:text size="lg">{{ $session->assignedTeacher->user->name }}</flux:text>
                                <flux:badge color="amber" size="sm">Substitute</flux:badge>
                            @else
                                <flux:text size="lg" class="text-gray-400">Same as class teacher</flux:text>
                            @endif
                            @if($session->isScheduled())
                                <flux:button size="xs" variant="ghost" wire:click="openAssignModal">
                                    <flux:icon name="pencil" class="w-3 h-3" />
                                </flux:button>
                            @endif
                        </div>
                    </div>

                    @if($session->started_by)
                        <div>
                            <flux:text class="text-sm text-gray-500 mb-1">Started By</flux:text>
                            <div class="flex items-center gap-2">
                                <flux:text size="lg">{{ $session->starter->name ?? 'Unknown' }}</flux:text>
                                @if($session->class->teacher && $session->started_by !== $session->class->teacher->user_id)
                                    <flux:badge color="amber" size="sm">Not Class Teacher</flux:badge>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Enhanced Duration Tracking -->
                    <div class="md:col-span-2">
                        <flux:text class="text-sm text-gray-500  mb-1">Duration Tracking</flux:text>
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <flux:text size="lg" class="text-gray-700">
                                    <span class="font-medium">Target:</span> {{ $session->formatted_duration }}
                                </flux:text>
                            </div>

                            @if($session->formatted_actual_duration)
                                <div class="flex items-center gap-2">
                                    <flux:text size="lg" class="{{ $session->meetsKpi() === true ? 'text-green-700' : ($session->meetsKpi() === false ? 'text-red-700' : 'text-gray-700') }}">
                                        <span class="font-medium">Actual:</span> {{ $session->formatted_actual_duration }}
                                    </flux:text>
                                </div>
                                <div class="flex items-center gap-2">
                                    <flux:badge :class="$session->kpi_badge_class">
                                        {{ $session->meetsKpi() ? 'KPI Met' : 'KPI Missed' }}
                                    </flux:badge>
                                    <flux:text size="sm" class="{{ $session->meetsKpi() === true ? 'text-green-600' : ($session->meetsKpi() === false ? 'text-red-600' : 'text-gray-500') }}">
                                        {{ $session->duration_comparison }}
                                    </flux:text>
                                </div>
                            @elseif($session->isOngoing())
                                <div class="flex items-center gap-2">
                                    <flux:text size="lg" class="text-yellow-700">
                                        <span class="font-medium">Current:</span>
                                        <span
                                            x-data="sessionTimer('{{ $session->started_at ? $session->started_at->toISOString() : now()->toISOString() }}')"
                                            x-init="startTimer()"
                                            class="font-mono"
                                            x-text="formattedTime"
                                        ></span>
                                    </flux:text>
                                </div>
                                <flux:badge variant="outline" class="animate-pulse">
                                    Session In Progress
                                </flux:badge>
                            @else
                                <flux:text size="sm" class="text-gray-500">
                                    Session not started yet
                                </flux:text>
                            @endif
                        </div>
                    </div>
                    
                    @if($session->topic)
                        <div>
                            <flux:text class="text-sm text-gray-500  mb-1">Topic</flux:text>
                            <flux:text size="lg">{{ $session->topic }}</flux:text>
                        </div>
                    @endif
                    
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
                    
                    @if($session->verified_at)
                        <div>
                            <flux:text class="text-sm text-gray-500  mb-1">Verification Status</flux:text>
                            <div class="flex items-center gap-2">
                                <flux:badge color="green" size="sm">Verified</flux:badge>
                                <flux:text size="sm" class="text-gray-600">
                                    {{ $session->verified_at->format('M d, Y g:i A') }}
                                </flux:text>
                            </div>
                        </div>
                        
                        <div>
                            <flux:text class="text-sm text-gray-500  mb-1">Verified By</flux:text>
                            <flux:text size="lg">
                                {{ $session->verifier ? $session->verifier->name : 'Unknown User' }}
                                @if($session->verifier_role)
                                    <span class="text-sm text-gray-500">({{ ucfirst($session->verifier_role) }})</span>
                                @endif
                            </flux:text>
                        </div>
                    @elseif($session->status === 'completed' && $session->allowance_amount)
                        <div>
                            <flux:text class="text-sm text-gray-500  mb-1">Verification Status</flux:text>
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
                                    
                                    @if($attendance->checked_in_at)
                                        <flux:text size="xs" class="text-gray-500">
                                            {{ $attendance->checked_in_at->format('g:i A') }}
                                        </flux:text>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    
                    <!-- Attendance Summary -->
                    <div class="mt-4 p-4 bg-blue-50 /20 rounded-lg">
                        @php
                            $totalStudents = $session->attendances->count();
                            $presentCount = $session->attendances->whereIn('status', ['present', 'late'])->count();
                            $absentCount = $session->attendances->where('status', 'absent')->count();
                            $attendanceRate = $totalStudents > 0 ? round(($presentCount / $totalStudents) * 100) : 0;
                        @endphp
                        <flux:heading size="sm" class="mb-2">Attendance Summary</flux:heading>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                            <div>
                                <div class="text-lg font-semibold text-blue-600">{{ $totalStudents }}</div>
                                <div class="text-gray-600">Total Students</div>
                            </div>
                            <div>
                                <div class="text-lg font-semibold text-green-600">{{ $presentCount }}</div>
                                <div class="text-gray-600">Present/Late</div>
                            </div>
                            <div>
                                <div class="text-lg font-semibold text-red-600">{{ $absentCount }}</div>
                                <div class="text-gray-600">Absent</div>
                            </div>
                            <div>
                                <div class="text-lg font-semibold text-purple-600">{{ $attendanceRate }}%</div>
                                <div class="text-gray-600">Attendance Rate</div>
                            </div>
                        </div>
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
                    @if($session->status === 'completed' && $session->allowance_amount && !$session->verified_at)
                        <flux:button wire:click="verifySession" variant="primary" class="w-full">
                            <div class="flex items-center justify-center">
                                <flux:icon name="check-badge" class="w-4 h-4 mr-2" />
                                Verify Session for Payroll
                            </div>
                        </flux:button>
                    @elseif($session->verified_at)
                        <flux:button wire:click="unverifySession" variant="ghost" class="w-full">
                            <div class="flex items-center justify-center">
                                <flux:icon name="x-mark" class="w-4 h-4 mr-2" />
                                Remove Verification
                            </div>
                        </flux:button>
                    @endif
                    
                    <flux:button href="{{ route('classes.show', $session->class) }}" variant="ghost" class="w-full">
                        <div class="flex items-center justify-center">
                            <flux:icon name="academic-cap" class="w-4 h-4 mr-2" />
                            View Class Details
                        </div>
                    </flux:button>
                    
                    @if($session->class->teacher)
                        <flux:button href="{{ route('teachers.show', $session->class->teacher) }}" variant="ghost" class="w-full">
                            <div class="flex items-center justify-center">
                                <flux:icon name="user" class="w-4 h-4 mr-2" />
                                View Teacher Profile
                            </div>
                        </flux:button>
                    @endif
                    
                    <flux:button href="{{ route('admin.sessions.index') }}" variant="ghost" class="w-full">
                        <div class="flex items-center justify-center">
                            <flux:icon name="arrow-left" class="w-4 h-4 mr-2" />
                            Back to Sessions List
                        </div>
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
                                @if($session->starter)
                                    <flux:text size="xs" class="text-gray-500">by {{ $session->starter->name }}</flux:text>
                                @endif
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
                    
                    @if($session->status === 'cancelled')
                        <div class="flex items-start space-x-3">
                            <div class="w-2 h-2 bg-red-500 rounded-full mt-2"></div>
                            <div>
                                <flux:text size="sm" class="font-medium">Session Cancelled</flux:text>
                                <flux:text size="xs" class="text-gray-500">{{ $session->updated_at->format('M d, Y g:i A') }}</flux:text>
                            </div>
                        </div>
                    @endif
                    
                    @if($session->verified_at)
                        <div class="flex items-start space-x-3">
                            <div class="w-2 h-2 bg-emerald-500 rounded-full mt-2"></div>
                            <div>
                                <flux:text size="sm" class="font-medium">Session Verified</flux:text>
                                <flux:text size="xs" class="text-gray-500">{{ $session->verified_at->format('M d, Y g:i A') }}</flux:text>
                                @if($session->verifier)
                                    <flux:text size="xs" class="text-gray-500">by {{ $session->verifier->name }}</flux:text>
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
                        <flux:text class="text-sm text-gray-500">Class Title</flux:text>
                        <flux:text size="sm" class="font-medium">{{ $session->class->title }}</flux:text>
                    </div>
                    
                    <div>
                        <flux:text class="text-sm text-gray-500">Course</flux:text>
                        <flux:text size="sm" class="font-medium">{{ $session->class->course->name }}</flux:text>
                    </div>
                    
                    @if($session->class->description)
                        <div>
                            <flux:text class="text-sm text-gray-500">Description</flux:text>
                            <flux:text size="sm" class="text-gray-700">{{ Str::limit($session->class->description, 100) }}</flux:text>
                        </div>
                    @endif
                    
                    @if($session->class->capacity)
                        <div>
                            <flux:text class="text-sm text-gray-500">Class Capacity</flux:text>
                            <flux:text size="sm" class="font-medium">{{ $session->class->capacity }} students</flux:text>
                        </div>
                    @endif
                </div>
            </flux:card>
        </div>
    </div>

    <!-- Assign Teacher Modal -->
    <flux:modal wire:model="showAssignModal" class="max-w-md">
        <div class="p-6">
            <flux:heading size="lg" class="mb-4">Assign Teacher to Session</flux:heading>
            <flux:text class="mb-4 text-gray-600">
                Select a teacher to conduct this session. Leave empty to use the class teacher.
            </flux:text>

            <flux:field class="mb-6">
                <flux:label>Select Teacher</flux:label>
                <flux:select wire:model="selectedTeacherId">
                    <option value="">-- Use Class Teacher ({{ $session->class->teacher?->user?->name ?? 'None' }}) --</option>
                    @foreach($teachers as $teacher)
                        <option value="{{ $teacher->id }}">{{ $teacher->user->name }}</option>
                    @endforeach
                </flux:select>
            </flux:field>

            @if($session->assigned_to)
                <div class="mb-4 p-3 bg-amber-50 rounded-lg">
                    <flux:text size="sm" class="text-amber-800">
                        Currently assigned to: <strong>{{ $session->assignedTeacher?->user?->name }}</strong>
                    </flux:text>
                </div>
            @endif

            <div class="flex justify-end gap-3">
                @if($session->assigned_to)
                    <flux:button variant="danger" wire:click="removeAssignment">
                        <div class="flex items-center justify-center">
                            <flux:icon name="x-mark" class="w-4 h-4 mr-1" />
                            Remove Assignment
                        </div>
                    </flux:button>
                @endif
                <flux:button variant="ghost" wire:click="$set('showAssignModal', false)">Cancel</flux:button>
                <flux:button variant="primary" wire:click="assignTeacher">
                    <div class="flex items-center justify-center">
                        <flux:icon name="check" class="w-4 h-4 mr-1" />
                        Save Assignment
                    </div>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>

<script>
function sessionTimer(startTime) {
    return {
        startTime: new Date(startTime),
        currentTime: '',
        formattedTime: '',
        interval: null,

        startTimer() {
            this.updateTime();
            this.interval = setInterval(() => {
                this.updateTime();
            }, 1000);
        },

        updateTime() {
            const now = new Date();
            const diffInSeconds = Math.floor((now - this.startTime) / 1000);

            if (diffInSeconds < 0) {
                this.formattedTime = '0:00';
                return;
            }

            const hours = Math.floor(diffInSeconds / 3600);
            const minutes = Math.floor((diffInSeconds % 3600) / 60);
            const seconds = diffInSeconds % 60;

            if (hours > 0) {
                this.formattedTime = `${hours}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            } else {
                this.formattedTime = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            }
        },

        destroy() {
            if (this.interval) {
                clearInterval(this.interval);
            }
        }
    }
}
</script>