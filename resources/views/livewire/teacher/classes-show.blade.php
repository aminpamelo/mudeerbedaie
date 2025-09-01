<?php

use App\Models\ClassModel;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('components.layouts.teacher')] class extends Component {
    public ClassModel $class;

    public function mount(ClassModel $class): void
    {
        // Ensure this class belongs to the current teacher
        $teacher = auth()->user()->teacher;
        if (!$teacher || $class->teacher_id !== $teacher->id) {
            abort(403, 'You are not authorized to view this class.');
        }

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
        return $this->class->activeStudents()->count();
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
    
    // Session management properties
    public $showSessionModal = false;
    public $showCompletionModal = false;
    public $showAttendanceViewModal = false;
    public $currentSession = null;
    public $completingSession = null;
    public $viewingSession = null;
    public $completionBookmark = '';

    public function markSessionAsOngoing($sessionId): void
    {
        $session = \App\Models\ClassSession::find($sessionId);
        if ($session && $session->isScheduled()) {
            $session->markAsOngoing();
            
            // Refresh the class data
            $this->class->refresh();
            $this->class->load([
                'course',
                'teacher.user',
                'sessions.attendances.student.user',
                'activeStudents.student.user'
            ]);
            
            session()->flash('success', 'Session started successfully.');
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
            
            // Refresh the class data
            $this->class->refresh();
            $this->class->load([
                'course',
                'teacher.user',
                'sessions.attendances.student.user',
                'activeStudents.student.user'
            ]);
        }
    }

    public function markSessionAsCancelled($sessionId): void
    {
        $session = \App\Models\ClassSession::find($sessionId);
        if ($session && ($session->isScheduled() || $session->isOngoing())) {
            $session->cancel();
            session()->flash('success', 'Session cancelled.');
            
            // Refresh the class data
            $this->class->refresh();
            $this->class->load([
                'course',
                'teacher.user',
                'sessions.attendances.student.user',
                'activeStudents.student.user'
            ]);
        }
    }

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

    public function setActiveTab($tab): void
    {
        $this->activeTab = $tab;
    }
}; ?>

<div class="space-y-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $class->title }}</flux:heading>
            <flux:text class="mt-2">{{ $class->course->name }} - Class Management</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button variant="ghost" href="{{ route('teacher.classes.index') }}">
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
                    <flux:icon name="document-text" class="h-4 w-4" />
                    Overview
                </div>
            </button>
            
            <button 
                wire:click="setActiveTab('sessions')"
                class="whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'sessions' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
            >
                <div class="flex items-center gap-2">
                    <flux:icon name="calendar" class="h-4 w-4" />
                    Sessions
                </div>
            </button>

            <button 
                wire:click="setActiveTab('students')"
                class="whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'students' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
            >
                <div class="flex items-center gap-2">
                    <flux:icon name="users" class="h-4 w-4" />
                    Students ({{ $this->enrolled_students_count }})
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
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $class->course->name }}</dd>
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Duration</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $class->formatted_duration }}</dd>
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Type</dt>
                                    <dd class="mt-1">
                                        <div class="flex items-center gap-2">
                                            @if($class->isIndividual())
                                                <flux:icon name="user" class="h-4 w-4 text-blue-500" />
                                                <span class="text-sm text-gray-900 dark:text-gray-100">Individual</span>
                                            @else
                                                <flux:icon name="users" class="h-4 w-4 text-green-500" />
                                                <span class="text-sm text-gray-900 dark:text-gray-100">Group</span>
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
                                        @if($class->status === 'active')
                                            <flux:badge color="green" size="sm">Active</flux:badge>
                                        @elseif($class->status === 'draft')
                                            <flux:badge color="gray" size="sm">Draft</flux:badge>
                                        @elseif($class->status === 'completed')
                                            <flux:badge color="blue" size="sm">Completed</flux:badge>
                                        @elseif($class->status === 'suspended')
                                            <flux:badge color="yellow" size="sm">Suspended</flux:badge>
                                        @else
                                            <flux:badge color="red" size="sm">{{ ucfirst($class->status) }}</flux:badge>
                                        @endif
                                    </dd>
                                </div>

                                @if($class->location)
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">Location</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $class->location }}</dd>
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
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $class->description }}</dd>
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
                                    @if($this->upcoming_sessions_count > 0)
                                        <div>
                                            <div class="text-xs font-medium text-gray-500 mb-2">Next Session</div>
                                            @php
                                                $nextSession = $class->sessions->where('status', 'scheduled')->where('session_date', '>', now()->toDateString())->sortBy('session_date')->first();
                                            @endphp
                                            @if($nextSession)
                                                <div class="text-sm text-gray-700 dark:text-gray-300 mb-2">
                                                    {{ $nextSession->formatted_date_time }}
                                                </div>
                                                <flux:button 
                                                    wire:click="markSessionAsOngoing({{ $nextSession->id }})"
                                                    variant="primary"
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
                                            <div class="text-sm text-gray-700 dark:text-gray-300 mb-2">
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
                                                        <flux:icon name="bookmark" class="h-3 w-3 text-amber-600" />
                                                        <span class="text-xs font-medium text-amber-800 dark:text-amber-200">Current Progress:</span>
                                                    </div>
                                                    <div class="text-sm text-amber-900 dark:text-amber-100 font-medium">
                                                        {{ $ongoingSession->bookmark }}
                                                    </div>
                                                </div>
                                            @endif
                                            
                                            <div class="flex gap-2">
                                                <flux:button 
                                                    wire:click="openSessionModal({{ $ongoingSession->id }})"
                                                    variant="ghost"
                                                    size="sm"
                                                    class="flex-1"
                                                    icon="users"
                                                >
                                                    Manage
                                                </flux:button>
                                                <flux:button 
                                                    wire:click="openCompletionModal({{ $ongoingSession->id }})"
                                                    variant="primary"
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
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-900">
                                    @php $hasAnySessions = count($this->sessions_by_month) > 0; @endphp
                                    @if($hasAnySessions)
                                        @foreach($this->sessions_by_month as $monthData)
                                            <!-- Month Header Row -->
                                            <tr class="bg-gray-50 dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600">
                                                <td colspan="6" class="px-6 py-3">
                                                    <div class="flex items-center justify-between">
                                                        <div class="flex items-center gap-3">
                                                            <flux:icon name="calendar" class="h-5 w-5 text-gray-500" />
                                                            <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $monthData['month_name'] }} {{ $monthData['year'] }}</span>
                                                            <flux:badge color="gray" size="sm">{{ $monthData['stats']['total'] }} sessions</flux:badge>
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
                                                            @if($session->status === 'completed')
                                                                <flux:badge color="green" size="sm">Completed</flux:badge>
                                                            @elseif($session->status === 'scheduled')
                                                                <flux:badge color="blue" size="sm">Scheduled</flux:badge>
                                                            @elseif($session->status === 'cancelled')
                                                                <flux:badge color="red" size="sm">Cancelled</flux:badge>
                                                            @elseif($session->status === 'no_show')
                                                                <flux:badge color="orange" size="sm">No Show</flux:badge>
                                                            @else
                                                                <flux:badge color="gray" size="sm">{{ ucfirst($session->status) }}</flux:badge>
                                                            @endif
                                                        @endif
                                                    </td>
                                                    
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        @if($session->hasBookmark())
                                                            <div class="flex items-center gap-2 group" title="{{ $session->bookmark }}">
                                                                <flux:icon name="bookmark" class="h-4 w-4 text-amber-500" />
                                                                <span class="text-gray-900 dark:text-gray-100">{{ $session->formatted_bookmark }}</span>
                                                            </div>
                                                        @else
                                                            <span class="text-gray-400">—</span>
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
                                                                
                                                            @elseif($session->isCompleted())
                                                                <flux:button 
                                                                    wire:click="openAttendanceViewModal({{ $session->id }})"
                                                                    variant="ghost" 
                                                                    size="sm" 
                                                                    icon="eye"
                                                                    class="text-blue-600 hover:text-blue-800"
                                                                >
                                                                    View
                                                                </flux:button>
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="6" class="px-6 py-12 text-center">
                                                <div class="text-gray-500">
                                                    <flux:icon name="calendar" class="mx-auto h-8 w-8 text-gray-400 mb-4" />
                                                    <p>No sessions scheduled yet</p>
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
                <!-- No Sessions - Show message -->
                <flux:card>
                    <div class="p-6 text-center">
                        <flux:icon name="calendar" class="mx-auto h-12 w-12 text-gray-400 mb-4" />
                        <flux:heading size="lg" class="mb-2">No Sessions Scheduled</flux:heading>
                        <flux:text class="mb-4">This class doesn't have any sessions yet. Contact your administrator to schedule sessions.</flux:text>
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
                                                <flux:badge color="green" size="sm">
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
            @else
                <!-- No Students Enrolled -->
                <flux:card>
                    <div class="p-6 text-center">
                        <flux:icon name="users" class="mx-auto h-12 w-12 text-gray-400 mb-4" />
                        <flux:heading size="lg" class="mb-2">No Students Enrolled</flux:heading>
                        <flux:text class="mb-4">This class doesn't have any students enrolled yet.</flux:text>
                    </div>
                </flux:card>
            @endif
        </div>
        <!-- End Overview Tab -->

        <!-- Sessions Tab -->
        <div class="{{ $activeTab === 'sessions' ? 'block' : 'hidden' }}">
            <!-- Session Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <flux:card class="p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $this->total_sessions_count }}</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Total Sessions</div>
                        </div>
                        <flux:icon name="calendar" class="h-8 w-8 text-blue-500" />
                    </div>
                </flux:card>
                
                <flux:card class="p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $this->completed_sessions_count }}</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Completed</div>
                        </div>
                        <flux:icon name="check-circle" class="h-8 w-8 text-green-500" />
                    </div>
                </flux:card>
                
                <flux:card class="p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $this->upcoming_sessions_count }}</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Upcoming</div>
                        </div>
                        <flux:icon name="clock" class="h-8 w-8 text-blue-500" />
                    </div>
                </flux:card>
                
                <flux:card class="p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">
                                {{ $class->sessions->where('status', 'ongoing')->count() }}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Active Now</div>
                        </div>
                        <flux:icon name="play" class="h-8 w-8 text-yellow-500" />
                    </div>
                </flux:card>
            </div>

            <!-- Quick Actions -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- Quick Start Session -->
                @if($this->upcoming_sessions_count > 0)
                    <flux:card class="p-6">
                        <div class="flex items-center gap-3 mb-4">
                            <flux:icon name="play" class="h-6 w-6 text-green-600" />
                            <flux:heading size="lg">Quick Start</flux:heading>
                        </div>
                        @php
                            $nextSession = $class->sessions->where('status', 'scheduled')->where('session_date', '>', now()->toDateString())->sortBy('session_date')->first();
                        @endphp
                        @if($nextSession)
                            <div class="space-y-3">
                                <div>
                                    <div class="text-sm text-gray-500">Next Session</div>
                                    <div class="font-medium">{{ $nextSession->formatted_date_time }}</div>
                                </div>
                                <flux:button 
                                    wire:click="markSessionAsOngoing({{ $nextSession->id }})"
                                    variant="primary"
                                    class="w-full"
                                    icon="play"
                                >
                                    Start Session Now
                                </flux:button>
                            </div>
                        @endif
                    </flux:card>
                @endif

                <!-- Active Session Management -->
                @php $ongoingSession = $class->sessions->where('status', 'ongoing')->first(); @endphp
                @if($ongoingSession)
                    <flux:card class="p-6 border-l-4 border-yellow-500">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-3 h-3 bg-yellow-500 rounded-full animate-pulse"></div>
                            <flux:heading size="lg">Session Running</flux:heading>
                        </div>
                        
                        <div class="space-y-4">
                            <div>
                                <div class="text-sm text-gray-500">Started</div>
                                <div class="font-medium">{{ $ongoingSession->formatted_date_time }}</div>
                            </div>
                            
                            <div 
                                x-data="sessionTimer('{{ $ongoingSession->started_at ? $ongoingSession->started_at->toISOString() : now()->toISOString() }}')"
                                x-init="startTimer()"
                                class="p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded border border-yellow-200 dark:border-yellow-800"
                            >
                                <div class="text-sm text-yellow-800 dark:text-yellow-200 mb-1">Duration</div>
                                <div class="text-xl font-mono font-bold text-yellow-900 dark:text-yellow-100" x-text="formattedTime"></div>
                            </div>
                            
                            @if($ongoingSession->hasBookmark())
                                <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded border border-blue-200 dark:border-blue-800">
                                    <div class="text-sm text-blue-800 dark:text-blue-200 mb-1">Current Progress</div>
                                    <div class="text-sm text-blue-900 dark:text-blue-100">{{ $ongoingSession->formatted_bookmark }}</div>
                                </div>
                            @endif
                            
                            <div class="flex gap-2">
                                <flux:button 
                                    wire:click="openSessionModal({{ $ongoingSession->id }})"
                                    variant="outline"
                                    class="flex-1"
                                    icon="users"
                                >
                                    Manage Attendance
                                </flux:button>
                                <flux:button 
                                    wire:click="openCompletionModal({{ $ongoingSession->id }})"
                                    variant="primary"
                                    class="flex-1"
                                    icon="check"
                                >
                                    Complete Session
                                </flux:button>
                            </div>
                        </div>
                    </flux:card>
                @endif
            </div>

            <!-- Sessions Calendar/List View -->
            @if($this->total_sessions_count > 0)
                <flux:card>
                    <div class="overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                            <flux:heading size="lg">Session Calendar</flux:heading>
                            <div class="text-sm text-gray-500">
                                {{ $this->total_sessions_count }} total sessions
                            </div>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date & Time</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Duration</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Progress</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Attendance</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-900">
                                    @php $hasAnySessions = count($this->sessions_by_month) > 0; @endphp
                                    @if($hasAnySessions)
                                        @foreach($this->sessions_by_month as $monthData)
                                            <!-- Month Header Row -->
                                            <tr class="bg-gray-50 dark:bg-gray-800 border-t-2 border-gray-300 dark:border-gray-600">
                                                <td colspan="6" class="px-6 py-4">
                                                    <div class="flex items-center justify-between">
                                                        <div class="flex items-center gap-3">
                                                            <flux:icon name="calendar" class="h-5 w-5 text-gray-500" />
                                                            <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $monthData['month_name'] }} {{ $monthData['year'] }}</span>
                                                            <flux:badge color="gray" size="sm">{{ $monthData['stats']['total'] }} sessions</flux:badge>
                                                        </div>
                                                        <div class="flex gap-2 text-sm">
                                                            @if($monthData['stats']['completed'] > 0)
                                                                <flux:badge color="green" size="sm">{{ $monthData['stats']['completed'] }} completed</flux:badge>
                                                            @endif
                                                            @if($monthData['stats']['upcoming'] > 0)
                                                                <flux:badge color="blue" size="sm">{{ $monthData['stats']['upcoming'] }} upcoming</flux:badge>
                                                            @endif
                                                            @if($monthData['stats']['ongoing'] > 0)
                                                                <flux:badge color="yellow" size="sm">{{ $monthData['stats']['ongoing'] }} running</flux:badge>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            
                                            <!-- Sessions for this month -->
                                            @foreach($monthData['sessions'] as $session)
                                                <tr class="divide-y divide-gray-200 dark:divide-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/50 {{ $session->isOngoing() ? 'bg-yellow-50 dark:bg-yellow-900/10' : '' }}">
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                            {{ $session->formatted_date_time }}
                                                        </div>
                                                        @if($session->isOngoing())
                                                            <div class="text-xs text-yellow-700 dark:text-yellow-300 mt-1 flex items-center gap-1">
                                                                <div class="w-1.5 h-1.5 bg-yellow-500 rounded-full animate-pulse"></div>
                                                                Active session
                                                            </div>
                                                        @endif
                                                    </td>
                                                    
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        {{ $session->formatted_duration }}
                                                        @if($session->isOngoing())
                                                            <div 
                                                                x-data="sessionTimer('{{ $session->started_at ? $session->started_at->toISOString() : now()->toISOString() }}')"
                                                                x-init="startTimer()"
                                                                class="text-xs text-yellow-700 dark:text-yellow-300 font-mono mt-1"
                                                            >
                                                                <span x-text="formattedTime"></span>
                                                            </div>
                                                        @endif
                                                    </td>
                                                    
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        @if($session->isOngoing())
                                                            <div class="flex items-center gap-2">
                                                                <div class="w-2 h-2 bg-yellow-500 rounded-full animate-pulse"></div>
                                                                <span class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Running</span>
                                                            </div>
                                                        @else
                                                            @if($session->status === 'completed')
                                                                <flux:badge color="green" size="sm">Completed</flux:badge>
                                                            @elseif($session->status === 'scheduled')
                                                                <flux:badge color="blue" size="sm">Scheduled</flux:badge>
                                                            @elseif($session->status === 'cancelled')
                                                                <flux:badge color="red" size="sm">Cancelled</flux:badge>
                                                            @elseif($session->status === 'no_show')
                                                                <flux:badge color="orange" size="sm">No Show</flux:badge>
                                                            @else
                                                                <flux:badge color="gray" size="sm">{{ ucfirst($session->status) }}</flux:badge>
                                                            @endif
                                                        @endif
                                                    </td>
                                                    
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        @if($session->hasBookmark())
                                                            <div class="flex items-center gap-2 group max-w-xs" title="{{ $session->bookmark }}">
                                                                <flux:icon name="bookmark" class="h-4 w-4 text-amber-500 flex-shrink-0" />
                                                                <span class="text-gray-900 dark:text-gray-100 truncate">{{ $session->formatted_bookmark }}</span>
                                                            </div>
                                                        @else
                                                            <span class="text-gray-400">No progress notes</span>
                                                        @endif
                                                    </td>
                                                    
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        @php
                                                            $presentCount = $session->attendances->where('status', 'present')->count();
                                                            $totalCount = $session->attendances->count();
                                                            $attendanceRate = $totalCount > 0 ? round(($presentCount / $totalCount) * 100) : 0;
                                                        @endphp
                                                        
                                                        @if($totalCount == 0)
                                                            <span class="text-gray-400">Not recorded</span>
                                                        @elseif($totalCount == 1)
                                                            @if($presentCount == 1)
                                                                <div class="flex items-center gap-1 text-green-600">
                                                                    <flux:icon name="check" class="h-4 w-4" />
                                                                    <span class="text-sm font-medium">Present</span>
                                                                </div>
                                                            @else
                                                                <div class="flex items-center gap-1 text-red-600">
                                                                    <flux:icon name="x-mark" class="h-4 w-4" />
                                                                    <span class="text-sm font-medium">Absent</span>
                                                                </div>
                                                            @endif
                                                        @else
                                                            <div class="flex items-center gap-3">
                                                                <div class="flex items-center gap-1">
                                                                    <span class="text-sm font-semibold {{ $attendanceRate >= 80 ? 'text-green-600' : ($attendanceRate >= 60 ? 'text-amber-600' : 'text-red-600') }}">
                                                                        {{ $presentCount }}/{{ $totalCount }}
                                                                    </span>
                                                                </div>
                                                                <div class="flex-1 w-16 h-2 bg-gray-200 rounded-full overflow-hidden">
                                                                    <div class="h-full rounded-full {{ $attendanceRate >= 80 ? 'bg-green-500' : ($attendanceRate >= 60 ? 'bg-amber-500' : 'bg-red-500') }}" 
                                                                         style="width: {{ $attendanceRate }}%"></div>
                                                                </div>
                                                                <span class="text-xs text-gray-400">{{ $attendanceRate }}%</span>
                                                            </div>
                                                        @endif
                                                    </td>
                                                    
                                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                                        <div class="flex items-center justify-end gap-2">
                                                            @if($session->isScheduled())
                                                                <flux:button 
                                                                    wire:click="markSessionAsOngoing({{ $session->id }})"
                                                                    variant="primary" 
                                                                    size="sm" 
                                                                    icon="play"
                                                                >
                                                                    Start
                                                                </flux:button>
                                                                
                                                            @elseif($session->isOngoing())
                                                                <flux:button 
                                                                    wire:click="openSessionModal({{ $session->id }})"
                                                                    variant="outline" 
                                                                    size="sm" 
                                                                    icon="users"
                                                                >
                                                                    Manage
                                                                </flux:button>
                                                                
                                                                <flux:button 
                                                                    wire:click="openCompletionModal({{ $session->id }})"
                                                                    variant="primary" 
                                                                    size="sm" 
                                                                    icon="check"
                                                                >
                                                                    Complete
                                                                </flux:button>
                                                                
                                                            @elseif($session->isCompleted())
                                                                <flux:button 
                                                                    wire:click="openAttendanceViewModal({{ $session->id }})"
                                                                    variant="ghost" 
                                                                    size="sm" 
                                                                    icon="eye"
                                                                >
                                                                    View Details
                                                                </flux:button>
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="6" class="px-6 py-12 text-center">
                                                <div class="text-gray-500">
                                                    <flux:icon name="calendar" class="mx-auto h-8 w-8 text-gray-400 mb-4" />
                                                    <p class="text-lg font-medium mb-2">No sessions scheduled yet</p>
                                                    <p class="text-sm">Contact your administrator to schedule sessions for this class</p>
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
                <!-- No Sessions - Show message -->
                <flux:card>
                    <div class="p-12 text-center">
                        <flux:icon name="calendar" class="mx-auto h-16 w-16 text-gray-400 mb-6" />
                        <flux:heading size="xl" class="mb-4">No Sessions Scheduled</flux:heading>
                        <flux:text class="text-lg mb-6 text-gray-600">This class doesn't have any sessions yet. Contact your administrator to schedule sessions.</flux:text>
                        <div class="max-w-md mx-auto p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                            <flux:text size="sm" class="text-blue-800 dark:text-blue-200">
                                💡 <strong>Tip:</strong> Sessions are where you'll conduct your classes, track attendance, and monitor progress.
                            </flux:text>
                        </div>
                    </div>
                </flux:card>
            @endif
        </div>
        <!-- End Sessions Tab -->

        <!-- Students Tab -->
        <div class="{{ $activeTab === 'students' ? 'block' : 'hidden' }}">
            <!-- Student Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <flux:card class="p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $this->enrolled_students_count }}</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Total Students</div>
                        </div>
                        <flux:icon name="users" class="h-8 w-8 text-blue-500" />
                    </div>
                </flux:card>
                
                <flux:card class="p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $this->overall_attendance_rate }}%</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Avg. Attendance</div>
                        </div>
                        <flux:icon name="chart-bar" class="h-8 w-8 text-green-500" />
                    </div>
                </flux:card>
                
                <flux:card class="p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $this->total_present_count }}</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Total Present</div>
                        </div>
                        <flux:icon name="check-circle" class="h-8 w-8 text-emerald-500" />
                    </div>
                </flux:card>
                
                <flux:card class="p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $this->total_absent_count }}</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Total Absent</div>
                        </div>
                        <flux:icon name="x-circle" class="h-8 w-8 text-red-500" />
                    </div>
                </flux:card>
            </div>

            @if($class->activeStudents->count() > 0)
                <!-- Class Capacity Info -->
                @if($class->max_capacity)
                    <div class="mb-6">
                        <flux:card class="p-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <flux:icon name="users" class="h-5 w-5 text-blue-600" />
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-gray-100">Class Capacity</div>
                                        <div class="text-sm text-gray-500">{{ $this->enrolled_students_count }} of {{ $class->max_capacity }} students enrolled</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="w-32 h-2 bg-gray-200 rounded-full overflow-hidden">
                                        <div class="h-full bg-blue-500 rounded-full" style="width: {{ ($this->enrolled_students_count / $class->max_capacity) * 100 }}%"></div>
                                    </div>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ round(($this->enrolled_students_count / $class->max_capacity) * 100, 1) }}%</span>
                                </div>
                            </div>
                        </flux:card>
                    </div>
                @endif

                <!-- Students List -->
                <flux:card>
                    <div class="overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                            <div>
                                <flux:heading size="lg">Student Performance</flux:heading>
                                <flux:text size="sm" class="text-gray-500">
                                    Individual attendance tracking and performance overview
                                </flux:text>
                            </div>
                            <div class="text-sm text-gray-500">
                                {{ $class->activeStudents->count() }} student(s) enrolled
                            </div>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Student</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Enrollment Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Sessions Attended</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Attendance Rate</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Performance</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($class->activeStudents->sortBy('student.user.name') as $classStudent)
                                        @php
                                            $student = $classStudent->student;
                                            $completedSessions = $this->completed_sessions_count;
                                            
                                            // Calculate detailed attendance for this student across all sessions
                                            $studentAttendances = collect();
                                            foreach($class->sessions as $session) {
                                                $attendance = $session->attendances->where('student_id', $student->id)->first();
                                                if($attendance) {
                                                    $studentAttendances->push($attendance);
                                                }
                                            }
                                            
                                            $presentCount = $studentAttendances->where('status', 'present')->count();
                                            $lateCount = $studentAttendances->where('status', 'late')->count();
                                            $absentCount = $studentAttendances->where('status', 'absent')->count();
                                            $excusedCount = $studentAttendances->where('status', 'excused')->count();
                                            $totalRecords = $studentAttendances->count();
                                            $attendanceRate = $totalRecords > 0 ? round(($presentCount / $totalRecords) * 100, 1) : 0;
                                            
                                            // Performance indicators
                                            $performanceColor = $attendanceRate >= 90 ? 'text-green-600' : ($attendanceRate >= 80 ? 'text-yellow-600' : ($attendanceRate >= 70 ? 'text-orange-600' : 'text-red-600'));
                                            $performanceText = $attendanceRate >= 90 ? 'Excellent' : ($attendanceRate >= 80 ? 'Good' : ($attendanceRate >= 70 ? 'Needs Improvement' : 'Poor'));
                                        @endphp
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center gap-3">
                                                    <flux:avatar size="md" :name="$student->fullName" />
                                                    <div>
                                                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ $student->fullName }}</div>
                                                        <div class="text-sm text-gray-500 dark:text-gray-400">ID: {{ $student->student_id }}</div>
                                                        @if($student->user->email)
                                                            <div class="text-xs text-gray-400">{{ $student->user->email }}</div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <div class="flex items-center gap-2">
                                                    <flux:icon name="calendar-days" class="h-4 w-4 text-gray-400" />
                                                    <div>
                                                        <div class="font-medium">{{ $classStudent->enrolled_at->format('M d, Y') }}</div>
                                                        <div class="text-xs text-gray-400">
                                                            {{ $classStudent->enrolled_at->diffForHumans() }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <div class="space-y-2">
                                                    <div class="flex items-center gap-2">
                                                        <span class="font-medium text-green-600">{{ $presentCount }}</span>
                                                        <span class="text-gray-400">/</span>
                                                        <span class="text-gray-600">{{ $totalRecords }}</span>
                                                        <span class="text-gray-400">sessions</span>
                                                    </div>
                                                    
                                                    @if($totalRecords > 0)
                                                        <div class="flex gap-2 text-xs">
                                                            @if($lateCount > 0)
                                                                <span class="text-yellow-600">{{ $lateCount }} late</span>
                                                            @endif
                                                            @if($absentCount > 0)
                                                                <span class="text-red-600">{{ $absentCount }} absent</span>
                                                            @endif
                                                            @if($excusedCount > 0)
                                                                <span class="text-blue-600">{{ $excusedCount }} excused</span>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                            </td>
                                            
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                @if($totalRecords > 0)
                                                    <div class="flex items-center gap-3">
                                                        <div class="flex items-center gap-2">
                                                            <span class="font-bold {{ $attendanceRate >= 80 ? 'text-green-600' : ($attendanceRate >= 60 ? 'text-yellow-600' : 'text-red-600') }}">
                                                                {{ $attendanceRate }}%
                                                            </span>
                                                        </div>
                                                        <div class="flex-1">
                                                            <div class="w-20 bg-gray-200 rounded-full h-2.5">
                                                                <div class="h-2.5 rounded-full {{ $attendanceRate >= 80 ? 'bg-green-500' : ($attendanceRate >= 60 ? 'bg-yellow-500' : 'bg-red-500') }}" 
                                                                     style="width: {{ $attendanceRate }}%"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @else
                                                    <span class="text-gray-400">No attendance data</span>
                                                @endif
                                            </td>
                                            
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                @if($totalRecords > 0)
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-2 h-2 rounded-full {{ $attendanceRate >= 90 ? 'bg-green-500' : ($attendanceRate >= 80 ? 'bg-yellow-500' : ($attendanceRate >= 70 ? 'bg-orange-500' : 'bg-red-500')) }}"></div>
                                                        <span class="font-medium {{ $performanceColor }}">{{ $performanceText }}</span>
                                                    </div>
                                                    <div class="text-xs text-gray-400 mt-1">
                                                        @if($attendanceRate >= 90)
                                                            Outstanding attendance record
                                                        @elseif($attendanceRate >= 80)
                                                            Consistent attendance
                                                        @elseif($attendanceRate >= 70)
                                                            Some absences noted
                                                        @else
                                                            Frequent absences
                                                        @endif
                                                    </div>
                                                @else
                                                    <span class="text-gray-400">No data</span>
                                                @endif
                                            </td>
                                            
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <flux:badge color="green" size="sm">
                                                    Active
                                                </flux:badge>
                                                <div class="text-xs text-gray-400 mt-1">
                                                    Since {{ $classStudent->enrolled_at->format('M Y') }}
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </flux:card>

                <!-- Attendance Summary Charts -->
                @if($this->total_attendance_records > 0)
                    <div class="mt-6">
                        <flux:card>
                            <div class="p-6">
                                <flux:heading size="lg" class="mb-4">Class Attendance Summary</flux:heading>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                    <!-- Present Attendance -->
                                    <div class="text-center p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                                        <div class="text-3xl font-bold text-green-600 mb-2">{{ $this->total_present_count }}</div>
                                        <div class="text-sm text-green-800 dark:text-green-200 font-medium">Present Records</div>
                                        <div class="text-xs text-green-700 dark:text-green-300 mt-1">
                                            {{ round(($this->total_present_count / $this->total_attendance_records) * 100, 1) }}% of all records
                                        </div>
                                    </div>
                                    
                                    <!-- Late Attendance -->
                                    <div class="text-center p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                                        <div class="text-3xl font-bold text-yellow-600 mb-2">{{ $this->total_late_count }}</div>
                                        <div class="text-sm text-yellow-800 dark:text-yellow-200 font-medium">Late Records</div>
                                        <div class="text-xs text-yellow-700 dark:text-yellow-300 mt-1">
                                            {{ $this->total_attendance_records > 0 ? round(($this->total_late_count / $this->total_attendance_records) * 100, 1) : 0 }}% of all records
                                        </div>
                                    </div>
                                    
                                    <!-- Absent Records -->
                                    <div class="text-center p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
                                        <div class="text-3xl font-bold text-red-600 mb-2">{{ $this->total_absent_count }}</div>
                                        <div class="text-sm text-red-800 dark:text-red-200 font-medium">Absent Records</div>
                                        <div class="text-xs text-red-700 dark:text-red-300 mt-1">
                                            {{ round(($this->total_absent_count / $this->total_attendance_records) * 100, 1) }}% of all records
                                        </div>
                                    </div>
                                    
                                    <!-- Excused Records -->
                                    <div class="text-center p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                                        <div class="text-3xl font-bold text-blue-600 mb-2">{{ $this->total_excused_count }}</div>
                                        <div class="text-sm text-blue-800 dark:text-blue-200 font-medium">Excused Records</div>
                                        <div class="text-xs text-blue-700 dark:text-blue-300 mt-1">
                                            {{ $this->total_attendance_records > 0 ? round(($this->total_excused_count / $this->total_attendance_records) * 100, 1) : 0 }}% of all records
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Overall Class Performance -->
                                <div class="mt-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="font-medium text-gray-900 dark:text-gray-100">Overall Class Performance</div>
                                        <div class="text-xl font-bold text-blue-600">{{ $this->overall_attendance_rate }}%</div>
                                    </div>
                                    
                                    <div class="w-full bg-gray-200 rounded-full h-3">
                                        <div class="bg-gradient-to-r from-green-500 to-blue-500 h-3 rounded-full transition-all duration-500" 
                                             style="width: {{ $this->overall_attendance_rate }}%"></div>
                                    </div>
                                    
                                    <div class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                        Based on {{ $this->total_attendance_records }} attendance records across {{ $this->completed_sessions_count }} completed sessions
                                    </div>
                                </div>
                            </div>
                        </flux:card>
                    </div>
                @endif
            @else
                <!-- No Students Enrolled -->
                <flux:card>
                    <div class="p-12 text-center">
                        <flux:icon name="users" class="mx-auto h-16 w-16 text-gray-400 mb-6" />
                        <flux:heading size="xl" class="mb-4">No Students Enrolled</flux:heading>
                        <flux:text class="text-lg mb-6 text-gray-600">This class doesn't have any students enrolled yet.</flux:text>
                        
                        <div class="max-w-md mx-auto space-y-4">
                            <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                                <flux:text size="sm" class="text-blue-800 dark:text-blue-200">
                                    📚 <strong>Class Type:</strong> {{ ucfirst($class->class_type) }} 
                                    @if($class->max_capacity)
                                        (Max {{ $class->max_capacity }} students)
                                    @endif
                                </flux:text>
                            </div>
                            
                            <div class="p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
                                <flux:text size="sm" class="text-green-800 dark:text-green-200">
                                    📈 <strong>Ready to teach:</strong> Once students are enrolled, you'll be able to track their attendance and monitor their progress here.
                                </flux:text>
                            </div>
                        </div>
                    </div>
                </flux:card>
            @endif
        </div>
        <!-- End Students Tab -->
    </div>

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
                    <flux:icon name="bookmark" class="h-5 w-5 text-amber-600" />
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
                                    variant="{{ $attendance->status === $status ? 'primary' : 'ghost' }}"
                                    size="sm"
                                    class="{{ match($status) {
                                        'present' => $attendance->status === $status ? '' : 'text-green-600 border-green-600 hover:bg-green-50',
                                        'late' => $attendance->status === $status ? '' : 'text-yellow-600 border-yellow-600 hover:bg-yellow-50',
                                        'absent' => $attendance->status === $status ? '' : 'text-red-600 border-red-600 hover:bg-red-50',
                                        'excused' => $attendance->status === $status ? '' : 'text-blue-600 border-blue-600 hover:bg-blue-50',
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
                            <flux:icon name="check-circle" class="h-4 w-4" />
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
                        <flux:icon name="bookmark" class="h-5 w-5 text-blue-600 mt-0.5 flex-shrink-0" />
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
                            @if($attendance->status === 'present')
                                <flux:badge color="green" size="sm">Present</flux:badge>
                            @elseif($attendance->status === 'late')
                                <flux:badge color="yellow" size="sm">Late</flux:badge>
                            @elseif($attendance->status === 'absent')
                                <flux:badge color="red" size="sm">Absent</flux:badge>
                            @elseif($attendance->status === 'excused')
                                <flux:badge color="blue" size="sm">Excused</flux:badge>
                            @else
                                <flux:badge color="gray" size="sm">{{ ucfirst($attendance->status) }}</flux:badge>
                            @endif
                            
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