<?php

use App\Models\ClassModel;
use App\Models\ClassSession;
use App\Support\TeacherStartBriefing;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.teacher')] class extends Component
{
    public string $currentView = 'week';

    public string $previousView = '';

    public Carbon $currentDate;

    public string $classFilter = 'all';

    public string $statusFilter = 'all';

    public bool $showModal = false;

    public ?ClassSession $selectedSession = null;

    public string $completionNotes = '';

    public bool $showNotesField = false;

    public bool $showStartConfirmation = false;

    public ?int $sessionToStartId = null;

    public ?int $classToStartId = null;

    public ?string $dateToStart = null;

    public ?string $timeToStart = null;

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

    public function closeStartConfirmation()
    {
        $this->showStartConfirmation = false;
        $this->sessionToStartId = null;
        $this->classToStartId = null;
        $this->dateToStart = null;
        $this->timeToStart = null;
    }

    public function requestStartSession($sessionId)
    {
        $this->sessionToStartId = $sessionId;
        $this->showStartConfirmation = true;
    }

    public function requestStartSessionFromTimetable($classId, $date, $time)
    {
        $this->classToStartId = $classId;
        $this->dateToStart = $date;
        $this->timeToStart = $time;
        $this->showStartConfirmation = true;
    }

    public function confirmStartSession()
    {
        if ($this->sessionToStartId) {
            // Start existing session
            $session = ClassSession::findOrFail($this->sessionToStartId);
            $this->startSession($session);
        } elseif ($this->classToStartId && $this->dateToStart && $this->timeToStart) {
            // Start session from timetable slot
            $this->startSessionFromTimetable($this->classToStartId, $this->dateToStart, $this->timeToStart);
        }

        $this->closeStartConfirmation();
    }

    public function getStartBriefingProperty(): ?array
    {
        if ($this->sessionToStartId) {
            $session = ClassSession::with(['class.course', 'class.pics', 'class.activeStudents'])->find($this->sessionToStartId);

            return TeacherStartBriefing::build($session, $session?->class);
        }

        if ($this->classToStartId && $this->dateToStart && $this->timeToStart) {
            $class = ClassModel::with(['course', 'pics', 'activeStudents'])->find($this->classToStartId);

            $when = Carbon::parse($this->dateToStart.' '.$this->timeToStart);

            $session = ClassSession::where('class_id', $this->classToStartId)
                ->whereDate('session_date', $when->toDateString())
                ->whereTime('session_time', $when->format('H:i:s'))
                ->first();

            return TeacherStartBriefing::build($session, $class, $when);
        }

        return null;
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
                session()->flash('warning', 'Session cannot be started (status: '.$existingSession->status.')');
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

        if (! $teacher) {
            return [
                'sessions' => collect(),
                'classes' => collect(),
                'statistics' => $this->getEmptyStatistics(),
                'currentPeriodLabel' => $this->getCurrentPeriodLabel(),
                'calendarData' => [],
            ];
        }

        $classes = $teacher->classes()->with('course')->get();
        $sessions = $this->getSessionsForCurrentView($teacher);

        return [
            'sessions' => $sessions,
            'classes' => $classes,
            'statistics' => $this->getStatistics($teacher, $sessions),
            'currentPeriodLabel' => $this->getCurrentPeriodLabel(),
            'calendarData' => $this->getCalendarData($sessions),
        ];
    }

    private function getSessionsForCurrentView($teacher)
    {
        $query = ClassSession::with(['class.course', 'attendances.student.user', 'starter'])
            ->whereHas('class', function ($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id);
            });

        // Apply class filter
        if ($this->classFilter !== 'all') {
            $query->whereHas('class', function ($q) {
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
        $sessionsThisWeek = ClassSession::whereHas('class', function ($q) use ($teacher) {
            $q->where('teacher_id', $teacher->id);
        })->whereBetween('session_date', [$weekStart, $weekEnd])->count();

        // Sessions this month
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $sessionsThisMonth = ClassSession::whereHas('class', function ($q) use ($teacher) {
            $q->where('teacher_id', $teacher->id);
        })->whereBetween('session_date', [$monthStart, $monthEnd])->count();

        // Upcoming sessions
        $upcomingSessions = ClassSession::whereHas('class', function ($q) use ($teacher) {
            $q->where('teacher_id', $teacher->id);
        })->where('session_date', '>=', $now->startOfDay())
            ->where('status', 'scheduled')->count();

        // Completed sessions this month
        $completedThisMonth = ClassSession::whereHas('class', function ($q) use ($teacher) {
            $q->where('teacher_id', $teacher->id);
        })->whereBetween('session_date', [$monthStart, $monthEnd])
            ->where('status', 'completed')->count();

        return [
            'sessions_this_week' => $sessionsThisWeek,
            'sessions_this_month' => $sessionsThisMonth,
            'upcoming_sessions' => $upcomingSessions,
            'completed_this_month' => $completedThisMonth,
        ];
    }

    private function getEmptyStatistics()
    {
        return [
            'sessions_this_week' => 0,
            'sessions_this_month' => 0,
            'upcoming_sessions' => 0,
            'completed_this_month' => 0,
        ];
    }

    private function getCurrentPeriodLabel()
    {
        switch ($this->currentView) {
            case 'week':
                $start = $this->currentDate->copy()->startOfWeek();
                $end = $this->currentDate->copy()->endOfWeek();

                return $start->format('M d').' - '.$end->format('M d, Y');
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
            $daySessions = $sessions->filter(function ($session) use ($date) {
                return $session->session_date->isSameDay($date);
            })->sortBy('session_time')->values();

            // Get all scheduled times for this day across all classes
            $scheduledSlots = [];
            if ($teacher) {
                foreach ($teacher->classes as $class) {
                    $timetable = $class->timetable;
                    if ($timetable && $timetable->weekly_schedule && $timetable->is_active) {
                        // Check if the date is within the timetable's valid date range
                        if (! $timetable->isDateWithinRange($date)) {
                            continue;
                        }

                        // Get times for this day based on recurrence pattern
                        $timesForDay = [];

                        if ($timetable->recurrence_pattern === 'monthly') {
                            // For monthly pattern, get week of month and check that week's schedule
                            $dayOfMonth = $date->day;
                            if ($dayOfMonth <= 7) {
                                $weekKey = 'week_1';
                            } elseif ($dayOfMonth <= 14) {
                                $weekKey = 'week_2';
                            } elseif ($dayOfMonth <= 21) {
                                $weekKey = 'week_3';
                            } else {
                                $weekKey = 'week_4';
                            }

                            if (isset($timetable->weekly_schedule[$weekKey][$dayName]) && ! empty($timetable->weekly_schedule[$weekKey][$dayName])) {
                                $timesForDay = $timetable->weekly_schedule[$weekKey][$dayName];
                            }
                        } else {
                            // For weekly/bi-weekly pattern
                            if (isset($timetable->weekly_schedule[$dayName]) && ! empty($timetable->weekly_schedule[$dayName])) {
                                $timesForDay = $timetable->weekly_schedule[$dayName];
                            }
                        }

                        foreach ($timesForDay as $time) {
                            // Determine if this is the first or last scheduled date
                            $isFirstClass = $timetable->start_date && $date->isSameDay($timetable->start_date);
                            $isLastClass = $timetable->end_date && $date->isSameDay($timetable->end_date);

                            $scheduledSlots[] = [
                                'time' => $time,
                                'class' => $class,
                                'session' => $daySessions->first(function ($session) use ($time, $class) {
                                    return $session->session_time->format('H:i') === $time
                                        && $session->class_id === $class->id;
                                }),
                                'isFirstClass' => $isFirstClass,
                                'isLastClass' => $isLastClass,
                                'startDate' => $timetable->start_date,
                                'endDate' => $timetable->end_date,
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
                'dayNumber' => $date->format('j'),
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
                $daySessions = $sessions->filter(function ($session) use ($date) {
                    return $session->session_date->isSameDay($date);
                })->count();

                $week[] = [
                    'date' => $date,
                    'sessionCount' => $daySessions,
                    'isCurrentMonth' => $date->month === $this->currentDate->month,
                    'isToday' => $date->isToday(),
                    'dayNumber' => $date->format('j'),
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
            $slotSessions = $sessions->filter(function ($session) use ($hour) {
                return $session->session_time->format('H') == $hour;
            });

            $timeSlots[] = [
                'time' => $time,
                'displayTime' => Carbon::createFromFormat('H:i', $time)->format('g A'),
                'sessions' => $slotSessions,
            ];
        }

        return $timeSlots;
    }
}; ?>

<div class="teacher-app w-full" x-data="{
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
    {{-- ──────────────────────────────────────────────────────────
         PAGE HEADER
         ────────────────────────────────────────────────────────── --}}
    <x-teacher.page-header
        title="Timetable"
        subtitle="Your weekly schedule and sessions at a glance"
    />

    {{-- ──────────────────────────────────────────────────────────
         STAT STRIP  -  4 colourful tone cards
         ────────────────────────────────────────────────────────── --}}
    <div class="mb-6 grid gap-4 grid-cols-2 lg:grid-cols-4">
        <x-teacher.stat-card
            eyebrow="This Week"
            :value="$statistics['sessions_this_week']"
            tone="indigo"
            icon="calendar-days"
        >
            <span class="text-violet-700/80 dark:text-violet-300/80 font-medium">{{ Str::plural('session', $statistics['sessions_this_week']) }}</span>
        </x-teacher.stat-card>

        <x-teacher.stat-card
            eyebrow="This Month"
            :value="$statistics['sessions_this_month']"
            tone="emerald"
            icon="calendar"
        >
            <span class="text-emerald-700/80 dark:text-emerald-300/80 font-medium">{{ Str::plural('session', $statistics['sessions_this_month']) }}</span>
        </x-teacher.stat-card>

        <x-teacher.stat-card
            eyebrow="Upcoming"
            :value="$statistics['upcoming_sessions']"
            tone="violet"
            icon="clock"
        >
            <span class="text-violet-700/80 dark:text-violet-300/80 font-medium">scheduled ahead</span>
        </x-teacher.stat-card>

        <x-teacher.stat-card
            eyebrow="Completed"
            :value="$statistics['completed_this_month']"
            tone="amber"
            icon="check-circle"
        >
            <span class="text-amber-700/80 dark:text-amber-300/80 font-medium">this month</span>
        </x-teacher.stat-card>
    </div>

    {{-- ──────────────────────────────────────────────────────────
         CONTROL BAR  -  view switcher + date nav + filters
         ────────────────────────────────────────────────────────── --}}
    <div class="teacher-card mb-6 p-4 sm:p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            {{-- View-mode segmented control --}}
            <div class="inline-flex flex-wrap items-center gap-1 rounded-xl bg-slate-100 dark:bg-zinc-800/60 p-1 ring-1 ring-slate-200/70 dark:ring-zinc-700/60">
                @php
                    $viewModes = [
                        'day'   => ['label' => 'Day',   'icon' => 'calendar'],
                        'week'  => ['label' => 'Week',  'icon' => 'calendar-days'],
                        'month' => ['label' => 'Month', 'icon' => 'calendar'],
                        'list'  => ['label' => 'List',  'icon' => 'sparkles'],
                    ];
                @endphp
                @foreach($viewModes as $mode => $cfg)
                    @php $isActive = $currentView === $mode; @endphp
                    <button
                        type="button"
                        wire:click="$set('currentView', '{{ $mode }}')"
                        class="inline-flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-sm font-semibold transition-all
                            @if($isActive)
                                bg-gradient-to-r from-violet-600 to-violet-500 text-white shadow-md shadow-violet-500/30
                            @else
                                text-slate-600 hover:text-violet-700 hover:bg-violet-50 dark:text-zinc-300 dark:hover:text-violet-300 dark:hover:bg-violet-500/10
                            @endif
                        "
                    >
                        <flux:icon name="{{ $cfg['icon'] }}" class="w-3.5 h-3.5" />
                        {{ $cfg['label'] }}
                    </button>
                @endforeach
            </div>

            {{-- Date navigation --}}
            <div class="flex flex-wrap items-center justify-between gap-3 lg:justify-end">
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="previousPeriod" class="teacher-cta-ghost !px-3 !py-2" aria-label="Previous">
                        <flux:icon name="chevron-left" class="w-4 h-4" />
                    </button>

                    <button type="button" wire:click="goToToday" class="teacher-cta !px-4 !py-2 !text-xs">
                        <flux:icon name="calendar-days" class="w-4 h-4" />
                        Today
                    </button>

                    <button type="button" wire:click="nextPeriod" class="teacher-cta-ghost !px-3 !py-2" aria-label="Next">
                        <flux:icon name="chevron-right" class="w-4 h-4" />
                    </button>
                </div>

                <div class="teacher-display font-bold text-sm sm:text-base text-slate-900 dark:text-white tracking-tight px-3 py-1.5 rounded-lg bg-violet-50/70 dark:bg-violet-500/10 ring-1 ring-violet-100 dark:ring-violet-500/20">
                    {{ $currentPeriodLabel }}
                </div>
            </div>
        </div>

        {{-- Filter bar --}}
        <div class="mt-4 pt-4 border-t border-slate-200/70 dark:border-zinc-800 flex flex-col sm:flex-row sm:items-center gap-3">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400 shrink-0">
                Filter
            </span>
            <div class="flex flex-wrap items-center gap-2 flex-1">
                <flux:select wire:model.live="classFilter" size="sm" placeholder="All Classes" class="min-w-[160px]">
                    <option value="all">All Classes</option>
                    @foreach($classes as $class)
                        <option value="{{ $class->id }}">{{ $class->title }}</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="statusFilter" size="sm" placeholder="All Status" class="min-w-[140px]">
                    <option value="all">All Status</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="ongoing">Ongoing</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </flux:select>
            </div>
        </div>
    </div>

    {{-- ──────────────────────────────────────────────────────────
         CALENDAR CONTENT  -  delegated to sub-views
         ────────────────────────────────────────────────────────── --}}
    <div class="teacher-card overflow-hidden" wire:poll.30s="$refresh">
        @if($currentView === 'week')
            @include('livewire.teacher.timetable.week-view', ['days' => $calendarData])
        @elseif($currentView === 'month')
            @include('livewire.teacher.timetable.month-view', ['weeks' => $calendarData])
        @elseif($currentView === 'day')
            @include('livewire.teacher.timetable.day-view', ['timeSlots' => $calendarData])
        @elseif($currentView === 'list')
            @include('livewire.teacher.timetable.list-view', ['sessions' => $sessions])
        @else
            <x-teacher.empty-state icon="calendar" title="Invalid view" message="Pick a view from the switcher above." />
        @endif
    </div>

    {{-- ──────────────────────────────────────────────────────────
         SESSION DETAILS MODAL  -  vibrant gradient redesign
         ────────────────────────────────────────────────────────── --}}
    <flux:modal wire:model="showModal" class="max-w-2xl !p-0 overflow-hidden" wire:poll.5s="$refresh">
        @if($selectedSession)
            @php
                $statusKey = $selectedSession->status;
                $statusBadge = match($statusKey) {
                    'completed' => ['bg' => 'bg-emerald-400/90', 'text' => 'text-emerald-950', 'icon' => 'check', 'label' => 'Completed'],
                    'ongoing'   => ['bg' => 'bg-emerald-400/95', 'text' => 'text-emerald-950', 'icon' => 'bolt',  'label' => 'Live now'],
                    'cancelled' => ['bg' => 'bg-rose-400/90',    'text' => 'text-rose-950',    'icon' => 'x-mark','label' => 'Cancelled'],
                    'no_show'   => ['bg' => 'bg-amber-400/95',   'text' => 'text-amber-950',   'icon' => 'exclamation-triangle', 'label' => 'No-show'],
                    default     => ['bg' => 'bg-white/95',       'text' => 'text-violet-700',  'icon' => 'calendar', 'label' => 'Scheduled'],
                };
            @endphp

            <div class="teacher-app">
                {{-- HERO HEADER --}}
                <div class="teacher-modal-hero relative px-6 pt-6 pb-7 sm:px-8 sm:pt-8 sm:pb-9 text-white">
                    <div class="teacher-grain absolute inset-0 pointer-events-none"></div>

                    <button
                        type="button"
                        wire:click="closeModal"
                        class="absolute top-4 right-4 w-9 h-9 rounded-full bg-white/15 hover:bg-white/25 ring-1 ring-white/20 backdrop-blur flex items-center justify-center transition"
                        aria-label="Close"
                    >
                        <flux:icon name="x-mark" class="w-4 h-4 text-white" />
                    </button>

                    <div class="relative">
                        <span class="inline-flex items-center gap-1.5 rounded-full {{ $statusBadge['bg'] }} {{ $statusBadge['text'] }} px-3 py-1 text-xs font-bold ring-1 ring-white/40">
                            @if($statusKey === 'ongoing')
                                <span class="teacher-live-dot bg-emerald-700 !shadow-none"></span>
                            @else
                                <flux:icon name="{{ $statusBadge['icon'] }}" class="w-3 h-3" />
                            @endif
                            {{ $statusBadge['label'] }}
                        </span>

                        <h2 class="teacher-display mt-3 text-2xl sm:text-3xl font-bold leading-tight pr-10">
                            {{ $selectedSession->class->course->title ?? $selectedSession->class->course->name }}
                        </h2>
                        <p class="text-white/80 text-sm sm:text-base mt-1">
                            {{ $selectedSession->class->title }}
                        </p>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-medium ring-1 ring-white/20 backdrop-blur">
                                <flux:icon name="calendar" class="w-3.5 h-3.5" />
                                {{ $selectedSession->session_date->format('D, j M Y') }}
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-medium ring-1 ring-white/20 backdrop-blur">
                                <flux:icon name="clock" class="w-3.5 h-3.5" />
                                {{ $selectedSession->session_time->format('g:i A') }}
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-medium ring-1 ring-white/20 backdrop-blur">
                                <flux:icon name="bolt" class="w-3.5 h-3.5" />
                                {{ $selectedSession->formatted_duration }}
                            </span>
                            @if($selectedSession->started_by)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-medium ring-1 ring-white/20 backdrop-blur">
                                    <flux:icon name="play" class="w-3.5 h-3.5" />
                                    Started by {{ $selectedSession->starter->name ?? 'Unknown' }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- BODY --}}
                <div class="bg-white dark:bg-zinc-900 px-6 py-6 sm:px-8 sm:py-7 space-y-6 max-h-[60vh] overflow-y-auto">

                    {{-- Live timer --}}
                    @if($selectedSession->isOngoing())
                        <div class="teacher-modal-timer rounded-2xl px-5 py-5 sm:px-6">
                            <div class="relative flex items-center justify-between gap-4">
                                <div>
                                    <div class="flex items-center gap-2 text-emerald-100/95 text-xs font-bold uppercase tracking-[0.2em]">
                                        <span class="teacher-live-dot bg-emerald-300"></span>
                                        Session live
                                    </div>
                                    <p class="mt-1 text-emerald-50/80 text-sm">Timer started — focus mode on.</p>
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
                                    <div class="teacher-num text-3xl sm:text-4xl font-mono font-bold text-white tracking-tight" x-text="formatTime(modalTimer)"></div>
                                    <div class="text-emerald-200/80 text-[10px] font-bold uppercase tracking-[0.18em] mt-0.5">Elapsed</div>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Attendance grid --}}
                    @if($selectedSession->attendances->count() > 0)
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="teacher-display font-bold text-slate-900 dark:text-white text-sm">
                                    Students <span class="text-slate-400 dark:text-zinc-500 font-medium">({{ $selectedSession->attendances->count() }})</span>
                                </h3>
                            </div>
                            <div class="grid sm:grid-cols-2 gap-2">
                                @foreach($selectedSession->attendances as $i => $attendance)
                                    @php
                                        $statusTone = match($attendance->status) {
                                            'present' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
                                            'absent'  => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
                                            'late'    => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
                                            'excused' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
                                            default   => 'bg-slate-100 text-slate-600 dark:bg-zinc-700/50 dark:text-zinc-300',
                                        };
                                        $initials = collect(explode(' ', trim($attendance->student->user->name)))
                                            ->take(2)
                                            ->map(fn ($p) => strtoupper(substr($p, 0, 1)))
                                            ->join('');
                                        $avatarVariant = ($i % 6) + 1;
                                    @endphp
                                    <div class="flex items-center gap-3 rounded-xl px-3 py-2.5 ring-1 ring-slate-200/70 dark:ring-zinc-800 bg-slate-50/60 dark:bg-zinc-800/40 hover:ring-violet-300 dark:hover:ring-violet-700/60 transition">
                                        <div class="teacher-avatar teacher-avatar-{{ $avatarVariant }}">{{ $initials }}</div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ $attendance->student->user->name }}</p>
                                        </div>
                                        <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider {{ $statusTone }}">
                                            {{ $attendance->status_label }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Notes (display) --}}
                    @if($selectedSession->teacher_notes && !$showNotesField)
                        <div>
                            <h3 class="teacher-display font-bold text-slate-900 dark:text-white text-sm flex items-center gap-1.5 mb-2">
                                <flux:icon name="sparkles" class="w-4 h-4 text-violet-500" />
                                Session Notes
                            </h3>
                            <div class="rounded-xl px-4 py-3 bg-gradient-to-br from-violet-50/70 to-violet-50/40 dark:from-violet-950/30 dark:to-violet-950/20 ring-1 ring-violet-100/80 dark:ring-violet-900/40">
                                <p class="text-sm text-slate-700 dark:text-zinc-200 whitespace-pre-wrap leading-relaxed">{{ $selectedSession->teacher_notes }}</p>
                            </div>
                        </div>
                    @endif

                    {{-- Notes (edit) --}}
                    @if($showNotesField)
                        <div>
                            <h3 class="teacher-display font-bold text-slate-900 dark:text-white text-sm flex items-center gap-1.5 mb-1">
                                <flux:icon name="sparkles" class="w-4 h-4 text-violet-500" />
                                Add Session Notes
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-zinc-400 mb-3">
                                Notes are required before marking the session complete.
                            </p>
                            <textarea
                                wire:model="completionNotes"
                                placeholder="Summary, what was covered, next steps, anything worth remembering…"
                                rows="5"
                                class="w-full rounded-xl border-0 ring-1 ring-slate-200 dark:ring-zinc-700 bg-white dark:bg-zinc-900 text-sm text-slate-900 dark:text-zinc-100 placeholder:text-slate-400 dark:placeholder:text-zinc-500 px-4 py-3 focus:ring-2 focus:ring-violet-500 dark:focus:ring-violet-400 transition"
                            ></textarea>
                            @error('completionNotes')
                                <p class="text-xs text-rose-600 dark:text-rose-400 mt-1.5">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    {{-- Empty state --}}
                    @if(!$selectedSession->teacher_notes && !$showNotesField && $selectedSession->attendances->count() === 0 && !$selectedSession->isOngoing())
                        <div class="text-center py-6">
                            <flux:icon name="sparkles" class="w-8 h-8 text-violet-400 mx-auto mb-2" />
                            <p class="text-sm text-slate-500 dark:text-zinc-400">No additional details for this session yet.</p>
                        </div>
                    @endif
                </div>

                {{-- FOOTER ACTIONS --}}
                <div class="bg-slate-50/80 dark:bg-zinc-950/60 px-6 py-4 sm:px-8 border-t border-slate-200/70 dark:border-zinc-800 flex items-center justify-between gap-3">
                    <button type="button" wire:click="closeModal" class="teacher-cta-ghost">
                        Close
                    </button>

                    <div class="flex gap-2">
                        @if($selectedSession->isScheduled())
                            <button type="button" wire:click="requestStartSession({{ $selectedSession->id }})" class="teacher-cta">
                                <flux:icon name="play" class="w-4 h-4" />
                                Start Session
                            </button>
                        @elseif($selectedSession->isOngoing())
                            @if(!$showNotesField)
                                <button type="button" wire:click="showCompleteSessionForm" class="teacher-cta">
                                    <flux:icon name="check" class="w-4 h-4" />
                                    Complete Session
                                </button>
                            @else
                                <button type="button" wire:click="$set('showNotesField', false)" class="teacher-cta-ghost">
                                    Cancel
                                </button>
                                <button type="button" wire:click="completeSession" class="teacher-cta">
                                    <flux:icon name="check-circle" class="w-4 h-4" />
                                    Confirm Complete
                                </button>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- ──────────────────────────────────────────────────────────
         START SESSION CONFIRMATION MODAL
         ────────────────────────────────────────────────────────── --}}
    <flux:modal wire:model="showStartConfirmation" class="max-w-md !p-0 overflow-hidden">
        <div class="teacher-app">
            <div class="teacher-modal-stripe"></div>

            <div class="bg-white dark:bg-zinc-900 px-6 pt-8 pb-6 text-center">
                <div class="flex justify-center mb-5">
                    <div class="teacher-modal-orb">
                        <flux:icon name="play" class="w-9 h-9" variant="solid" />
                    </div>
                </div>

                <h2 class="teacher-display text-2xl font-bold text-slate-900 dark:text-white">
                    Start Session?
                </h2>
                <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400 leading-relaxed">
                    Once you start, the timer begins and you'll be able to manage attendance and notes.
                </p>

                @include('livewire.teacher._partials.start-session-briefing', ['briefing' => $this->startBriefing])

                <div class="mt-5 rounded-2xl bg-gradient-to-br from-violet-50 via-violet-100/60 to-violet-200/30 dark:from-violet-950/50 dark:via-violet-900/30 dark:to-violet-800/20 ring-1 ring-violet-100 dark:ring-violet-900/40 px-4 py-4 text-left">
                    <div class="flex items-start gap-3">
                        <div class="shrink-0 w-9 h-9 rounded-xl bg-gradient-to-br from-violet-600 to-violet-500 text-white shadow-lg shadow-violet-500/30 flex items-center justify-center">
                            <flux:icon name="bolt" class="w-4 h-4" variant="solid" />
                        </div>
                        <div class="flex-1 text-xs leading-relaxed">
                            <p class="font-semibold text-violet-900 dark:text-violet-200 text-[13px] mb-0.5">You're all set</p>
                            <p class="text-violet-700/80 dark:text-violet-300/80">Timer starts immediately and runs in real-time.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-slate-50/80 dark:bg-zinc-950/60 px-6 py-4 border-t border-slate-200/70 dark:border-zinc-800 flex gap-2 justify-end">
                <button type="button" wire:click="closeStartConfirmation" class="teacher-cta-ghost">
                    Cancel
                </button>
                <button type="button" wire:click="confirmStartSession" class="teacher-cta">
                    <flux:icon name="play" class="w-4 h-4" variant="solid" />
                    Yes, Start Session
                </button>
            </div>
        </div>
    </flux:modal>
</div>