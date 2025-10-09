<?php

use App\Models\ClassSession;
use App\Models\ClassAttendance;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('components.layouts.teacher')] class extends Component {
    public bool $showModal = false;
    public ?ClassSession $selectedSession = null;
    public string $completionNotes = '';
    public bool $showNotesField = false;
    public bool $showStartConfirmation = false;
    public ?int $sessionToStartId = null;
    public ?int $classToStartId = null;
    public ?string $timeToStart = null;

    public function mount(): void
    {
        if (!auth()->user()->isTeacher()) {
            abort(403, 'Unauthorized access');
        }
    }

    public function selectSession(ClassSession $session)
    {
        $this->selectedSession = $session;
        $this->completionNotes = $session->teacher_notes ?? '';
        $this->showNotesField = false;
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->selectedSession = null;
        $this->completionNotes = '';
        $this->showNotesField = false;
    }

    public function showCompleteSessionForm()
    {
        $this->showNotesField = true;
    }

    public function closeStartConfirmation()
    {
        $this->showStartConfirmation = false;
        $this->sessionToStartId = null;
        $this->classToStartId = null;
        $this->timeToStart = null;
    }

    public function requestStartSession($sessionId)
    {
        $this->sessionToStartId = $sessionId;
        $this->showStartConfirmation = true;
    }

    public function requestStartSessionFromScheduledSlot($classId, $time)
    {
        $this->classToStartId = $classId;
        $this->timeToStart = $time;
        $this->showStartConfirmation = true;
    }

    public function confirmStartSession()
    {
        if ($this->sessionToStartId) {
            // Start existing session
            $session = ClassSession::findOrFail($this->sessionToStartId);
            $this->startSession($session->id);
        } elseif ($this->classToStartId && $this->timeToStart) {
            // Start session from scheduled slot
            $this->startSessionFromScheduledSlot($this->classToStartId, $this->timeToStart);
        }

        $this->closeStartConfirmation();
    }

    // Today's sessions for the teacher (including scheduled slots from timetables)
    public function getTodaySessionsProperty()
    {
        $teacher = auth()->user()->teacher;

        if (!$teacher) {
            return collect();
        }

        $today = now();
        $dayName = strtolower($today->format('l')); // e.g., 'monday', 'tuesday'

        // Get existing sessions for today
        $existingSessions = $teacher->classes()
            ->with(['sessions' => function($query) {
                $query->today()->with(['class.course', 'attendances.student.user']);
            }])
            ->get()
            ->flatMap->sessions;

        // Get all classes with their timetables
        $classes = $teacher->classes()->with(['course', 'timetable'])->get();

        // Create collection to hold combined items
        $combinedSessions = collect();

        // Add existing sessions
        foreach($existingSessions as $session) {
            $combinedSessions->push([
                'type' => 'session',
                'session' => $session,
                'class' => $session->class,
                'time' => $session->session_time->format('H:i'),
                'display_time' => $session->session_time->format('g:i A'),
                'duration' => $session->duration_minutes,
                'status' => $session->status,
                'is_scheduled_slot' => false
            ]);
        }

        // Add scheduled slots from timetables that don't have existing sessions
        foreach($classes as $class) {
            if ($class->timetable && $class->timetable->weekly_schedule && isset($class->timetable->weekly_schedule[$dayName])) {
                foreach($class->timetable->weekly_schedule[$dayName] as $time) {
                    // Check if there's already an existing session for this time/class
                    $hasExistingSession = $existingSessions->first(function($session) use ($time, $class) {
                        return $session->session_time->format('H:i') === $time
                            && $session->class_id === $class->id;
                    });

                    // If no existing session, add as scheduled slot
                    if (!$hasExistingSession) {
                        $combinedSessions->push([
                            'type' => 'scheduled_slot',
                            'session' => null,
                            'class' => $class,
                            'time' => $time,
                            'display_time' => \Carbon\Carbon::parse($time)->format('g:i A'),
                            'duration' => $class->duration_minutes ?? 60,
                            'status' => 'scheduled',
                            'is_scheduled_slot' => true
                        ]);
                    }
                }
            }
        }

        return $combinedSessions->sortBy('time');
    }
    
    // This week's statistics
    public function getWeeklyStatsProperty()
    {
        $teacher = auth()->user()->teacher;

        if (!$teacher) {
            return [
                'total_sessions' => 0,
                'completed_sessions' => 0,
                'total_earnings' => 0,
                'total_students' => 0,
                'attendance_rate' => 0
            ];
        }

        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();

        $weekSessions = ClassSession::whereHas('class', function($query) use ($teacher) {
            $query->where('teacher_id', $teacher->id);
        })
        ->whereBetween('session_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
        ->get();

        $completedSessions = $weekSessions->where('status', 'completed');

        return [
            'total_sessions' => $weekSessions->count(),
            'completed_sessions' => $completedSessions->count(),
            'total_earnings' => $completedSessions->sum('allowance_amount'),
            'total_students' => $teacher->classes()->withCount('activeStudents')->get()->sum('active_students_count'),
            'attendance_rate' => $this->calculateWeeklyAttendanceRate($weekSessions)
        ];
    }
    
    // Monthly earnings
    public function getMonthlyEarningsProperty()
    {
        $teacher = auth()->user()->teacher;

        if (!$teacher) {
            return 0;
        }

        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        return ClassSession::whereHas('class', function($query) use ($teacher) {
            $query->where('teacher_id', $teacher->id);
        })
        ->whereBetween('session_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
        ->where('status', 'completed')
        ->sum('allowance_amount');
    }
    
    // Upcoming sessions (next 7 days) including scheduled slots from timetables
    public function getUpcomingSessionsProperty()
    {
        $teacher = auth()->user()->teacher;

        if (!$teacher) {
            return collect();
        }

        $upcomingDates = collect();

        // Get next 7 days
        for ($i = 1; $i <= 7; $i++) {
            $date = now()->addDays($i);
            $dayName = strtolower($date->format('l'));

            // Get existing sessions for this date
            $existingSessions = $teacher->classes()
                ->with(['sessions' => function($query) use ($date) {
                    $query->whereDate('session_date', $date->toDateString())
                          ->with(['class.course']);
                }])
                ->get()
                ->flatMap->sessions;

            // Get classes with timetables for this day
            $classes = $teacher->classes()->with(['course', 'timetable'])->get();

            foreach($classes as $class) {
                if ($class->timetable && $class->timetable->weekly_schedule && isset($class->timetable->weekly_schedule[$dayName])) {
                    foreach($class->timetable->weekly_schedule[$dayName] as $time) {
                        // Check if session already exists
                        $existingSession = $existingSessions->first(function($session) use ($time, $class) {
                            return $session->session_time->format('H:i') === $time
                                && $session->class_id === $class->id;
                        });

                        if ($existingSession) {
                            // Add existing session
                            $upcomingDates->push([
                                'type' => 'session',
                                'session' => $existingSession,
                                'class' => $existingSession->class,
                                'date' => $date,
                                'time' => $time,
                                'display_time' => \Carbon\Carbon::parse($time)->format('g:i A'),
                                'sort_key' => $date->format('Y-m-d') . ' ' . $time
                            ]);
                        } else {
                            // Add scheduled slot
                            $upcomingDates->push([
                                'type' => 'scheduled_slot',
                                'session' => null,
                                'class' => $class,
                                'date' => $date,
                                'time' => $time,
                                'display_time' => \Carbon\Carbon::parse($time)->format('g:i A'),
                                'sort_key' => $date->format('Y-m-d') . ' ' . $time
                            ]);
                        }
                    }
                }
            }
        }

        return $upcomingDates->sortBy('sort_key')->take(5);
    }
    
    // Recent activities (last 10 activities)
    public function getRecentActivitiesProperty()
    {
        $teacher = auth()->user()->teacher;

        if (!$teacher) {
            return collect();
        }

        $recentSessions = ClassSession::whereHas('class', function($query) use ($teacher) {
            $query->where('teacher_id', $teacher->id);
        })
        ->with(['class.course'])
        ->whereIn('status', ['completed', 'cancelled', 'no_show'])
        ->orderBy('updated_at', 'desc')
        ->limit(5)
        ->get();

        $activities = collect();

        foreach ($recentSessions as $session) {
            $activities->push([
                'type' => 'session_' . $session->status,
                'message' => $this->getSessionActivityMessage($session),
                'time' => $session->updated_at->diffForHumans(),
                'icon' => $this->getSessionActivityIcon($session->status)
            ]);
        }

        return $activities;
    }
    
    // Quick action methods
    public function startSession($sessionId)
    {
        $session = ClassSession::findOrFail($sessionId);

        // Verify this session belongs to the current teacher
        if ($session->class->teacher_id !== auth()->user()->teacher->id) {
            abort(403, 'Unauthorized access');
        }

        $session->markAsOngoing();

        $this->dispatch('session-started', sessionId: $sessionId);
        session()->flash('message', 'Session started successfully!');
    }

    public function startSessionFromScheduledSlot($classId, $time)
    {
        $class = \App\Models\ClassModel::findOrFail($classId);
        $teacher = auth()->user()->teacher;

        // Verify teacher owns this class
        if ($class->teacher_id !== $teacher->id) {
            abort(403, 'You are not authorized to manage this class.');
        }

        $today = now();
        $timeObj = \Carbon\Carbon::parse($time);

        // Check if session already exists for this date/time/class
        $existingSession = $class->sessions()
            ->whereDate('session_date', $today->toDateString())
            ->whereTime('session_time', $timeObj->format('H:i:s'))
            ->first();

        if ($existingSession) {
            // If session exists and is scheduled, start it
            if ($existingSession->isScheduled()) {
                $existingSession->markAsOngoing();
                $this->dispatch('session-started', sessionId: $existingSession->id);
                session()->flash('message', 'Session started successfully!');
            } elseif ($existingSession->isOngoing()) {
                session()->flash('message', 'Session is already ongoing.');
            } else {
                session()->flash('error', 'Session cannot be started (status: ' . $existingSession->status . ')');
            }
        } else {
            // Create new session and start it
            $newSession = $class->sessions()->create([
                'session_date' => $today->toDateString(),
                'session_time' => $timeObj->format('H:i:s'),
                'duration_minutes' => $class->duration_minutes ?? 60,
                'status' => 'ongoing',
                'started_at' => now(),
            ]);

            $this->dispatch('session-started', sessionId: $newSession->id);
            session()->flash('message', 'New session created and started!');
        }
    }
    
    public function completeSession()
    {
        // Validate that notes are provided
        if (empty(trim($this->completionNotes))) {
            session()->flash('error', 'Please add notes before completing the session.');
            return;
        }

        if ($this->selectedSession && $this->selectedSession->isOngoing()) {
            $this->selectedSession->markCompleted($this->completionNotes);
            $this->selectedSession = $this->selectedSession->fresh(); // Refresh the selected session
            $this->showNotesField = false;
            $this->dispatch('session-completed', sessionId: $this->selectedSession->id);
            session()->flash('message', 'Session completed successfully!');
            $this->closeModal();
        }
    }
    
    private function calculateWeeklyAttendanceRate($sessions)
    {
        $completedSessions = $sessions->where('status', 'completed');
        
        if ($completedSessions->isEmpty()) {
            return 0;
        }
        
        $totalPossibleAttendance = $completedSessions->sum(function($session) {
            return $session->class->activeStudents()->count();
        });
        
        if ($totalPossibleAttendance === 0) {
            return 0;
        }
        
        $totalPresentAttendance = $completedSessions->sum('present_count');
        
        return round(($totalPresentAttendance / $totalPossibleAttendance) * 100, 1);
    }
    
    private function getSessionActivityMessage($session)
    {
        return match($session->status) {
            'completed' =>"Completed session for {$session->class->course->name}",
            'cancelled' =>"Cancelled session for {$session->class->course->name}",
            'no_show' =>"No-show session for {$session->class->course->name}",
            default =>"Session updated for {$session->class->course->name}"
        };
    }
    
    private function getSessionActivityIcon($status)
    {
        return match($status) {
            'completed' => 'check-circle',
            'cancelled' => 'x-circle',
            'no_show' => 'exclamation-triangle',
            default => 'information-circle'
        };
    }
}; ?>

<div class="w-full space-y-6" x-data="{
    showModal: @entangle('showModal'),
    elapsedTime: 0,
    startTime: null,
    timer: null,

    init() {
        this.updateTimer();
    },

    updateTimer() {
        if (this.startTime) {
            this.elapsedTime = Math.floor((Date.now() - this.startTime) / 1000);
        }
    },

    startTimer(startedAt) {
        this.startTime = new Date(startedAt).getTime();
        this.timer = setInterval(() => {
            this.updateTimer();
        }, 1000);
    },

    stopTimer() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
        this.elapsedTime = 0;
        this.startTime = null;
    },

    formatTime(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;

        if (hours > 0) {
            return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        } else {
            return `${minutes}:${secs.toString().padStart(2, '0')}`;
        }
    }
}"
x-effect="
    if (showModal && $wire.selectedSession && $wire.selectedSession.status === 'ongoing' && $wire.selectedSession.started_at) {
        startTimer($wire.selectedSession.started_at);
    } else {
        stopTimer();
    }
