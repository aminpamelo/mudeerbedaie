<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\ClassSession;
use App\Models\ClassStudent;
use Carbon\Carbon;

new #[Layout('components.layouts.app')] class extends Component {
    public Carbon $currentDate;
    public string $classFilter = 'all';
    public string $statusFilter = 'all';
    public bool $showModal = false;
    public ?ClassSession $selectedSession = null;
    
    public function mount()
    {
        $this->currentDate = Carbon::now();
    }
    
    public function previousPeriod()
    {
        $this->currentDate->subWeek();
    }
    
    public function nextPeriod()
    {
        $this->currentDate->addWeek();
    }
    
    public function goToToday()
    {
        $this->currentDate = Carbon::now();
    }
    
    public function selectSession(ClassSession $session)
    {
        $this->selectedSession = $session;
        $this->showModal = true;
    }
    
    public function closeModal()
    {
        $this->showModal = false;
        $this->selectedSession = null;
    }
    
    public function with()
    {
        $student = auth()->user()->student;
        
        if (!$student) {
            return [
                'sessions' => collect(),
                'classes' => collect(),
                'statistics' => $this->getEmptyStatistics()
            ];
        }
        
        $classes = $student->activeClasses()->with('course', 'teacher.user')->get();
        $sessions = $this->getSessionsForCurrentView($student);
        
        return [
            'sessions' => $sessions,
            'classes' => $classes,
            'statistics' => $this->getStatistics($student, $sessions),
            'currentPeriodLabel' => $this->getCurrentPeriodLabel(),
            'calendarData' => $this->getCalendarData($sessions)
        ];
    }
    
    private function getSessionsForCurrentView($student)
    {
        $query = ClassSession::with(['class.course', 'class.teacher.user', 'attendances' => function($q) use ($student) {
            $q->where('student_id', $student->id);
        }, 'attendances.student.user'])
            ->whereHas('class.students', function($q) use ($student) {
                $q->where('class_students.student_id', $student->id)
                  ->where('class_students.status', 'active');
            });
        
        // Apply class filter
        if ($this->classFilter !== 'all') {
            $query->where('class_id', $this->classFilter);
        }
        
        // Apply status filter
        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }
        
        // Apply date range for week view
        $startOfWeek = $this->currentDate->copy()->startOfWeek();
        $endOfWeek = $this->currentDate->copy()->endOfWeek();
        $query->whereBetween('session_date', [$startOfWeek, $endOfWeek]);
        
        return $query->orderBy('session_date')->orderBy('session_time')->get();
    }
    
    private function getStatistics($student, $sessions)
    {
        $now = Carbon::now();
        
        // Sessions this week
        $weekStart = $now->copy()->startOfWeek();
        $weekEnd = $now->copy()->endOfWeek();
        $sessionsThisWeek = ClassSession::whereHas('class.students', function($q) use ($student) {
            $q->where('class_students.student_id', $student->id)->where('class_students.status', 'active');
        })->whereBetween('session_date', [$weekStart, $weekEnd])->count();
        
        // Sessions this month
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $sessionsThisMonth = ClassSession::whereHas('class.students', function($q) use ($student) {
            $q->where('class_students.student_id', $student->id)->where('class_students.status', 'active');
        })->whereBetween('session_date', [$monthStart, $monthEnd])->count();
        
        // Upcoming sessions
        $upcomingSessions = ClassSession::whereHas('class.students', function($q) use ($student) {
            $q->where('class_students.student_id', $student->id)->where('class_students.status', 'active');
        })->where('session_date', '>=', $now->startOfDay())
          ->where('status', 'scheduled')->count();
        
        // Attended sessions this month
        $attendedThisMonth = ClassSession::whereHas('class.students', function($q) use ($student) {
            $q->where('class_students.student_id', $student->id)->where('class_students.status', 'active');
        })->whereHas('attendances', function($q) use ($student) {
            $q->where('student_id', $student->id)->whereIn('status', ['present', 'late']);
        })->whereBetween('session_date', [$monthStart, $monthEnd])
          ->where('status', 'completed')->count();
        
        return [
            'sessions_this_week' => $sessionsThisWeek,
            'sessions_this_month' => $sessionsThisMonth,
            'upcoming_sessions' => $upcomingSessions,
            'attended_this_month' => $attendedThisMonth
        ];
    }
    
    private function getEmptyStatistics()
    {
        return [
            'sessions_this_week' => 0,
            'sessions_this_month' => 0,
            'upcoming_sessions' => 0,
            'attended_this_month' => 0
        ];
    }
    
    private function getCurrentPeriodLabel()
    {
        $start = $this->currentDate->copy()->startOfWeek();
        $end = $this->currentDate->copy()->endOfWeek();
        return $start->format('M d') . ' - ' . $end->format('M d, Y');
    }
    
    private function getCalendarData($sessions)
    {
        return $this->getWeekData($sessions);
    }
    
    private function getWeekData($sessions)
    {
        $weekStart = $this->currentDate->copy()->startOfWeek();
        $student = auth()->user()->student;
        $days = [];
        
        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->copy()->addDays($i);
            $dayName = strtolower($date->format('l'));
            $daySessions = $sessions->filter(function($session) use ($date) {
                return $session->session_date->isSameDay($date);
            })->sortBy('session_time')->values();
            
            // Get all scheduled times for this day across all student's classes
            $scheduledSlots = [];
            if ($student) {
                foreach ($student->activeClasses as $class) {
                    $timetable = $class->timetable;
                    if ($timetable && $timetable->weekly_schedule && isset($timetable->weekly_schedule[$dayName])) {
                        foreach ($timetable->weekly_schedule[$dayName] as $time) {
                            $scheduledSlots[] = [
                                'time' => $time,
                                'class' => $class,
                                'session' => $daySessions->first(function($session) use ($time, $class) {
                                    return $session->session_time->format('H:i') === $time 
                                        && $session->class_id === $class->id;
                                })
                            ];
                        }
                    }
                }
            }
            
            $days[] = [
                'date' => $date,
                'sessions' => $daySessions,
                'scheduledSlots' => collect($scheduledSlots),
                'isToday' => $date->isToday(),
                'dayName' => $date->format('D'),
                'dayNumber' => $date->format('j')
            ];
        }
        
        return $days;
    }
    
}; ?>

