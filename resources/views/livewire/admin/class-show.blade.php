<?php

use App\Models\ClassModel;
use Livewire\Volt\Component;

new class extends Component {
    public ClassModel $class;

    public function mount(ClassModel $class): void
    {
        $this->class = $class->load([
            'course',
            'teacher.user',
            'sessions.attendances.student.user',
            'activeStudents.student.user',
            'timetable'
        ]);
    }

    public function getEnrolledStudentsCountProperty()
    {
        return $this->class->course->activeEnrollments()->count();
    }

    public function getTotalSessionsCountProperty(): int
    {
        return $this->class->sessions->count();
    }

    public function getCompletedSessionsCountProperty(): int
    {
        return $this->class->sessions->where('status', 'completed')->count();
    }

    public function getUpcomingSessionsCountProperty(): int
    {
        return $this->class->sessions->where('status', 'scheduled')->where('session_date', '>', now()->toDateString())->count();
    }

    public function getTotalAttendanceRecordsProperty(): int
    {
        return $this->class->sessions->sum(function ($session) {
            return $session->attendances->count();
        });
    }

    public function getTotalPresentCountProperty(): int
    {
        return $this->class->sessions->sum(function ($session) {
            return $session->attendances->where('status', 'present')->count();
        });
    }

    public function getTotalAbsentCountProperty(): int
    {
        return $this->class->sessions->sum(function ($session) {
            return $session->attendances->where('status', 'absent')->count();
        });
    }

    public function getTotalLateCountProperty(): int
    {
        return $this->class->sessions->sum(function ($session) {
            return $session->attendances->where('status', 'late')->count();
        });
    }

    public function getTotalExcusedCountProperty(): int
    {
        return $this->class->sessions->sum(function ($session) {
            return $session->attendances->where('status', 'excused')->count();
        });
    }

    public function getOverallAttendanceRateProperty(): float
    {
        if ($this->total_attendance_records === 0) {
            return 0;
        }
        
        return round(($this->total_present_count / $this->total_attendance_records) * 100, 1);
    }

    public function getSessionsByMonthProperty(): array
    {
        return $this->class->sessions
            ->sortBy('session_date')
            ->groupBy(function($session) {
                return $session->session_date->format('Y-m');
            })
            ->map(function($sessions, $key) {
                [$year, $month] = explode('-', $key);
                return [
                    'year' => $year,
                    'month' => $month,
                    'month_name' => \Carbon\Carbon::createFromFormat('m', $month)->format('F'),
                    'sessions' => $sessions,
                    'stats' => [
                        'total' => $sessions->count(),
                        'completed' => $sessions->where('status', 'completed')->count(),
                        'cancelled' => $sessions->where('status', 'cancelled')->count(),
                        'no_show' => $sessions->where('status', 'no_show')->count(),
                        'upcoming' => $sessions->where('status', 'scheduled')->count(),
                        'ongoing' => $sessions->where('status', 'ongoing')->count(),
                    ]
                ];
            })
            ->toArray();
    }

    public $activeTab = 'overview';
    public $showCreateSessionModal = false;
    public $showEnrollStudentsModal = false;
    
    // Session creation properties
    public $sessionDate = '';
    public $sessionTime = '';
    public $duration = 60;
    
    // Student enrollment properties
    public $studentSearch = '';
    public $selectedStudents = [];

    public function openCreateSessionModal(): void
    {
        $this->showCreateSessionModal = true;
        // Reset form fields
        $this->sessionDate = '';
        $this->sessionTime = '';
        $this->duration = 60;
    }

    public function closeCreateSessionModal(): void
    {
        $this->showCreateSessionModal = false;
        // Reset form fields
        $this->sessionDate = '';
        $this->sessionTime = '';
        $this->duration = 60;
    }

    public function createSession(): void
    {
        $this->validate([
            'sessionDate' => 'required|date|after_or_equal:today',
            'sessionTime' => 'required',
            'duration' => 'required|integer|min:15|max:480'
        ], [
            'sessionDate.required' => 'Session date is required.',
            'sessionDate.date' => 'Please enter a valid date.',
            'sessionDate.after_or_equal' => 'Session date cannot be in the past.',
            'sessionTime.required' => 'Session time is required.',
            'duration.required' => 'Duration is required.',
            'duration.integer' => 'Duration must be a number.',
            'duration.min' => 'Duration must be at least 15 minutes.',
            'duration.max' => 'Duration cannot exceed 8 hours (480 minutes).'
        ]);

        try {
            \App\Models\ClassSession::create([
                'class_id' => $this->class->id,
                'session_date' => $this->sessionDate,
                'session_time' => $this->sessionTime,
                'duration_minutes' => $this->duration,
                'status' => 'scheduled'
            ]);

            session()->flash('success', 'Session created successfully.');
            $this->closeCreateSessionModal();
            
            // Refresh the class data to show the new session
            $this->class->refresh();
            $this->class->load([
                'course',
                'teacher.user',
                'sessions.attendances.student.user',
                'activeStudents.student.user'
            ]);
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to create session. Please try again.');
        }
    }

    public function openEnrollStudentsModal(): void
    {
        $this->showEnrollStudentsModal = true;
        $this->studentSearch = '';
        $this->selectedStudents = [];
    }

    public function closeEnrollStudentsModal(): void
    {
        $this->showEnrollStudentsModal = false;
        $this->studentSearch = '';
        $this->selectedStudents = [];
    }

    public function getAvailableStudentsProperty()
    {
        // Get students enrolled in the course but not in this class
        $enrolledStudentIds = $this->class->course->activeEnrollments()
            ->pluck('student_id')
            ->toArray();
        
        $classStudentIds = $this->class->activeStudents()
            ->pluck('student_id')
            ->toArray();
        
        $availableStudentIds = array_diff($enrolledStudentIds, $classStudentIds);
        
        $query = \App\Models\Student::whereIn('id', $availableStudentIds)
            ->with('user');
        
        // Apply search filter
        if (!empty($this->studentSearch)) {
            $query->where(function($q) {
                $q->whereHas('user', function($userQuery) {
                    $userQuery->where('name', 'like', '%' . $this->studentSearch . '%')
                             ->orWhere('email', 'like', '%' . $this->studentSearch . '%');
                })
                ->orWhere('student_id', 'like', '%' . $this->studentSearch . '%');
            });
        }
        
        return $query->orderBy('created_at', 'desc')->get();
    }

    public function enrollSelectedStudents(): void
    {
        if (empty($this->selectedStudents)) {
            session()->flash('error', 'Please select at least one student to enroll.');
            return;
        }

        // Check capacity if class has max capacity
        if ($this->class->max_capacity) {
            $currentCount = $this->class->activeStudents()->count();
            $selectedCount = count($this->selectedStudents);
            
            if (($currentCount + $selectedCount) > $this->class->max_capacity) {
                session()->flash('error', 'Cannot enroll students. Class capacity would be exceeded.');
                return;
            }
        }

        $enrolled = 0;
        foreach ($this->selectedStudents as $studentId) {
            try {
                $student = \App\Models\Student::find($studentId);
                if ($student) {
                    $this->class->addStudent($student);
                    $enrolled++;
                }
            } catch (\Exception $e) {
                // Skip if student already enrolled or other error
                continue;
            }
        }

        if ($enrolled > 0) {
            session()->flash('success', "Successfully enrolled {$enrolled} student(s) in the class.");
            $this->closeEnrollStudentsModal();
            
            // Refresh the class data to show updated student list
            $this->class->refresh();
            $this->class->load([
                'course',
                'teacher.user',
                'sessions.attendances.student.user',
                'activeStudents.student.user'
            ]);
        } else {
            session()->flash('error', 'No students were enrolled. They may already be in the class.');
        }
    }

    public function markSessionAsOngoing($sessionId): void
    {
        $session = \App\Models\ClassSession::find($sessionId);
        if ($session && $session->isScheduled()) {
            $session->markAsOngoing();
            session()->flash('success', 'Session marked as ongoing.');
        }
    }

    public function openCompletionModal($sessionId): void
    {
        $this->completingSession = \App\Models\ClassSession::find($sessionId);
        if ($this->completingSession && ($this->completingSession->isScheduled() || $this->completingSession->isOngoing())) {
            $this->completionBookmark = $this->completingSession->bookmark ?? '';
            $this->showCompletionModal = true;
        }
    }

    public function closeCompletionModal(): void
    {
        $this->showCompletionModal = false;
        $this->completingSession = null;
        $this->completionBookmark = '';
    }

    public function completeSessionWithBookmark(): void
    {
        $this->validate([
            'completionBookmark' => 'required|string|min:3|max:500'
        ], [
            'completionBookmark.required' => 'Bookmark is required before completing the session.',
            'completionBookmark.min' => 'Bookmark must be at least 3 characters.',
            'completionBookmark.max' => 'Bookmark cannot exceed 500 characters.'
        ]);

        if ($this->completingSession && ($this->completingSession->isScheduled() || $this->completingSession->isOngoing())) {
            $this->completingSession->markCompleted($this->completionBookmark);
            session()->flash('success', 'Session completed with bookmark.');
            
            $this->closeCompletionModal();
            
            // Close session modal if it's open
            if ($this->showSessionModal) {
                $this->closeSessionModal();
            }
            
            // Refresh data
            $this->class->refresh();
            $this->class->load([
                'course',
                'teacher.user',
                'sessions.attendances.student.user',
                'activeStudents.student.user'
            ]);
        }
    }

    public function markSessionAsNoShow($sessionId): void
    {
        $session = \App\Models\ClassSession::find($sessionId);
        if ($session && ($session->isScheduled() || $session->isOngoing())) {
            $session->markAsNoShow('Student did not attend');
            session()->flash('success', 'Session marked as no-show.');
        }
    }

    public function markSessionAsCancelled($sessionId): void
    {
        $session = \App\Models\ClassSession::find($sessionId);
        if ($session && ($session->isScheduled() || $session->isOngoing())) {
            $session->cancel();
            session()->flash('success', 'Session cancelled.');
        }
    }

    public $showSessionModal = false;
    public $showCompletionModal = false;
    public $showAttendanceViewModal = false;
    public $currentSession = null;
    public $completingSession = null;
    public $viewingSession = null;
    public $completionBookmark = '';

    public function openSessionModal($sessionId): void
    {
        $this->currentSession = \App\Models\ClassSession::with(['attendances.student.user'])->find($sessionId);
        if ($this->currentSession && $this->currentSession->isOngoing()) {
            $this->bookmarkText = $this->currentSession->bookmark ?? '';
            $this->showSessionModal = true;
        }
    }

    public function closeSessionModal(): void
    {
        $this->showSessionModal = false;
        $this->currentSession = null;
    }

    public function openAttendanceViewModal($sessionId): void
    {
        $this->viewingSession = \App\Models\ClassSession::with(['attendances.student.user'])->find($sessionId);
        if ($this->viewingSession && $this->viewingSession->isCompleted()) {
            $this->showAttendanceViewModal = true;
        }
    }

    public function closeAttendanceViewModal(): void
    {
        $this->showAttendanceViewModal = false;
        $this->viewingSession = null;
    }

    public function updateStudentAttendance($studentId, $status): void
    {
        if (!$this->currentSession || !$this->currentSession->isOngoing()) {
            return;
        }

        $success = $this->currentSession->updateStudentAttendance($studentId, $status);
        
        if ($success) {
            // Refresh the current session data
            $this->currentSession = \App\Models\ClassSession::with(['attendances.student.user'])->find($this->currentSession->id);
            
            // Refresh the class data to update statistics
            $this->class->refresh();
            $this->class->load([
                'course',
                'teacher.user',
                'sessions.attendances.student.user',
                'activeStudents.student.user'
            ]);
            
            session()->flash('success', 'Attendance updated successfully.');
        }
    }

    public $bookmarkText = '';
    public $editingBookmark = false;

    public function updateSessionBookmark(): void
    {
        if (!$this->currentSession || !$this->currentSession->isOngoing()) {
            return;
        }

        $this->currentSession->updateBookmark($this->bookmarkText);
        
        // Refresh the current session data
        $this->currentSession = \App\Models\ClassSession::with(['attendances.student.user'])->find($this->currentSession->id);
        
        // Refresh the class data
        $this->class->refresh();
        $this->class->load([
            'course',
            'teacher.user',
            'sessions.attendances.student.user',
            'activeStudents.student.user'
        ]);
        
        session()->flash('success', 'Bookmark updated successfully.');
    }

    public function insertBookmarkTemplate($template): void
    {
        $this->bookmarkText = $template;
        $this->updateSessionBookmark();
    }

    public function setActiveTab($tab): void
    {
        $this->activeTab = $tab;
    }
};