">
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Welcome back, {{ auth()->user()->name }}!</flux:heading>
            <flux:text class="mt-2">Here's what's happening with your classes today</flux:text>
        </div>
        <flux:button variant="primary" :href="route('teacher.timetable')" wire:navigate icon="calendar">Timetable</flux:button>
    </div>

    <!-- Statistics Cards -->
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <!-- Today's Sessions -->
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-gray-600">Today's Sessions</flux:heading>
                    <flux:heading size="xl">{{ $this->todaySessions->count() }}</flux:heading>
                    <flux:text size="sm" class="text-blue-600">
                        {{ $this->todaySessions->where('status', 'completed')->count() }} completed
                    </flux:text>
                </div>
                <flux:icon icon="calendar-days" class="w-8 h-8 text-blue-500" />
            </div>
        </flux:card>

        <!-- This Week's Earnings -->
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-gray-600">Week Earnings</flux:heading>
                    <flux:heading size="xl">RM {{ number_format($this->weeklyStats['total_earnings'], 2) }}</flux:heading>
                    <flux:text size="sm" class="text-emerald-600">
                        {{ $this->weeklyStats['completed_sessions'] }} sessions
                    </flux:text>
                </div>
                <flux:icon icon="currency-dollar" class="w-8 h-8 text-emerald-500" />
            </div>
        </flux:card>

        <!-- Active Students -->
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-gray-600">Active Students</flux:heading>
                    <flux:heading size="xl">{{ $this->weeklyStats['total_students'] }}</flux:heading>
                    <flux:text size="sm" class="text-purple-600">Across all classes</flux:text>
                </div>
                <flux:icon icon="users" class="w-8 h-8 text-purple-500" />
            </div>
        </flux:card>

        <!-- Attendance Rate -->
        <flux:card>
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm" class="text-gray-600">Attendance Rate</flux:heading>
                    <flux:heading size="xl">{{ $this->weeklyStats['attendance_rate'] }}%</flux:heading>
                    <flux:text size="sm" class="text-orange-600">This week</flux:text>
                </div>
                <flux:icon icon="chart-pie" class="w-8 h-8 text-orange-500" />
            </div>
        </flux:card>
    </div>

    <!-- Main Content Grid -->
    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Today's Schedule -->
        <div class="lg:col-span-2">
            <flux:card>
                <flux:header>
                    <flux:heading size="lg">Today's Schedule</flux:heading>
                    <flux:text size="sm" class="text-gray-600">{{ now()->format('l, F j, Y') }}</flux:text>
                </flux:header>
                
                @if($this->todaySessions->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($this->todaySessions as $item)
                            <div class="flex items-center justify-between p-4 rounded-lg
                                {{ $item['is_scheduled_slot'] ? 'bg-indigo-50 /30 border border-indigo-200 ' : 'bg-gray-50 ' }}">
                                <div class="flex items-center space-x-4">
                                    <div class="text-center min-w-[60px]">
                                        <flux:text size="sm" class="font-medium">{{ $item['display_time'] }}</flux:text>
                                        <flux:text size="xs" class="text-gray-500">
                                            @if($item['is_scheduled_slot'])
                                                {{ $item['duration'] }} min
                                            @else
                                                {{ $item['session']->formatted_duration }}
                                            @endif
                                        </flux:text>
                                    </div>
                                    <div class="flex-1">
                                        <flux:heading size="sm">{{ $item['class']->course->title ?? $item['class']->course->name }}</flux:heading>
                                        <flux:text size="sm" class="text-gray-600">
                                            {{ $item['class']->title }}
                                            @if(!$item['is_scheduled_slot'])
                                                • {{ $item['session']->class->activeStudents()->count() }} students
                                            @else
                                                • {{ $item['class']->activeStudents()->count() }} students
                                            @endif
                                            • {{ $item['class']->location ?? 'Online' }}
                                        </flux:text>
                                    </div>
                                    <div>
                                        @if($item['is_scheduled_slot'])
                                            <flux:badge color="indigo">Scheduled</flux:badge>
                                        @else
                                            <flux:badge
                                                :color="$item['status'] === 'completed' ? 'emerald' : ($item['status'] === 'ongoing' ? 'blue' : ($item['status'] === 'scheduled' ? 'gray' : 'red'))">
                                                {{ ucfirst($item['status']) }}
                                            </flux:badge>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    @if($item['is_scheduled_slot'])
                                        <flux:button
                                            variant="primary"
                                            size="sm"
                                            icon="play"
                                            wire:click="requestStartSessionFromScheduledSlot({{ $item['class']->id }}, '{{ $item['time'] }}')"
                                        >
                                            Start Session
                                        </flux:button>
                                    @else
                                        @if($item['session']->isScheduled())
                                            <flux:button
                                                variant="primary"
                                                size="sm"
                                                icon="play"
                                                wire:click="requestStartSession({{ $item['session']->id }})"
                                            >
                                                Start
                                            </flux:button>
                                        @elseif($item['session']->isOngoing())
                                            <div class="flex items-center gap-2">
                                                <!-- Elapsed Time Display -->
                                                <div class="bg-green-100 border border-green-200 rounded px-2 py-1"
                                                     x-data="{
                                                        elapsedTime: 0,
                                                        timer: null,
                                                        formatTime(seconds) {
                                                            const hours = Math.floor(seconds / 3600);
                                                            const minutes = Math.floor((seconds % 3600) / 60);
                                                            const secs = seconds % 60;
                                                            if (hours > 0) {
                                                                return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
                                                            } else {
                                                                return `${minutes}:${secs.toString().padStart(2, '0')}`;
                                                            }
                                                        }
                                                    }"
                                                    x-init="
                                                        const startTime = new Date('{{ $item['session']->started_at ? $item['session']->started_at->toISOString() : now()->toISOString() }}').getTime();
                                                        elapsedTime = Math.floor((Date.now() - startTime) / 1000);
                                                        timer = setInterval(() => {
                                                            elapsedTime = Math.floor((Date.now() - startTime) / 1000);
                                                        }, 1000);
                                                    "
                                                    x-destroy="timer && clearInterval(timer)">
                                                    <div class="text-xs font-mono font-semibold text-green-700" x-text="formatTime(elapsedTime)"></div>
                                                    <div class="text-xs text-green-600">Elapsed</div>
                                                </div>
                                                <flux:button
                                                    variant="primary"
                                                    size="sm"
                                                    icon="check"
                                                    wire:click="selectSession({{ $item['session']->id }})"
                                                >
                                                    Complete
                                                </flux:button>
                                            </div>
                                        @endif
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            icon="eye"
                                            wire:click="selectSession({{ $item['session']->id }})"
                                        >
                                            View
                                        </flux:button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12">
                        <flux:icon icon="calendar-days" class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                        <flux:heading size="lg" class="text-gray-600  mb-2">No sessions today</flux:heading>
                        <flux:text class="text-gray-600">Take a well-deserved break!</flux:text>
                    </div>
                @endif
                
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <flux:link :href="route('teacher.timetable')" wire:navigate variant="subtle" icon="calendar">
                        View all timetable
                    </flux:link>
                </div>
            </flux:card>
        </div>

        <!-- Sidebar Content -->
        <div class="space-y-6">
            <!-- Monthly Earnings Overview -->
            <flux:card>
                <flux:header>
                    <flux:heading size="lg">This Month</flux:heading>
                </flux:header>
                
                <div class="space-y-4">
                    <div class="text-center">
                        <flux:heading size="2xl" class="text-emerald-600">RM {{ number_format($this->monthlyEarnings, 2) }}</flux:heading>
                        <flux:text size="sm" class="text-gray-600">Total Earnings</flux:text>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 text-center">
                        <div>
                            <flux:text class="font-medium">{{ $this->weeklyStats['completed_sessions'] }}</flux:text>
                            <flux:text size="xs" class="text-gray-600">Sessions</flux:text>
                        </div>
                        <div>
                            <flux:text class="font-medium">{{ $this->weeklyStats['attendance_rate'] }}%</flux:text>
                            <flux:text size="xs" class="text-gray-600">Attendance</flux:text>
                        </div>
                    </div>
                </div>
            </flux:card>

            <!-- Recent Activity -->
            <flux:card>
                <flux:header>
                    <flux:heading size="lg">Recent Activity</flux:heading>
                </flux:header>
                
                @if($this->recentActivities->isNotEmpty())
                    <div class="space-y-3">
                        @foreach($this->recentActivities as $activity)
                            <div class="flex items-start space-x-3">
                                <div class="w-8 h-8 bg-gray-100  rounded-full flex items-center justify-center">
                                    <flux:icon icon="{{ $activity['icon'] }}" class="w-4 h-4 text-gray-600" />
                                </div>
                                <div class="flex-1">
                                    <flux:text size="sm">{{ $activity['message'] }}</flux:text>
                                    <flux:text size="xs" class="text-gray-600">{{ $activity['time'] }}</flux:text>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <flux:text class="text-gray-600">No recent activity</flux:text>
                @endif
            </flux:card>

            <!-- Upcoming Sessions -->
            <flux:card>
                <flux:header>
                    <flux:heading size="lg">Coming Up</flux:heading>
                </flux:header>
                
                @if($this->upcomingSessions->isNotEmpty())
                    <div class="space-y-3">
                        @foreach($this->upcomingSessions as $item)
                            <div class="flex items-center justify-between p-3 rounded-lg
                                {{ $item['type'] === 'scheduled_slot' ? 'bg-indigo-50 border border-indigo-200' : 'bg-gray-50' }}">
                                <div>
                                    <flux:text size="sm" class="font-medium">
                                        {{ $item['class']->course->title ?? $item['class']->course->name }}
                                    </flux:text>
                                    <flux:text size="xs" class="text-gray-600">
                                        {{ $item['date']->format('M j') }} at {{ $item['display_time'] }}
                                    </flux:text>
                                    <flux:text size="xs" class="text-gray-500">
                                        {{ $item['class']->title }}
                                    </flux:text>
                                </div>
                                <div class="text-right">
                                    @if($item['type'] === 'scheduled_slot')
                                        <flux:badge color="indigo" size="sm">Scheduled</flux:badge>
                                    @else
                                        <flux:badge
                                            size="sm"
                                            :color="$item['session']->status === 'completed' ? 'emerald' : ($item['session']->status === 'ongoing' ? 'blue' : 'gray')"
                                        >
                                            {{ ucfirst($item['session']->status) }}
                                        </flux:badge>
                                    @endif
                                    <flux:text size="xs" class="text-gray-500 mt-1 block">
                                        {{ $item['class']->activeStudents()->count() }} students
                                    </flux:text>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <flux:text class="text-gray-600">No upcoming sessions</flux:text>
                @endif
                
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <flux:link :href="route('teacher.timetable')" wire:navigate variant="subtle" icon="calendar">
                        View timetable
                    </flux:link>
                </div>
            </flux:card>
        </div>
    </div>

    <!-- Session Details Modal -->
    <flux:modal wire:model="showModal" class="max-w-2xl" wire:poll.5s="$refresh">
        @if($selectedSession)
            <div class="p-6 border-b border-gray-200">
                <flux:heading size="lg">{{ $selectedSession->class->title }}</flux:heading>
            </div>

            <div class="p-6">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <flux:text class="font-medium">Date & Time</flux:text>
                            <flux:text class="text-gray-600">
                                {{ $selectedSession->formatted_date_time }}
                            </flux:text>
                        </div>
                        <div>
                            <flux:text class="font-medium">Duration</flux:text>
                            <flux:text class="text-gray-600">
                                {{ $selectedSession->formatted_duration }}
                            </flux:text>
                        </div>
                        <div>
                            <flux:text class="font-medium">Status</flux:text>
                            <flux:badge class="{{ $selectedSession->status_badge_class }}">
                                {{ $selectedSession->status_label }}
                            </flux:badge>
                        </div>
                        <div>
                            <flux:text class="font-medium">Course</flux:text>
                            <flux:text class="text-gray-600">
                                {{ $selectedSession->class->course->title }}
                            </flux:text>
                        </div>
                    </div>

                    @if($selectedSession->isOngoing())
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <flux:text class="font-medium text-green-800">Session Timer</flux:text>
                                    <flux:text class="text-green-600 text-sm">Session in progress</flux:text>
                                </div>
                                <div class="text-right" x-data="{
                                    modalTimer: 0,
                                    modalInterval: null,
                                    initModalTimer() {
                                        const startedAt = '{{ $selectedSession->started_at ? $selectedSession->started_at->toISOString() : now()->toISOString() }}';
                                        if (startedAt) {
                                            const startTime = new Date(startedAt).getTime();
                                            this.modalTimer = Math.floor((Date.now() - startTime) / 1000);
                                            this.modalInterval = setInterval(() => {
                                                this.modalTimer = Math.floor((Date.now() - startTime) / 1000);
                                            }, 1000);
                                        }
                                    },
                                    stopModalTimer() {
                                        if (this.modalInterval) {
                                            clearInterval(this.modalInterval);
                                            this.modalInterval = null;
                                        }
                                    }
                                }" x-init="initModalTimer()" x-destroy="stopModalTimer()">
                                    <div class="text-2xl font-mono font-bold text-green-700" x-text="formatTime(modalTimer)">
                                    </div>
                                    <flux:text class="text-green-600 text-xs">Elapsed time</flux:text>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if($selectedSession->attendances->count() > 0)
                        <div>
                            <flux:text class="font-medium mb-2">Students ({{ $selectedSession->attendances->count() }})</flux:text>
                            <div class="space-y-1">
                                @foreach($selectedSession->attendances as $attendance)
                                    <div class="flex items-center justify-between py-1">
                                        <flux:text class="text-sm">{{ $attendance->student->user->name }}</flux:text>
                                        <flux:badge :class="$attendance->status_badge_class" size="sm">
                                            {{ $attendance->status_label }}
                                        </flux:badge>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($selectedSession->teacher_notes && !$showNotesField)
                        <div>
                            <flux:text class="font-medium">Session Notes</flux:text>
                            <flux:text class="text-gray-600 text-sm">
                                {{ $selectedSession->teacher_notes }}
                            </flux:text>
                        </div>
                    @endif

                    @if($showNotesField)
                        <div>
                            <flux:text class="font-medium">Session Notes</flux:text>
                            <flux:text class="text-gray-500 text-xs mb-2">
                                Please add notes before completing the session
                            </flux:text>
                            <flux:textarea
                                wire:model="completionNotes"
                                placeholder="Add session notes, summary, or any important details..."
                                rows="4"
                                class="w-full"
                            />
                        </div>
                    @endif
                </div>
            </div>

            <div class="p-6 border-t border-gray-200">
                <div class="flex items-center justify-between w-full">
                    <div class="flex gap-2">
                        @if($selectedSession->isScheduled())
                            <flux:button wire:click="startSession({{ $selectedSession->id }})" variant="primary" size="sm">
                                Start Session
                            </flux:button>
                        @elseif($selectedSession->isOngoing())
                            @if(!$showNotesField)
                                <flux:button wire:click="showCompleteSessionForm" variant="primary" size="sm">
                                    Complete Session
                                </flux:button>
                            @else
                                <div class="flex gap-2">
                                    <flux:button wire:click="completeSession" variant="primary" size="sm">
                                        Confirm Complete
                                    </flux:button>
                                    <flux:button wire:click="$set('showNotesField', false)" variant="ghost" size="sm">
                                        Cancel
                                    </flux:button>
                                </div>
                            @endif
                        @endif
                    </div>

                    <flux:button wire:click="closeModal" variant="ghost">
                        Close
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>

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