<div x-data="{ 
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
"
@modal-opened.window="
    if ($wire.selectedSession && $wire.selectedSession.status === 'ongoing' && $wire.selectedSession.started_at) {
        $nextTick(() => {
            startTimer($wire.selectedSession.started_at);
        });
    }
">
    <!-- Header Section -->
    <div class="mb-6 space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">My Timetable</flux:heading>
                <flux:text class="mt-2">Your class schedule and sessions across all classes</flux:text>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4 md:gap-6">
            <flux:card class="p-4 md:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:text size="sm" class="text-gray-600">This Week</flux:text>
                        <flux:heading size="lg">{{ $statistics['sessions_this_week'] }}</flux:heading>
                        <flux:text size="sm" class="text-gray-500">Sessions</flux:text>
                    </div>
                    <div class="p-2 bg-blue-50 /20 rounded-lg">
                        <flux:icon name="calendar-days" class="w-6 h-6 text-blue-600" />
                    </div>
                </div>
            </flux:card>
            
            <flux:card class="p-4 md:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:text size="sm" class="text-gray-600">This Month</flux:text>
                        <flux:heading size="lg">{{ $statistics['sessions_this_month'] }}</flux:heading>
                        <flux:text size="sm" class="text-gray-500">Sessions</flux:text>
                    </div>
                    <div class="p-2 bg-green-50 /20 rounded-lg">
                        <flux:icon name="calendar" class="w-6 h-6 text-green-600" />
                    </div>
                </div>
            </flux:card>
            
            <flux:card class="p-4 md:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:text size="sm" class="text-gray-600">Upcoming</flux:text>
                        <flux:heading size="lg">{{ $statistics['upcoming_sessions'] }}</flux:heading>
                        <flux:text size="sm" class="text-gray-500">Sessions</flux:text>
                    </div>
                    <div class="p-2 bg-purple-50 /20 rounded-lg">
                        <flux:icon name="clock" class="w-6 h-6 text-purple-600" />
                    </div>
                </div>
            </flux:card>
            
            <flux:card class="p-4 md:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:text size="sm" class="text-gray-600">Attended</flux:text>
                        <flux:heading size="lg">{{ $statistics['attended_this_month'] }}</flux:heading>
                        <flux:text size="sm" class="text-gray-500">This Month</flux:text>
                    </div>
                    <div class="p-2 bg-emerald-50 /20 rounded-lg">
                        <flux:icon name="check-circle" class="w-6 h-6 text-emerald-600" />
                    </div>
                </div>
            </flux:card>
        </div>
    </div>
    
    <!-- Controls Section -->
    <flux:card class="mb-6">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <!-- Week View Title -->
            <div class="flex items-center gap-2">
                <flux:icon name="calendar" class="w-5 h-5 text-primary-600" />
                <flux:heading size="lg">Week View</flux:heading>
            </div>
            
            <!-- Navigation and Filters -->
            <div class="flex flex-col lg:flex-row items-start lg:items-center gap-4">
                <!-- Week Navigation -->
                <div class="flex items-center gap-3 bg-gray-50  rounded-lg p-2 border">
                    <flux:button 
                        variant="outline" 
                        wire:click="previousPeriod" 
                        size="sm"
                        title="Previous Week"
                    >
                        <div class="flex items-center justify-center">
                            <flux:icon name="chevron-left" class="w-4 h-4" />
                        </div>
                    </flux:button>
                    
                    <div class="px-4 py-2 text-sm font-semibold text-gray-900  bg-white  rounded border min-w-[200px] text-center">
                        <div class="text-xs text-gray-500  uppercase tracking-wide">Current Week</div>
                        <div class="font-medium">{{ $currentPeriodLabel }}</div>
                    </div>
                    
                    <flux:button 
                        variant="outline" 
                        wire:click="nextPeriod" 
                        size="sm"
                        title="Next Week"
                    >
                        <div class="flex items-center justify-center">
                            <flux:icon name="chevron-right" class="w-4 h-4" />
                        </div>
                    </flux:button>
                    
                    <div class="hidden md:block border-l border-gray-300  pl-3">
                        <flux:button 
                            variant="primary" 
                            wire:click="goToToday" 
                            size="sm"
                            title="Go to current week"
                        >
                            <div class="flex items-center justify-center">
                                <flux:icon name="calendar-days" class="w-4 h-4 mr-1" />
                                This Week
                            </div>
                        </flux:button>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="flex items-center gap-2">
                    <flux:select wire:model.live="classFilter" size="sm" placeholder="All Classes">
                        <option value="all">All Classes</option>
                        @foreach($classes as $class)
                            <option value="{{ $class->id }}">{{ $class->title }}</option>
                        @endforeach
                    </flux:select>
                    
                    <flux:select wire:model.live="statusFilter" size="sm" placeholder="All Status">
                        <option value="all">All Status</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="ongoing">Ongoing</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </flux:select>
                </div>
            </div>
        </div>
    </flux:card>
    
    <!-- Week View Content -->
    <flux:card wire:poll.30s="$refresh">
        @include('livewire.student.my-timetable.week-view', ['days' => $calendarData])
    </flux:card>
    
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
                                {{ $selectedSession->class->course->name }}
                            </flux:text>
                        </div>
                        <div>
                            <flux:text class="font-medium">Teacher</flux:text>
                            <flux:text class="text-gray-600">
                                {{ $selectedSession->class->teacher->user->name }}
                            </flux:text>
                        </div>
                    </div>
                    
                    @if($selectedSession->isOngoing())
                        <div class="bg-green-50 /20 border border-green-200  rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <flux:text class="font-medium text-green-800">Session Timer</flux:text>
                                    <flux:text class="text-green-600  text-sm">Session in progress</flux:text>
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
                                    <flux:text class="text-green-600  text-xs">Elapsed time</flux:text>
                                </div>
                            </div>
                        </div>
                    @endif
                    
                    <!-- Student's Own Attendance Status -->
                    @php
                        $myAttendance = $selectedSession->attendances->first(function($attendance) {
                            return $attendance->student_id === auth()->user()->student->id;
                        });
                    @endphp
                    
                    @if($myAttendance)
                        <div>
                            <flux:text class="font-medium mb-2">My Attendance</flux:text>
                            <flux:badge class="{{ $myAttendance->status_badge_class }}">
                                {{ $myAttendance->status_label }}
                            </flux:badge>
                            @if($myAttendance->teacher_remarks)
                                <flux:text class="text-gray-600  text-sm mt-1">
                                    {{ $myAttendance->teacher_remarks }}
                                </flux:text>
                            @endif
                        </div>
                    @endif
                    
                    <!-- Other Students (if group class) -->
                    @if($selectedSession->attendances->count() > 1)
                        <div>
                            <flux:text class="font-medium mb-2">Other Students ({{ $selectedSession->attendances->count() - 1 }})</flux:text>
                            <div class="space-y-1">
                                @foreach($selectedSession->attendances->where('student_id', '!=', auth()->user()->student->id) as $attendance)
                                    <div class="flex items-center justify-between py-1">
                                        <flux:text class="text-sm">{{ $attendance->student->user->name }}</flux:text>
                                        <flux:badge class="{{ $attendance->status_badge_class }}" size="sm">
                                            {{ $attendance->status_label }}
                                        </flux:badge>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    
                    @if($selectedSession->teacher_notes)
                        <div>
                            <flux:text class="font-medium">Session Notes</flux:text>
                            <flux:text class="text-gray-600  text-sm">
                                {{ $selectedSession->teacher_notes }}
                            </flux:text>
                        </div>
                    @endif
                </div>
            </div>
            
            <div class="p-6 border-t border-gray-200">
                <div class="flex items-center justify-end">
                    <flux:button wire:click="closeModal" variant="ghost">
                        Close
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>