?>

<div class="space-y-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $class->title }}</flux:heading>
            <flux:text class="mt-2">Class details and attendance</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button variant="ghost" href="{{ route('classes.edit', $class) }}" icon="pencil">
                Edit Class
            </flux:button>
            <flux:button variant="ghost" href="{{ route('classes.index') }}">
                Back to Classes
            </flux:button>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
        <nav class="flex space-x-8">
            <button 
                wire:click="setActiveTab('overview')"
                class="whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'overview' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
            >
                <div class="flex items-center gap-2">
                    <flux:icon.document-text class="h-4 w-4" />
                    Overview
                </div>
            </button>
            
            <button 
                wire:click="setActiveTab('timetable')"
                class="whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'timetable' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
            >
                <div class="flex items-center gap-2">
                    <flux:icon.calendar class="h-4 w-4" />
                    Timetable
                </div>
            </button>
        </nav>
    </div>

    <!-- Tab Content -->
    <div>
        <!-- Overview Tab -->
        <div class="{{ $activeTab === 'overview' ? 'block' : 'hidden' }}">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Class Information -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Basic Info -->
            <flux:card>
                <div class="p-6">
                    <flux:heading size="lg" class="mb-4">Class Information</flux:heading>
                    
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Course</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $class->course->name }}</dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Teacher</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $class->teacher->fullName }}</dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Date & Time</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $class->formatted_date_time }}</dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Duration</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $class->formatted_duration }}</dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Type</dt>
                            <dd class="mt-1">
                                <div class="flex items-center gap-2">
                                    @if($class->isIndividual())
                                        <flux:icon.user class="h-4 w-4 text-blue-500" />
                                        <span class="text-sm text-gray-900">Individual</span>
                                    @else
                                        <flux:icon.users class="h-4 w-4 text-green-500" />
                                        <span class="text-sm text-gray-900">Group</span>
                                        @if($class->max_capacity)
                                            <span class="text-xs text-gray-500">(Max: {{ $class->max_capacity }})</span>
                                        @endif
                                    @endif
                                </div>
                            </dd>
                        </div>
                        
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Status</dt>
                            <dd class="mt-1">
                                <flux:badge size="sm" :class="$class->status_badge_class">
                                    {{ ucfirst($class->status) }}
                                </flux:badge>
                            </dd>
                        </div>

                        @if($class->location)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Location</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $class->location }}</dd>
                            </div>
                        @endif

                        @if($class->meeting_url)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Meeting URL</dt>
                                <dd class="mt-1">
                                    <a href="{{ $class->meeting_url }}" target="_blank" class="text-blue-600 hover:text-blue-800 text-sm">
                                        Join Meeting
                                    </a>
                                </dd>
                            </div>
                        @endif
                        
                        @if($class->description)
                            <div class="sm:col-span-2">
                                <dt class="text-sm font-medium text-gray-500">Description</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $class->description }}</dd>
                            </div>
                        @endif
                        
                        @if($class->notes)
                            <div class="sm:col-span-2">
                                <dt class="text-sm font-medium text-gray-500">Notes</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $class->notes }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </flux:card>

            <!-- Teacher Allowance Info -->
            <flux:card>
                <div class="p-6">
                    <flux:heading size="lg" class="mb-4">Teacher Allowance</flux:heading>
                    
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Rate Type</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ match($class->rate_type) {
                                    'per_class' => 'Per Class (Fixed)',
                                    'per_student' => 'Per Student',
                                    'per_session' => 'Per Session (Commission)',
                                    default => ucfirst($class->rate_type)
                                } }}
                            </dd>
                        </div>
                        
                        @if($class->rate_type !== 'per_session')
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Rate Amount</dt>
                                <dd class="mt-1 text-sm text-gray-900">RM {{ number_format($class->teacher_rate, 2) }}</dd>
                            </div>
                        @endif

                        @if($class->rate_type === 'per_session')
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Commission Type</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ $class->commission_type === 'percentage' ? 'Percentage' : 'Fixed Amount' }}
                                </dd>
                            </div>
                            
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Commission Value</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    @if($class->commission_type === 'percentage')
                                        {{ number_format($class->commission_value, 1) }}%
                                    @else
                                        RM {{ number_format($class->commission_value, 2) }}
                                    @endif
                                </dd>
                            </div>
                        @endif

                        @if($class->completed_sessions > 0)
                            <div class="sm:col-span-2">
                                <dt class="text-sm font-medium text-gray-500">Total Teacher Allowance</dt>
                                <dd class="mt-1">
                                    <span class="text-lg font-semibold text-green-600">
                                        RM {{ number_format($class->calculateTotalTeacherAllowance(), 2) }}
                                    </span>
                                    <span class="text-sm text-gray-500 ml-2">
                                        ({{ $class->completed_sessions }} session(s) completed)
                                    </span>
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </flux:card>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Attendance Summary -->
            <flux:card>
                <div class="p-6">
                    <flux:heading size="lg" class="mb-4">Attendance Summary</flux:heading>
                    
                    <!-- Session Stats -->
                    <div class="mb-4 space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Total Sessions:</span>
                            <span class="font-medium">{{ $this->total_sessions_count }}</span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-green-600">Completed:</span>
                            <span class="font-medium text-green-600">{{ $this->completed_sessions_count }}</span>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-blue-600">Upcoming:</span>
                            <span class="font-medium text-blue-600">{{ $this->upcoming_sessions_count }}</span>
                        </div>
                    </div>

                    @if($this->total_sessions_count > 0)
                        <div class="border-t pt-4">
                            <div class="text-sm font-medium text-gray-600 mb-3">Overall Attendance</div>
                            
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Total Records:</span>
                                    <span class="font-medium">{{ $this->total_attendance_records }}</span>
                                </div>
                                
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-green-600">Present:</span>
                                    <span class="font-medium text-green-600">{{ $this->total_present_count }}</span>
                                </div>
                                
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-red-600">Absent:</span>
                                    <span class="font-medium text-red-600">{{ $this->total_absent_count }}</span>
                                </div>
                                
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-yellow-600">Late:</span>
                                    <span class="font-medium text-yellow-600">{{ $this->total_late_count }}</span>
                                </div>
                                
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-blue-600">Excused:</span>
                                    <span class="font-medium text-blue-600">{{ $this->total_excused_count }}</span>
                                </div>
                            </div>

                            @if($this->total_attendance_records > 0)
                                <div class="mt-3 pt-3 border-t">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600">Attendance Rate:</span>
                                        <span class="font-medium">{{ $this->overall_attendance_rate }}%</span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </flux:card>

            <!-- Quick Actions -->
            <flux:card>
                <div class="p-6">
                    <flux:heading size="lg" class="mb-4">Quick Actions</flux:heading>
                        
                        <div class="space-y-3">
                            <flux:button 
                                variant="filled"
                                size="sm"
                                class="w-full"
                                icon="plus"
                                wire:click="openCreateSessionModal"
                            >
                                Add New Session
                            </flux:button>
                            
                            <flux:button 
                                href="{{ route('classes.edit', $class) }}" 
                                variant="ghost"
                                size="sm"
                                class="w-full"
                                icon="pencil"
                            >
                                Edit Class Details
                            </flux:button>

                            @if($this->upcoming_sessions_count > 0)
                                <div class="pt-2 border-t">
                                    <div class="text-xs font-medium text-gray-500 mb-2">Next Session</div>
                                    @php
                                        $nextSession = $class->sessions->where('status', 'scheduled')->where('session_date', '>', now()->toDateString())->sortBy('session_date')->first();
                                    @endphp
                                    @if($nextSession)
                                        <div class="text-sm text-gray-700 mb-2">
                                            {{ $nextSession->formatted_date_time }}
                                        </div>
                                        <flux:button 
                                            wire:click="markSessionAsOngoing({{ $nextSession->id }})"
                                            variant="ghost"
                                            size="sm"
                                            class="w-full"
                                            icon="play"
                                        >
                                            Start Session
                                        </flux:button>
                                    @endif
                                </div>
                            @endif

                            @php
                                $ongoingSession = $class->sessions->where('status', 'ongoing')->first();
                            @endphp
                            @if($ongoingSession)
                                <div class="pt-2 border-t">
                                    <div class="text-xs font-medium text-gray-500 mb-2">Current Session</div>
                                    <div class="text-sm text-gray-700 mb-2">
                                        {{ $ongoingSession->formatted_date_time }}
                                    </div>
                                    
                                    <div 
                                        x-data="sessionTimer('{{ $ongoingSession->started_at ? $ongoingSession->started_at->toISOString() : now()->toISOString() }}')" 
                                        x-init="startTimer()"
                                        class="flex items-center gap-2 mb-3 p-2 bg-yellow-50 dark:bg-yellow-900/20 rounded border border-yellow-200 dark:border-yellow-800"
                                    >
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 bg-yellow-500 rounded-full animate-pulse"></div>
                                            <span class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Running:</span>
                                            <span class="text-sm font-mono font-semibold text-yellow-900 dark:text-yellow-100" x-text="formattedTime"></span>
                                        </div>
                                    </div>
                                    
                                    @if($ongoingSession->hasBookmark())
                                        <div class="mb-3 p-2 bg-amber-50 dark:bg-amber-900/20 rounded border border-amber-200 dark:border-amber-800">
                                            <div class="flex items-center gap-2 mb-1">
                                                <flux:icon.bookmark class="h-3 w-3 text-amber-600" />
                                                <span class="text-xs font-medium text-amber-800 dark:text-amber-200">Current Progress:</span>
                                            </div>
                                            <div class="text-sm text-amber-900 dark:text-amber-100 font-medium">
                                                {{ $ongoingSession->bookmark }}
                                            </div>
                                        </div>
                                    @endif
                                    
                                    <div class="flex gap-2">
                                        <flux:button 
                                            wire:click="openCompletionModal({{ $ongoingSession->id }})"
                                            variant="filled"
                                            size="sm"
                                            class="flex-1"
                                            icon="check"
                                        >
                                            Complete
                                        </flux:button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </flux:card>
        </div>
    </div>

    <!-- Sessions Management - Full Width -->
    @if($this->total_sessions_count > 0)
        <flux:card>
            <div class="overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <flux:heading size="lg">Sessions</flux:heading>
                    <flux:button variant="primary" size="sm" icon="plus" wire:click="openCreateSessionModal">
                        Add Session
                    </flux:button>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date & Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Duration</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Bookmark</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Attendance</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Allowance</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900">
                            @php $hasAnySessions = count($this->sessions_by_month) > 0; @endphp
                            @if($hasAnySessions)
                                @foreach($this->sessions_by_month as $monthData)
                                    <!-- Month Header Row -->
                                    <tr class="bg-gray-50 dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600">
                                        <td colspan="7" class="px-6 py-3">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-3">
                                                    <flux:icon.calendar class="h-5 w-5 text-gray-500" />
                                                    <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $monthData['month_name'] }} {{ $monthData['year'] }}</span>
                                                    <flux:badge size="sm" variant="outline">{{ $monthData['stats']['total'] }} sessions</flux:badge>
                                                </div>
                                                <div class="flex gap-3 text-sm text-gray-500">
                                                    @if($monthData['stats']['completed'] > 0)
                                                        <span class="text-green-600">✓ {{ $monthData['stats']['completed'] }} completed</span>
                                                    @endif
                                                    @if($monthData['stats']['upcoming'] > 0)
                                                        <span class="text-blue-600">📅 {{ $monthData['stats']['upcoming'] }} upcoming</span>
                                                    @endif
                                                    @if($monthData['stats']['ongoing'] > 0)
                                                        <span class="text-yellow-600">▶️ {{ $monthData['stats']['ongoing'] }} running</span>
                                                    @endif
                                                    @if($monthData['stats']['cancelled'] > 0)
                                                        <span class="text-red-600">✗ {{ $monthData['stats']['cancelled'] }} cancelled</span>
                                                    @endif
                                                    @if($monthData['stats']['no_show'] > 0)
                                                        <span class="text-orange-600">👤 {{ $monthData['stats']['no_show'] }} no show</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    
                                    <!-- Sessions for this month -->
                                    @foreach($monthData['sessions'] as $session)
                                        <tr class="divide-y divide-gray-200 dark:divide-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    {{ $session->formatted_date_time }}
                                                </div>
                                            </td>
                                            
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $session->formatted_duration }}
                                            </td>
                                            
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if($session->isOngoing())
                                                    <div 
                                                        x-data="sessionTimer('{{ $session->started_at ? $session->started_at->toISOString() : now()->toISOString() }}')" 
                                                        x-init="startTimer()"
                                                        class="flex items-center gap-2"
                                                    >
                                                        <div class="flex items-center gap-1">
                                                            <div class="w-2 h-2 bg-yellow-500 rounded-full animate-pulse"></div>
                                                            <span class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Running</span>
                                                        </div>
                                                        <span class="text-sm font-mono font-semibold text-yellow-900 dark:text-yellow-100" x-text="formattedTime"></span>
                                                    </div>
                                                @else
                                                    <flux:badge size="sm" :class="match($session->status) {
                                                        'completed' => 'badge-green',
                                                        'scheduled' => 'badge-blue',
                                                        'ongoing' => 'badge-yellow',
                                                        'cancelled' => 'badge-red',
                                                        'no_show' => 'badge-orange',
                                                        'rescheduled' => 'badge-purple',
                                                        default => 'badge-gray'
                                                    }">
                                                        {{ match($session->status) {
                                                            'no_show' => 'No Show',
                                                            'rescheduled' => 'Rescheduled',
                                                            default => ucfirst($session->status)
                                                        } }}
                                                    </flux:badge>
                                                @endif
                                            </td>
                                            
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                @if($session->hasBookmark())
                                                    <div class="flex items-center gap-2 group" title="{{ $session->bookmark }}">
                                                        <flux:icon.bookmark class="h-4 w-4 text-amber-500" />
                                                        <span class="text-gray-900 dark:text-gray-100">{{ $session->formatted_bookmark }}</span>
                                                        @if($session->isOngoing())
                                                            <flux:button 
                                                                wire:click="openSessionModal({{ $session->id }})"
                                                                variant="ghost" 
                                                                size="sm" 
                                                                icon="pencil"
                                                                class="opacity-0 group-hover:opacity-100 transition-opacity text-amber-600 hover:text-amber-800"
                                                            />
                                                        @endif
                                                    </div>
                                                @else
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-gray-400">—</span>
                                                        @if($session->isOngoing())
                                                            <flux:button 
                                                                wire:click="openSessionModal({{ $session->id }})"
                                                                variant="ghost" 
                                                                size="sm" 
                                                                icon="plus"
                                                                class="text-amber-600 hover:text-amber-800"
                                                            >
                                                                Add
                                                            </flux:button>
                                                        @endif
                                                    </div>
                                                @endif
                                            </td>
                                            
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                @php
                                                    $presentCount = $session->attendances->where('status', 'present')->count();
                                                    $totalCount = $session->attendances->count();
                                                    $attendanceRate = $totalCount > 0 ? round(($presentCount / $totalCount) * 100) : 0;
                                                @endphp
                                                
                                                @if($totalCount == 0)
                                                    <span class="text-gray-400">—</span>
                                                @elseif($totalCount == 1)
                                                    @if($presentCount == 1)
                                                        <span class="text-green-600 text-lg">✓</span>
                                                    @else
                                                        <span class="text-red-600 text-lg">✗</span>
                                                    @endif
                                                @elseif($totalCount <= 5)
                                                    <div class="flex items-center gap-1">
                                                        @foreach($session->attendances as $att)
                                                            <div class="w-2 h-2 rounded-full {{ $att->status == 'present' ? 'bg-green-500' : ($att->status == 'late' ? 'bg-yellow-500' : 'bg-gray-300') }}" 
                                                                 title="{{ $att->student->fullName }}: {{ ucfirst($att->status) }}"></div>
                                                        @endforeach
                                                        <span class="text-xs text-gray-600 ml-1">{{ $presentCount }}</span>
                                                    </div>
                                                @else
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-sm font-semibold {{ $attendanceRate >= 80 ? 'text-green-600' : ($attendanceRate >= 60 ? 'text-amber-600' : 'text-red-600') }}">
                                                            {{ $presentCount }}
                                                        </span>
                                                        <div class="w-12 h-1.5 bg-gray-200 rounded-full">
                                                            <div class="h-full rounded-full {{ $attendanceRate >= 80 ? 'bg-green-500' : ($attendanceRate >= 60 ? 'bg-amber-500' : 'bg-red-500') }}" 
                                                                 style="width: {{ $attendanceRate }}%"></div>
                                                        </div>
                                                        <span class="text-xs text-gray-500">/ {{ $totalCount }}</span>
                                                    </div>
                                                @endif
                                            </td>
                                            
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                @if($session->isCompleted())
                                                    <span class="font-medium text-green-600">
                                                        RM {{ number_format($session->getTeacherAllowanceAmount(), 2) }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">—</span>
                                                @endif
                                            </td>
                                            
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                                <div class="flex items-center justify-end gap-2">
                                                    @if($session->isScheduled())
                                                        <flux:button 
                                                            wire:click="markSessionAsOngoing({{ $session->id }})"
                                                            variant="ghost" 
                                                            size="sm" 
                                                            icon="play"
                                                            class="text-yellow-600 hover:text-yellow-800"
                                                        >
                                                            Start
                                                        </flux:button>
                                                        
                                                        <flux:button 
                                                            wire:click="openCompletionModal({{ $session->id }})"
                                                            variant="ghost" 
                                                            size="sm" 
                                                            icon="check"
                                                            class="text-green-600 hover:text-green-800"
                                                        >
                                                            Complete
                                                        </flux:button>
                                                        
                                                        <flux:button 
                                                            wire:click="markSessionAsNoShow({{ $session->id }})"
                                                            variant="ghost" 
                                                            size="sm" 
                                                            icon="user-minus"
                                                            class="text-orange-600 hover:text-orange-800"
                                                        >
                                                            No Show
                                                        </flux:button>
                                                        
                                                        <flux:button 
                                                            wire:click="markSessionAsCancelled({{ $session->id }})"
                                                            variant="ghost" 
                                                            size="sm" 
                                                            icon="x-mark"
                                                            class="text-red-600 hover:text-red-800"
                                                        >
                                                            Cancel
                                                        </flux:button>
                                                        
                                                    @elseif($session->isOngoing())
                                                        <flux:button 
                                                            wire:click="openSessionModal({{ $session->id }})"
                                                            variant="ghost" 
                                                            size="sm" 
                                                            icon="users"
                                                            class="text-blue-600 hover:text-blue-800"
                                                        >
                                                            Manage
                                                        </flux:button>
                                                        
                                                        <flux:button 
                                                            wire:click="openCompletionModal({{ $session->id }})"
                                                            variant="ghost" 
                                                            size="sm" 
                                                            icon="check"
                                                            class="text-green-600 hover:text-green-800"
                                                        >
                                                            Complete
                                                        </flux:button>
                                                        
                                                        <flux:button 
                                                            wire:click="markSessionAsNoShow({{ $session->id }})"
                                                            variant="ghost" 
                                                            size="sm" 
                                                            icon="user-minus"
                                                            class="text-orange-600 hover:text-orange-800"
                                                        >
                                                            No Show
                                                        </flux:button>
                                                        
                                                    @elseif($session->isCompleted())
                                                        <flux:button 
                                                            wire:click="openAttendanceViewModal({{ $session->id }})"
                                                            variant="ghost" 
                                                            size="sm" 
                                                            icon="eye"
                                                            class="text-blue-600 hover:text-blue-800"
                                                        >
                                                            View Attendance
                                                        </flux:button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center">
                                        <div class="text-gray-500">
                                            <flux:icon.calendar class="mx-auto h-8 w-8 text-gray-400 mb-4" />
                                            <p>No sessions scheduled yet</p>
                                            <flux:button variant="primary" size="sm" class="mt-3" icon="plus" wire:click="openCreateSessionModal">
                                                Schedule First Session
                                            </flux:button>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </flux:card>
    @else
        <!-- No Sessions - Create First Session -->
        <flux:card>
            <div class="p-6 text-center">
                <flux:icon.calendar class="mx-auto h-12 w-12 text-gray-400 mb-4" />
                <flux:heading size="lg" class="mb-2">No Sessions Scheduled</flux:heading>
                <flux:text class="mb-4">This class doesn't have any sessions yet. Create the first session to get started.</flux:text>
                <flux:button variant="primary" icon="plus" wire:click="openCreateSessionModal">
                    Create First Session
                </flux:button>
            </div>
        </flux:card>
    @endif

    <!-- Enrolled Students -->
    @if($class->activeStudents->count() > 0)
        <flux:card>
            <div class="overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <div>
                        <flux:heading size="lg">Enrolled Students</flux:heading>
                        <flux:text size="sm" class="text-gray-500">
                            {{ $class->activeStudents->count() }} student(s) enrolled
                            @if($class->max_capacity)
                                / {{ $class->max_capacity }} max capacity
                            @endif
                        </flux:text>
                    </div>
                    
                    @if($class->class_type === 'group' && (!$class->max_capacity || $class->activeStudents->count() < $class->max_capacity))
                        <flux:button variant="primary" size="sm" icon="user-plus" wire:click="openEnrollStudentsModal">
                            Add Students
                        </flux:button>
                    @endif
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Student</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Enrolled</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Sessions Attended</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Attendance Rate</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($class->activeStudents as $classStudent)
                                @php
                                    $student = $classStudent->student;
                                    $completedSessions = $this->completed_sessions_count;
                                    
                                    // Calculate attendance for this student across all sessions
                                    $studentAttendances = collect();
                                    foreach($class->sessions as $session) {
                                        $attendance = $session->attendances->where('student_id', $student->id)->first();
                                        if($attendance) {
                                            $studentAttendances->push($attendance);
                                        }
                                    }
                                    
                                    $presentCount = $studentAttendances->where('status', 'present')->count();
                                    $totalRecords = $studentAttendances->count();
                                    $attendanceRate = $totalRecords > 0 ? round(($presentCount / $totalRecords) * 100, 1) : 0;
                                @endphp
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-3">
                                            <flux:avatar size="sm" :name="$student->fullName" />
                                            <div>
                                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $student->fullName }}</div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $student->student_id }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $classStudent->enrolled_at->format('M d, Y') }}
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-green-600">{{ $presentCount }}</span>
                                            <span>/</span>
                                            <span>{{ $totalRecords }}</span>
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        @if($totalRecords > 0)
                                            <div class="flex items-center gap-2">
                                                <span class="font-medium {{ $attendanceRate >= 80 ? 'text-green-600' : ($attendanceRate >= 60 ? 'text-yellow-600' : 'text-red-600') }}">
                                                    {{ $attendanceRate }}%
                                                </span>
                                                <div class="w-12 bg-gray-200 rounded-full h-2">
                                                    <div class="h-2 rounded-full {{ $attendanceRate >= 80 ? 'bg-green-500' : ($attendanceRate >= 60 ? 'bg-yellow-500' : 'bg-red-500') }}" 
                                                         style="width: {{ $attendanceRate }}%"></div>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-gray-400">No records</span>
                                        @endif
                                    </td>
                                    
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <flux:badge size="sm" class="badge-green">
                                            Active
                                        </flux:badge>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </flux:card>
    @elseif($class->isDraft() || $class->isActive())
        <!-- No Students Enrolled -->
        <flux:card>
            <div class="p-6 text-center">
                <flux:icon.users class="mx-auto h-12 w-12 text-gray-400 mb-4" />
                <flux:heading size="lg" class="mb-2">No Students Enrolled</flux:heading>
                <flux:text class="mb-4">This class doesn't have any students enrolled yet.</flux:text>
                <flux:button variant="primary" icon="user-plus" wire:click="openEnrollStudentsModal">
                    Enroll Students
                </flux:button>
            </div>
        </flux:card>
    @endif
        </div>
        <!-- End Overview Tab -->

        <!-- Timetable Tab -->
        <div class="{{ $activeTab === 'timetable' ? 'block' : 'hidden' }}">
            @if($class->timetable)
                <flux:card>
                    <div class="p-6">
                        <div class="mb-6 flex items-center justify-between">
                            <div>
                                <flux:heading size="lg">Class Timetable</flux:heading>
                                <flux:text class="mt-2">Weekly recurring schedule for this class</flux:text>
                            </div>
                        </div>

                        <!-- Timetable Info -->
                        <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                                <flux:text class="text-sm font-medium text-blue-800 dark:text-blue-200">Recurrence Pattern</flux:text>
                                <flux:text class="text-lg font-semibold text-blue-900 dark:text-blue-100">
                                    {{ ucfirst(str_replace('_', ' ', $class->timetable->recurrence_pattern)) }}
                                </flux:text>
                            </div>

                            <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg">
                                <flux:text class="text-sm font-medium text-green-800 dark:text-green-200">Total Sessions</flux:text>
                                <flux:text class="text-lg font-semibold text-green-900 dark:text-green-100">
                                    {{ $class->timetable->total_sessions ?? 'Unlimited' }}
                                </flux:text>
                            </div>

                            <div class="bg-purple-50 dark:bg-purple-900/20 p-4 rounded-lg">
                                <flux:text class="text-sm font-medium text-purple-800 dark:text-purple-200">Duration</flux:text>
                                <flux:text class="text-lg font-semibold text-purple-900 dark:text-purple-100">
                                    {{ $class->formatted_duration }}
                                </flux:text>
                            </div>
                        </div>

                        <!-- Date Range -->
                        <div class="mb-6 flex items-center gap-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <div class="flex items-center gap-2">
                                <flux:icon.calendar-days class="h-5 w-5 text-gray-600" />
                                <flux:text class="font-medium">Start Date:</flux:text>
                                <flux:text>{{ $class->timetable->start_date->format('M d, Y') }}</flux:text>
                            </div>
                            
                            @if($class->timetable->end_date)
                                <div class="flex items-center gap-2">
                                    <flux:icon.calendar class="h-5 w-5 text-gray-600" />
                                    <flux:text class="font-medium">End Date:</flux:text>
                                    <flux:text>{{ $class->timetable->end_date->format('M d, Y') }}</flux:text>
                                </div>
                            @endif
                        </div>

                        <!-- Weekly Schedule Grid -->
                        <div class="overflow-x-auto">
                            <div class="inline-block min-w-full">
                                <flux:heading size="md" class="mb-4">Weekly Schedule</flux:heading>
                                
                                <div class="grid grid-cols-7 gap-2 mb-2">
                                    @foreach(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day)
                                        <div class="text-center p-2 font-medium text-gray-700 dark:text-gray-300 text-sm">
                                            {{ $day }}
                                        </div>
                                    @endforeach
                                </div>

                                <div class="grid grid-cols-7 gap-2">
                                    @foreach(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $dayKey)
                                        <div class="min-h-32 border border-gray-200 dark:border-gray-700 rounded-lg p-2 bg-white dark:bg-gray-800">
                                            @if(isset($class->timetable->weekly_schedule[$dayKey]) && !empty($class->timetable->weekly_schedule[$dayKey]))
                                                @foreach($class->timetable->weekly_schedule[$dayKey] as $time)
                                                    <div class="mb-2 p-2 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200 rounded text-sm text-center font-medium">
                                                        {{ date('g:i A', strtotime($time)) }}
                                                    </div>
                                                @endforeach
                                            @else
                                                <div class="text-center text-gray-400 text-sm py-4">
                                                    No class
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <!-- Sessions Generated -->
                        @if($class->sessions->count() > 0)
                            <div class="mt-6 p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
                                <div class="flex items-center gap-2">
                                    <flux:icon.check-circle class="h-5 w-5 text-green-600" />
                                    <flux:text class="font-medium text-green-800 dark:text-green-200">
                                        {{ $class->sessions->count() }} sessions have been generated from this timetable
                                    </flux:text>
                                </div>
                            </div>
                        @endif
                    </div>
                </flux:card>
            @else
                <!-- No Timetable -->
                <flux:card>
                    <div class="p-6 text-center">
                        <flux:icon.calendar class="mx-auto h-12 w-12 text-gray-400 mb-4" />
                        <flux:heading size="lg" class="mb-2">No Timetable Configured</flux:heading>
                        <flux:text class="mb-4">This class doesn't have a recurring timetable. Sessions are managed individually.</flux:text>
                        <flux:button variant="primary" href="{{ route('classes.edit', $class) }}" icon="plus">
                            Add Timetable
                        </flux:button>
                    </div>
                </flux:card>
            @endif
        </div>
        <!-- End Timetable Tab -->
    </div>

    <!-- Create Session Modal -->
    <flux:modal name="create-session" :show="$showCreateSessionModal" wire:model="showCreateSessionModal">
        <div class="pb-4 border-b border-gray-200 mb-4 pt-8">
            <flux:heading size="lg">Create New Session</flux:heading>
        </div>
        
        <div class="space-y-4 mb-6">
            <flux:text>Create a new session for this class. Enter the session details below.</flux:text>
            
            <div class="space-y-4">
                <div>
                    <flux:field>
                        <flux:label>Session Date</flux:label>
                        <flux:input type="date" wire:model="sessionDate" />
                        <flux:error name="sessionDate" />
                    </flux:field>
                </div>
                
                <div>
                    <flux:field>
                        <flux:label>Session Time</flux:label>
                        <flux:input type="time" wire:model="sessionTime" />
                        <flux:error name="sessionTime" />
                    </flux:field>
                </div>
                
                <div>
                    <flux:field>
                        <flux:label>Duration (minutes)</flux:label>
                        <flux:input type="number" wire:model="duration" placeholder="60" />
                        <flux:error name="duration" />
                    </flux:field>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
            <flux:button variant="ghost" wire:click="closeCreateSessionModal">Cancel</flux:button>
            <flux:button variant="primary" wire:click="createSession">Create Session</flux:button>
        </div>
    </flux:modal>

    <!-- Enroll Students Modal -->
    <flux:modal name="enroll-students" :show="$showEnrollStudentsModal" wire:model="showEnrollStudentsModal">
        <div class="pb-4 border-b border-gray-200 mb-4 pt-8">
            <flux:heading size="lg">Enroll Students</flux:heading>
        </div>
        
        <div class="space-y-4 mb-6">
            <flux:text>Select students from the course to enroll in this class.</flux:text>
            
            <div>
                <flux:field>
                    <flux:label>Search Students</flux:label>
                    <flux:input 
                        wire:model.live="studentSearch" 
                        placeholder="Type student name, email or ID..." 
                    />
                </flux:field>
            </div>
            
            @if(count($this->available_students) > 0)
                <div class="max-h-80 overflow-y-auto border border-gray-200 rounded-lg">
                    @foreach($this->available_students as $student)
                        <div class="flex items-start gap-3 p-3 border-b border-gray-100 last:border-b-0 hover:bg-gray-50">
                            <flux:checkbox 
                                wire:model.live="selectedStudents" 
                                value="{{ $student->id }}"
                                class="mt-1"
                            />
                            
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <flux:text class="font-medium text-gray-900">
                                        {{ $student->user->name }}
                                    </flux:text>
                                    <flux:badge size="sm" variant="outline">
                                        {{ $student->student_id }}
                                    </flux:badge>
                                </div>
                                <flux:text class="text-sm text-gray-600">
                                    {{ $student->user->email }}
                                </flux:text>
                            </div>
                        </div>
                    @endforeach
                </div>
                
                @if(count($selectedStudents) > 0)
                    <div class="flex items-center gap-2 text-sm">
                        <flux:icon.check-circle class="h-4 w-4 text-green-600" />
                        <flux:text class="text-green-600 font-medium">
                            {{ count($selectedStudents) }} student(s) selected
                        </flux:text>
                    </div>
                @endif
            @else
                <div class="p-6 text-center bg-gray-50 rounded-lg">
                    <flux:icon.users class="mx-auto h-8 w-8 text-gray-400 mb-2" />
                    @if(empty($studentSearch))
                        <flux:text class="text-gray-500">
                            No students available to enroll. All course students are already in this class.
                        </flux:text>
                    @else
                        <flux:text class="text-gray-500">
                            No students found matching "{{ $studentSearch }}"
                        </flux:text>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
            <flux:button variant="ghost" wire:click="closeEnrollStudentsModal">Cancel</flux:button>
            <flux:button 
                variant="primary" 
                wire:click="enrollSelectedStudents"
                :disabled="count($selectedStudents) === 0"
            >
                Enroll {{ count($selectedStudents) > 0 ? count($selectedStudents) . ' Selected' : 'Selected' }}
            </flux:button>
        </div>
    </flux:modal>

    <!-- Session Management Modal -->
    <flux:modal name="session-management" :show="$showSessionModal" wire:model="showSessionModal" max-width="4xl">
        @if($currentSession)
            <div class="pb-4 border-b border-gray-200 mb-4 pt-8">
                <flux:heading size="lg">Manage Session - {{ $currentSession->formatted_date_time }}</flux:heading>
                <flux:text class="text-gray-600">Mark students as present, late, absent, or excused during this session</flux:text>
            </div>
            
            <!-- Session Timer -->
            <div 
                x-data="sessionTimer('{{ $currentSession->started_at ? $currentSession->started_at->toISOString() : now()->toISOString() }}')" 
                x-init="startTimer()"
                class="flex items-center gap-3 mb-6 p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-200 dark:border-yellow-800"
            >
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 bg-yellow-500 rounded-full animate-pulse"></div>
                    <span class="font-medium text-yellow-800 dark:text-yellow-200">Session Running:</span>
                    <span class="font-mono font-bold text-yellow-900 dark:text-yellow-100" x-text="formattedTime"></span>
                </div>
                
                <div class="ml-auto flex items-center gap-2 text-sm text-yellow-700 dark:text-yellow-300">
                    <span>{{ $currentSession->attendances->where('status', 'present')->count() }} present</span>
                    <span>•</span>
                    <span>{{ $currentSession->attendances->count() }} total</span>
                </div>
            </div>

            <!-- Session Bookmark -->
            <div class="mb-6 p-4 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
                <div class="flex items-center gap-2 mb-3">
                    <flux:icon.bookmark class="h-5 w-5 text-amber-600" />
                    <flux:heading size="sm" class="text-amber-800 dark:text-amber-200">Session Bookmark</flux:heading>
                </div>
                
                <div class="space-y-3">
                    <flux:input
                        wire:model="bookmarkText"
                        wire:change="updateSessionBookmark"
                        placeholder="e.g., Stopped at page 45, Chapter 3"
                        class="w-full"
                    />
                    
                    
                    @if($currentSession->hasBookmark())
                        <div class="text-sm text-amber-700 dark:text-amber-300">
                            Current bookmark: <span class="font-medium">{{ $currentSession->bookmark }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Student Attendance List -->
            <div class="space-y-3 max-h-96 overflow-y-auto">
                @foreach($currentSession->attendances as $attendance)
                    <div class="flex items-center justify-between p-3 border border-gray-200 dark:border-gray-700 rounded-lg">
                        <div class="flex items-center gap-3">
                            <flux:avatar size="sm" :name="$attendance->student->fullName" />
                            <div>
                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $attendance->student->fullName }}</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $attendance->student->student_id }}</div>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-2">
                            @foreach(['present', 'late', 'absent', 'excused'] as $status)
                                <flux:button
                                    wire:click="updateStudentAttendance({{ $attendance->student_id }}, '{{ $status }}')"
                                    variant="{{ $attendance->status === $status ? 'primary' : 'outline' }}"
                                    size="sm"
                                    class="{{ match($status) {
                                        'present' => 'text-green-600 border-green-600 hover:bg-green-50',
                                        'late' => 'text-yellow-600 border-yellow-600 hover:bg-yellow-50',
                                        'absent' => 'text-red-600 border-red-600 hover:bg-red-50',
                                        'excused' => 'text-blue-600 border-blue-600 hover:bg-blue-50',
                                        default => ''
                                    } }}"
                                >
                                    {{ ucfirst($status) }}
                                </flux:button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex justify-between items-center gap-3 pt-6 border-t border-gray-200 mt-6">
                <flux:button variant="ghost" wire:click="closeSessionModal">Close</flux:button>
                
                <div class="flex gap-2">
                    <flux:button 
                        wire:click="openCompletionModal({{ $currentSession->id }})"
                        variant="primary"
                        icon="check"
                    >
                        Complete Session
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

    <!-- Session Completion Modal -->
    <flux:modal name="session-completion" :show="$showCompletionModal" wire:model="showCompletionModal">
        @if($completingSession)
            <div class="pb-4 border-b border-gray-200 mb-4 pt-8">
                <flux:heading size="lg">Complete Session</flux:heading>
                <flux:text class="text-gray-600">{{ $completingSession->formatted_date_time }}</flux:text>
            </div>
            
            <div class="space-y-4 mb-6">
                <flux:text>Please add a bookmark to track your progress before completing this session.</flux:text>
                
                <div>
                    <flux:field>
                        <flux:label>Session Bookmark <span class="text-red-500">*</span></flux:label>
                        <flux:textarea 
                            wire:model="completionBookmark" 
                            placeholder="e.g., Completed Chapter 3, stopped at page 45, reviewed exercises 1-10"
                            rows="3"
                        />
                        <flux:error name="completionBookmark" />
                        <flux:description>Describe what was covered or where you stopped in this session.</flux:description>
                    </flux:field>
                </div>
                
                @if($completingSession->attendances->count() > 0)
                    <div class="p-3 bg-green-50 dark:bg-green-900/20 rounded border border-green-200 dark:border-green-800">
                        <div class="flex items-center gap-2 text-sm text-green-800 dark:text-green-200">
                            <flux:icon.check-circle class="h-4 w-4" />
                            <span>{{ $completingSession->attendances->where('status', 'present')->count() }} of {{ $completingSession->attendances->count() }} students marked as present</span>
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                <flux:button variant="ghost" wire:click="closeCompletionModal">Cancel</flux:button>
                <flux:button 
                    variant="primary" 
                    wire:click="completeSessionWithBookmark"
                    icon="check"
                >
                    Complete Session
                </flux:button>
            </div>
        @endif
    </flux:modal>

    <!-- Attendance View Modal -->
    <flux:modal name="attendance-view" :show="$showAttendanceViewModal" wire:model="showAttendanceViewModal" max-width="2xl">
        @if($viewingSession)
            <div class="pb-4 border-b border-gray-200 mb-4 pt-8">
                <flux:heading size="lg">Session Attendance</flux:heading>
                <flux:text class="text-gray-600">{{ $viewingSession->formatted_date_time }}</flux:text>
            </div>

            <!-- Session Summary -->
            <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <div class="grid grid-cols-4 gap-4 text-center">
                    <div>
                        <div class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $viewingSession->attendances->count() }}</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Total</div>
                    </div>
                    <div>
                        <div class="text-2xl font-semibold text-green-600">{{ $viewingSession->attendances->where('status', 'present')->count() }}</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Present</div>
                    </div>
                    <div>
                        <div class="text-2xl font-semibold text-yellow-600">{{ $viewingSession->attendances->where('status', 'late')->count() }}</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Late</div>
                    </div>
                    <div>
                        <div class="text-2xl font-semibold text-red-600">{{ $viewingSession->attendances->where('status', 'absent')->count() }}</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Absent</div>
                    </div>
                </div>
            </div>

            @if($viewingSession->hasBookmark())
                <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                    <div class="flex items-start gap-3">
                        <flux:icon.bookmark class="h-5 w-5 text-blue-600 mt-0.5 flex-shrink-0" />
                        <div>
                            <div class="text-sm font-medium text-blue-900 dark:text-blue-100 mb-1">Session Bookmark</div>
                            <div class="text-sm text-blue-800 dark:text-blue-200">{{ $viewingSession->bookmark }}</div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Student Attendance List -->
            <div class="space-y-3 max-h-96 overflow-y-auto">
                @foreach($viewingSession->attendances->sortBy('student.user.name') as $attendance)
                    <div class="flex items-center justify-between p-3 border border-gray-200 dark:border-gray-700 rounded-lg">
                        <div class="flex items-center gap-3">
                            <flux:avatar size="sm" :name="$attendance->student->fullName" />
                            <div>
                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $attendance->student->fullName }}</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $attendance->student->student_id }}</div>
                            </div>
                        </div>
                        
                        <div class="text-right">
                            <flux:badge 
                                size="sm"
                                :class="match($attendance->status) {
                                    'present' => 'text-green-700 bg-green-100 dark:text-green-400 dark:bg-green-900/20',
                                    'late' => 'text-yellow-700 bg-yellow-100 dark:text-yellow-400 dark:bg-yellow-900/20',
                                    'absent' => 'text-red-700 bg-red-100 dark:text-red-400 dark:bg-red-900/20',
                                    'excused' => 'text-blue-700 bg-blue-100 dark:text-blue-400 dark:bg-blue-900/20',
                                    default => 'text-gray-700 bg-gray-100 dark:text-gray-400 dark:bg-gray-900/20'
                                }"
                            >
                                {{ ucfirst($attendance->status) }}
                            </flux:badge>
                            @if($attendance->checked_in_at)
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {{ $attendance->checked_in_at->format('g:i A') }}
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 flex justify-end">
                <flux:button 
                    wire:click="closeAttendanceViewModal"
                    variant="outline"
                >
                    Close
                </flux:button>
            </div>
        @endif
    </flux:modal>
</div>

<script>
function sessionTimer(startedAtISO) {
    return {
        startedAt: new Date(startedAtISO),
        formattedTime: '',
        interval: null,
        
        init() {
            this.updateFormattedTime();
        },
        
        startTimer() {
            this.updateFormattedTime();
            this.interval = setInterval(() => {
                this.updateFormattedTime();
            }, 1000);
        },
        
        stopTimer() {
            if (this.interval) {
                clearInterval(this.interval);
                this.interval = null;
            }
        },
        
        updateFormattedTime() {
            const now = new Date();
            const diffInSeconds = Math.floor((now - this.startedAt) / 1000);
            
            // Ensure we don't show negative time
            const seconds = Math.max(0, diffInSeconds);
            
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;
            
            if (hours > 0) {
                this.formattedTime = `${hours}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
            } else {
                this.formattedTime = `${minutes}:${String(secs).padStart(2, '0')}`;
            }
        }
    }
}
</script>