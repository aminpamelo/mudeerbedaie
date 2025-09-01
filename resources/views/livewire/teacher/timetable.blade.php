<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\ClassSession;
use App\Models\ClassModel;
use Carbon\Carbon;

new #[Layout('components.layouts.teacher')] class extends Component {
    public string $currentView = 'week';
    public Carbon $currentDate;
    public string $classFilter = 'all';
    public string $statusFilter = 'all';
    public bool $showModal = false;
    public ?ClassSession $selectedSession = null;
    
    public function mount()
    {
        $this->currentDate = Carbon::now();
    }
    
    public function updatedCurrentView()
    {
        // Reset to current date when view changes
        $this->currentDate = Carbon::now();
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
        $this->showModal = true;
    }
    
    public function closeModal()
    {
        $this->showModal = false;
        $this->selectedSession = null;
    }
    
    public function startSession(ClassSession $session)
    {
        if ($session->isScheduled()) {
            $session->markAsOngoing();
            $this->dispatch('session-started', ['sessionId' => $session->id]);
            session()->flash('success', 'Session started successfully!');
        }
    }
    
    public function completeSession(ClassSession $session, ?string $notes = null)
    {
        if ($session->isOngoing()) {
            $session->markCompleted($notes);
            $this->dispatch('session-completed', ['sessionId' => $session->id]);
            session()->flash('success', 'Session completed successfully!');
        }
    }
    
    public function with()
    {
        $teacher = auth()->user()->teacher;
        
        if (!$teacher) {
            return [
                'sessions' => collect(),
                'classes' => collect(),
                'statistics' => $this->getEmptyStatistics()
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
        $days = [];
        
        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->copy()->addDays($i);
            $daySessions = $sessions->filter(function($session) use ($date) {
                return $session->session_date->isSameDay($date);
            })->sortBy('session_time')->values();
            
            $days[] = [
                'date' => $date,
                'sessions' => $daySessions,
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

<div x-data="{ showModal: @entangle('showModal') }">
    <!-- Header Section -->
    <div class="mb-6 space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">Timetable</flux:heading>
                <flux:text class="mt-2">Your teaching schedule and sessions</flux:text>
            </div>
            <flux:button variant="primary" wire:click="goToToday">
                Today
            </flux:button>
        </div>
        
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:text size="sm" class="text-gray-600 dark:text-gray-400">This Week</flux:text>
                        <flux:heading size="lg">{{ $statistics['sessions_this_week'] }}</flux:heading>
                        <flux:text size="sm" class="text-gray-500">Sessions</flux:text>
                    </div>
                    <div class="p-2 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                        <flux:icon name="calendar-days" class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                    </div>
                </div>
            </flux:card>
            
            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:text size="sm" class="text-gray-600 dark:text-gray-400">This Month</flux:text>
                        <flux:heading size="lg">{{ $statistics['sessions_this_month'] }}</flux:heading>
                        <flux:text size="sm" class="text-gray-500">Sessions</flux:text>
                    </div>
                    <div class="p-2 bg-green-50 dark:bg-green-900/20 rounded-lg">
                        <flux:icon name="calendar" class="w-6 h-6 text-green-600 dark:text-green-400" />
                    </div>
                </div>
            </flux:card>
            
            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:text size="sm" class="text-gray-600 dark:text-gray-400">Upcoming</flux:text>
                        <flux:heading size="lg">{{ $statistics['upcoming_sessions'] }}</flux:heading>
                        <flux:text size="sm" class="text-gray-500">Sessions</flux:text>
                    </div>
                    <div class="p-2 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                        <flux:icon name="clock" class="w-6 h-6 text-purple-600 dark:text-purple-400" />
                    </div>
                </div>
            </flux:card>
            
            <flux:card>
                <div class="flex items-center justify-between">
                    <div>
                        <flux:text size="sm" class="text-gray-600 dark:text-gray-400">Completed</flux:text>
                        <flux:heading size="lg">{{ $statistics['completed_this_month'] }}</flux:heading>
                        <flux:text size="sm" class="text-gray-500">This Month</flux:text>
                    </div>
                    <div class="p-2 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg">
                        <flux:icon name="check-circle" class="w-6 h-6 text-emerald-600 dark:text-emerald-400" />
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
            <div class="flex items-center gap-4">
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
                
                <!-- Navigation -->
                <div class="flex items-center gap-2">
                    <flux:button variant="ghost" wire:click="previousPeriod" size="sm">
                        <flux:icon name="chevron-left" class="w-4 h-4" />
                    </flux:button>
                    
                    <div class="px-4 py-2 text-sm font-medium text-gray-900 dark:text-gray-100 min-w-0">
                        {{ $currentPeriodLabel }}
                    </div>
                    
                    <flux:button variant="ghost" wire:click="nextPeriod" size="sm">
                        <flux:icon name="chevron-right" class="w-4 h-4" />
                    </flux:button>
                </div>
            </div>
        </div>
    </flux:card>
    
    <!-- Calendar Content -->
    <flux:card>
        @if($currentView === 'week')
            @include('livewire.teacher.timetable.week-view', ['days' => $calendarData])
        @elseif($currentView === 'month')
            @include('livewire.teacher.timetable.month-view', ['weeks' => $calendarData])
        @elseif($currentView === 'day')
            @include('livewire.teacher.timetable.day-view', ['timeSlots' => $calendarData])
        @elseif($currentView === 'list')
            @include('livewire.teacher.timetable.list-view', ['sessions' => $sessions])
        @endif
    </flux:card>
    
    <!-- Session Details Modal -->
    <flux:modal wire:model="showModal" class="max-w-2xl">
        @if($selectedSession)
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <flux:heading size="lg">{{ $selectedSession->class->title }}</flux:heading>
            </div>
            
            <div class="p-6">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <flux:text class="font-medium">Date & Time</flux:text>
                            <flux:text class="text-gray-600 dark:text-gray-400">
                                {{ $selectedSession->formatted_date_time }}
                            </flux:text>
                        </div>
                        <div>
                            <flux:text class="font-medium">Duration</flux:text>
                            <flux:text class="text-gray-600 dark:text-gray-400">
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
                            <flux:text class="text-gray-600 dark:text-gray-400">
                                {{ $selectedSession->class->course->title }}
                            </flux:text>
                        </div>
                    </div>
                    
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
                    
                    @if($selectedSession->teacher_notes)
                        <div>
                            <flux:text class="font-medium">Notes</flux:text>
                            <flux:text class="text-gray-600 dark:text-gray-400 text-sm">
                                {{ $selectedSession->teacher_notes }}
                            </flux:text>
                        </div>
                    @endif
                </div>
            </div>
            
            <div class="p-6 border-t border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between w-full">
                    <div class="flex gap-2">
                        @if($selectedSession->isScheduled())
                            <flux:button wire:click="startSession({{ $selectedSession->id }})" variant="primary" size="sm">
                                Start Session
                            </flux:button>
                        @elseif($selectedSession->isOngoing())
                            <flux:button wire:click="completeSession({{ $selectedSession->id }})" variant="primary" size="sm">
                                Complete Session
                            </flux:button>
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