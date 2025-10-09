<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\ClassSession;
use App\Models\ClassModel;
use Carbon\Carbon;

new #[Layout('components.layouts.teacher')] class extends Component {
    public string $currentView = 'week';
    public string $previousView = '';
    public Carbon $currentDate;
    public string $classFilter = 'all';
    public string $statusFilter = 'all';
    public bool $showModal = false;
    public ?ClassSession $selectedSession = null;
    public string $completionNotes = '';
    public bool $showNotesField = false;
    
    public function mount()
    {
        $this->currentDate = Carbon::now();
        $this->previousView = $this->currentView;
    }
    
    public function updatedCurrentView($value)
    {
        // Only reset to current date when actually switching views
        // This prevents resetting the date during navigation within the same view
        if ($this->previousView !== $value) {
            $this->currentDate = Carbon::now();
            $this->previousView = $value;
        }
    }
    
    public function previousPeriod()
    {
        switch ($this->currentView) {
            case 'week':
                $this->currentDate->subWeek();
                break;
            case 'month':
                $this->currentDate->subMonth();
                break;
            case 'day':
                $this->currentDate->subDay();
                break;
        }
    }
    
    public function nextPeriod()
    {
        switch ($this->currentView) {
            case 'week':
                $this->currentDate->addWeek();
                break;
            case 'month':
                $this->currentDate->addMonth();
                break;
            case 'day':
                $this->currentDate->addDay();
                break;
        }
    }
    
    public function goToToday()
    {
        $this->currentDate = Carbon::now();
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
    
    public function startSession(ClassSession $session)
    {
        if ($session->isScheduled()) {
            $session->markAsOngoing();
            $this->selectedSession = $session->fresh(); // Refresh the selected session
            $this->dispatch('session-started', ['sessionId' => $session->id]);
            session()->flash('success', 'Session started successfully!');
        }
    }
    
    public function showCompleteSessionForm()
    {
        $this->showNotesField = true;
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
            $this->dispatch('session-completed', ['sessionId' => $this->selectedSession->id]);
            session()->flash('success', 'Session completed successfully!');
        }
    }
    
    public function startSessionFromTimetable($classId, $date, $time)
    {
        $class = ClassModel::findOrFail($classId);
        $teacher = auth()->user()->teacher;
        
        // Verify teacher owns this class
        if ($class->teacher_id !== $teacher->id) {
            session()->flash('error', 'You are not authorized to manage this class.');
            return;
        }
        
        $dateObj = Carbon::parse($date);
        $timeObj = Carbon::parse($time);
        
        // Check if session already exists for this date/time/class
        $existingSession = $class->sessions()
            ->whereDate('session_date', $dateObj->toDateString())
            ->whereTime('session_time', $timeObj->format('H:i:s'))
            ->first();
        
        if ($existingSession) {
            // If session exists and is scheduled, start it
            if ($existingSession->isScheduled()) {
                $existingSession->markAsOngoing();
                $this->selectSession($existingSession->fresh());
                session()->flash('success', 'Session started successfully!');
            } elseif ($existingSession->isOngoing()) {
                // Select the ongoing session
                $this->selectSession($existingSession);
            } else {
                session()->flash('warning', 'Session cannot be started (status: ' . $existingSession->status . ')');
            }
        } else {
            // Create new session and start it
            $newSession = $class->sessions()->create([
                'session_date' => $dateObj->toDateString(),
                'session_time' => $timeObj->format('H:i:s'),
                'duration_minutes' => $class->duration_minutes ?? 60,
                'status' => 'ongoing',
                'started_at' => now(),
            ]);
            
            $this->selectSession($newSession);
            session()->flash('success', 'New session created and started!');
        }
    }
    
    public function with()
    {
        $teacher = auth()->user()->teacher;

        if (!$teacher) {
            return [
                'sessions' => collect(),
                'classes' => collect(),
                'statistics' => $this->getEmptyStatistics(),
                'currentPeriodLabel' => $this->getCurrentPeriodLabel(),
                'calendarData' => []
            ];
        }

        $classes = $teacher->classes()->with('course')->get();
        $sessions = $this->getSessionsForCurrentView($teacher);

        return [
            'sessions' => $sessions,
            'classes' => $classes,
            'statistics' => $this->getStatistics($teacher, $sessions),
            'currentPeriodLabel' => $this->getCurrentPeriodLabel(),
            'calendarData' => $this->getCalendarData($sessions)
        ];
    }
    
    private function getSessionsForCurrentView($teacher)
    {
        $query = ClassSession::with(['class.course', 'attendances.student.user'])
            ->whereHas('class', function($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id);
            });
        
        // Apply class filter
        if ($this->classFilter !== 'all') {
            $query->whereHas('class', function($q) {
                $q->where('id', $this->classFilter);
            });
        }
        
        // Apply status filter
        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }
        
        // Apply date range based on view
        switch ($this->currentView) {
            case 'week':
                $startOfWeek = $this->currentDate->copy()->startOfWeek();
                $endOfWeek = $this->currentDate->copy()->endOfWeek();
                $query->whereBetween('session_date', [$startOfWeek, $endOfWeek]);
                break;
            case 'month':
                $startOfMonth = $this->currentDate->copy()->startOfMonth();
                $endOfMonth = $this->currentDate->copy()->endOfMonth();
                $query->whereBetween('session_date', [$startOfMonth, $endOfMonth]);
                break;
            case 'day':
                $query->whereDate('session_date', $this->currentDate);
                break;
            case 'list':
                $query->where('session_date', '>=', now()->startOfDay())
                     ->orderBy('session_date')
                     ->orderBy('session_time');
                break;
        }
        
        return $query->orderBy('session_date')->orderBy('session_time')->get();
    }
    
    private function getStatistics($teacher, $sessions)
    {
        $now = Carbon::now();
        
        // Sessions this week
        $weekStart = $now->copy()->startOfWeek();
        $weekEnd = $now->copy()->endOfWeek();
        $sessionsThisWeek = ClassSession::whereHas('class', function($q) use ($teacher) {
            $q->where('teacher_id', $teacher->id);
        })->whereBetween('session_date', [$weekStart, $weekEnd])->count();
        
        // Sessions this month
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $sessionsThisMonth = ClassSession::whereHas('class', function($q) use ($teacher) {
            $q->where('teacher_id', $teacher->id);
        })->whereBetween('session_date', [$monthStart, $monthEnd])->count();
        
        // Upcoming sessions
        $upcomingSessions = ClassSession::whereHas('class', function($q) use ($teacher) {
            $q->where('teacher_id', $teacher->id);
        })->where('session_date', '>=', $now->startOfDay())
          ->where('status', 'scheduled')->count();
        
        // Completed sessions this month
        $completedThisMonth = ClassSession::whereHas('class', function($q) use ($teacher) {
            $q->where('teacher_id', $teacher->id);
        })->whereBetween('session_date', [$monthStart, $monthEnd])
          ->where('status', 'completed')->count();
        
        return [
            'sessions_this_week' => $sessionsThisWeek,
            'sessions_this_month' => $sessionsThisMonth,
            'upcoming_sessions' => $upcomingSessions,
            'completed_this_month' => $completedThisMonth
        ];
    }
    
    private function getEmptyStatistics()
    {
        return [
            'sessions_this_week' => 0,
            'sessions_this_month' => 0,
            'upcoming_sessions' => 0,
            'completed_this_month' => 0
        ];
    }
    
    private function getCurrentPeriodLabel()
    {
        switch ($this->currentView) {
            case 'week':
                $start = $this->currentDate->copy()->startOfWeek();
                $end = $this->currentDate->copy()->endOfWeek();
                return $start->format('M d') . ' - ' . $end->format('M d, Y');
            case 'month':
                return $this->currentDate->format('F Y');
            case 'day':
                return $this->currentDate->format('l, F d, Y');
            case 'list':
                return 'Upcoming Sessions';
            default:
                return '';
        }
    }
    
    private function getCalendarData($sessions)
    {
        switch ($this->currentView) {
            case 'week':
                return $this->getWeekData($sessions);
            case 'month':
                return $this->getMonthData($sessions);
            case 'day':
                return $this->getDayData($sessions);
            default:
                return [];
        }
    }
    
    private function getWeekData($sessions)
    {
        $weekStart = $this->currentDate->copy()->startOfWeek();
        $teacher = auth()->user()->teacher;
        $days = [];
        
        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->copy()->addDays($i);
            $dayName = strtolower($date->format('l'));
            $daySessions = $sessions->filter(function($session) use ($date) {
                return $session->session_date->isSameDay($date);
            })->sortBy('session_time')->values();
            
            // Get all scheduled times for this day across all classes
            $scheduledSlots = [];
            if ($teacher) {
                foreach ($teacher->classes as $class) {
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
    
    private function getMonthData($sessions)
    {
        $monthStart = $this->currentDate->copy()->startOfMonth();
        $monthEnd = $this->currentDate->copy()->endOfMonth();
        $calendarStart = $monthStart->copy()->startOfWeek();
        $calendarEnd = $monthEnd->copy()->endOfWeek();
        
        $weeks = [];
        $currentWeekStart = $calendarStart->copy();
        
        while ($currentWeekStart <= $calendarEnd) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $date = $currentWeekStart->copy()->addDays($i);
                $daySessions = $sessions->filter(function($session) use ($date) {
                    return $session->session_date->isSameDay($date);
                })->count();
                
                $week[] = [
                    'date' => $date,
                    'sessionCount' => $daySessions,
                    'isCurrentMonth' => $date->month === $this->currentDate->month,
                    'isToday' => $date->isToday(),
                    'dayNumber' => $date->format('j')
                ];
            }
            $weeks[] = $week;
            $currentWeekStart->addWeek();
        }
        
        return $weeks;
    }
    
    private function getDayData($sessions)
    {
        $timeSlots = [];
        $startHour = 6; // 6 AM
        $endHour = 22; // 10 PM
        
        for ($hour = $startHour; $hour <= $endHour; $hour++) {
            $time = sprintf('%02d:00', $hour);
            $slotSessions = $sessions->filter(function($session) use ($hour) {
                return $session->session_time->format('H') == $hour;
            });
            
            $timeSlots[] = [
                'time' => $time,
                'displayTime' => Carbon::createFromFormat('H:i', $time)->format('g A'),
                'sessions' => $slotSessions
            ];
        }
        
        return $timeSlots;
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
                <flux:heading size="xl">Timetable</flux:heading>
                <flux:text class="mt-2">Your teaching schedule and sessions</flux:text>
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
                        <flux:text size="sm" class="text-gray-600">Completed</flux:text>
                        <flux:heading size="lg">{{ $statistics['completed_this_month'] }}</flux:heading>
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
            <!-- View Buttons -->
            <div class="flex flex-wrap gap-2">
                <flux:button 
                    :variant="$currentView === 'week' ? 'primary' : 'ghost'" 
                    wire:click="$set('currentView', 'week')"
                    size="sm"
                >
                    Week
                </flux:button>
                <flux:button 
                    :variant="$currentView === 'month' ? 'primary' : 'ghost'" 
                    wire:click="$set('currentView', 'month')"
                    size="sm"
                >
                    Month
                </flux:button>
                <flux:button 
                    :variant="$currentView === 'day' ? 'primary' : 'ghost'" 
                    wire:click="$set('currentView', 'day')"
                    size="sm"
                >
                    Day
                </flux:button>
                <flux:button 
                    :variant="$currentView === 'list' ? 'primary' : 'ghost'" 
                    wire:click="$set('currentView', 'list')"
                    size="sm"
                >
                    List
                </flux:button>
            </div>
            
            <!-- Navigation and Filters -->
            <div class="flex flex-col lg:flex-row items-start lg:items-center gap-4">
                <!-- Enhanced Week Navigation -->
                @if($currentView === 'week')
                    <div class="flex items-center gap-3 bg-gray-50  rounded-lg p-2 border">
                        <flux:button 
                            variant="outline" 
                            wire:click="previousPeriod" 
                            size="sm"
                            title="Previous Week"
                        >
                            <flux:icon name="chevron-left" class="w-4 h-4" />
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
                            <flux:icon name="chevron-right" class="w-4 h-4" />
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
                @else
                    <!-- Standard Navigation for other views -->
                    <div class="flex items-center gap-2">
                        <flux:button variant="ghost" wire:click="previousPeriod" size="sm">
                            <flux:icon name="chevron-left" class="w-4 h-4" />
                        </flux:button>
                        
                        <div class="px-4 py-2 text-sm font-medium text-gray-900  min-w-0">
                            {{ $currentPeriodLabel }}
                        </div>
                        
                        <flux:button variant="ghost" wire:click="nextPeriod" size="sm">
                            <flux:icon name="chevron-right" class="w-4 h-4" />
                        </flux:button>
                    </div>
                @endif
                
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
    
    <!-- Calendar Content -->
    <flux:card wire:poll.30s="$refresh">
        @if($currentView === 'week')
            @include('livewire.teacher.timetable.week-view', ['days' => $calendarData])
        @elseif($currentView === 'month')
            @include('livewire.teacher.timetable.month-view', ['weeks' => $calendarData])
        @elseif($currentView === 'day')
            @include('livewire.teacher.timetable.day-view', ['timeSlots' => $calendarData])
        @elseif($currentView === 'list')
            @include('livewire.teacher.timetable.list-view', ['sessions' => $sessions])
        @else
            <div class="text-center py-8">
                <flux:text>Invalid view selected</flux:text>
            </div>
        @endif
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
                                {{ $selectedSession->class->course->title }}
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
                            <flux:text class="text-gray-600  text-sm">
                                {{ $selectedSession->teacher_notes }}
                            </flux:text>
                        </div>
                    @endif
                    
                    @if($showNotesField)
                        <div>
                            <flux:text class="font-medium">Session Notes</flux:text>
                            <flux:text class="text-gray-500  text-xs mb-2">
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
</div>