<?php

use App\Models\ClassModel;
use App\Models\ClassSession;
use App\Support\TeacherStartBriefing;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.teacher')] class extends Component
{
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
        if (! auth()->user()->isTeacher()) {
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

    public function getStartBriefingProperty(): ?array
    {
        if ($this->sessionToStartId) {
            $session = ClassSession::with(['class.course', 'class.pics', 'class.activeStudents'])->find($this->sessionToStartId);

            return TeacherStartBriefing::build($session, $session?->class);
        }

        if ($this->classToStartId && $this->timeToStart) {
            $class = ClassModel::with(['course', 'pics', 'activeStudents'])->find($this->classToStartId);

            return TeacherStartBriefing::build(null, $class, Carbon::parse(today()->toDateString().' '.$this->timeToStart));
        }

        return null;
    }

    // Today's sessions for the teacher (including scheduled slots from timetables)
    public function getTodaySessionsProperty()
    {
        $teacher = auth()->user()->teacher;

        if (! $teacher) {
            return collect();
        }

        $today = now();
        $dayName = strtolower($today->format('l')); // e.g., 'monday', 'tuesday'

        // Get existing sessions for today
        $existingSessions = $teacher->classes()
            ->with(['sessions' => function ($query) {
                $query->today()->with(['class.course', 'attendances.student.user']);
            }])
            ->get()
            ->flatMap->sessions;

        // Get all classes with their timetables
        $classes = $teacher->classes()->with(['course', 'timetable'])->get();

        // Create collection to hold combined items
        $combinedSessions = collect();

        // Add existing sessions
        foreach ($existingSessions as $session) {
            $combinedSessions->push([
                'type' => 'session',
                'session' => $session,
                'class' => $session->class,
                'time' => $session->session_time->format('H:i'),
                'display_time' => $session->session_time->format('g:i A'),
                'duration' => $session->duration_minutes,
                'status' => $session->status,
                'is_scheduled_slot' => false,
            ]);
        }

        // Add scheduled slots from timetables that don't have existing sessions
        foreach ($classes as $class) {
            if ($class->timetable && $class->timetable->weekly_schedule && isset($class->timetable->weekly_schedule[$dayName])) {
                foreach ($class->timetable->weekly_schedule[$dayName] as $time) {
                    // Check if there's already an existing session for this time/class
                    $hasExistingSession = $existingSessions->first(function ($session) use ($time, $class) {
                        return $session->session_time->format('H:i') === $time
                            && $session->class_id === $class->id;
                    });

                    // If no existing session, add as scheduled slot
                    if (! $hasExistingSession) {
                        $combinedSessions->push([
                            'type' => 'scheduled_slot',
                            'session' => null,
                            'class' => $class,
                            'time' => $time,
                            'display_time' => \Carbon\Carbon::parse($time)->format('g:i A'),
                            'duration' => $class->duration_minutes ?? 60,
                            'status' => 'scheduled',
                            'is_scheduled_slot' => true,
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

        if (! $teacher) {
            return [
                'total_sessions' => 0,
                'completed_sessions' => 0,
                'total_earnings' => 0,
                'total_students' => 0,
                'attendance_rate' => 0,
            ];
        }

        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();

        $weekSessions = ClassSession::whereHas('class', function ($query) use ($teacher) {
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
            'attendance_rate' => $this->calculateWeeklyAttendanceRate($weekSessions),
        ];
    }

    // Monthly earnings
    public function getMonthlyEarningsProperty()
    {
        $teacher = auth()->user()->teacher;

        if (! $teacher) {
            return 0;
        }

        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        return ClassSession::whereHas('class', function ($query) use ($teacher) {
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

        if (! $teacher) {
            return collect();
        }

        $upcomingDates = collect();

        // Get next 7 days
        for ($i = 1; $i <= 7; $i++) {
            $date = now()->addDays($i);
            $dayName = strtolower($date->format('l'));

            // Get existing sessions for this date
            $existingSessions = $teacher->classes()
                ->with(['sessions' => function ($query) use ($date) {
                    $query->whereDate('session_date', $date->toDateString())
                        ->with(['class.course']);
                }])
                ->get()
                ->flatMap->sessions;

            // Get classes with timetables for this day
            $classes = $teacher->classes()->with(['course', 'timetable'])->get();

            foreach ($classes as $class) {
                if ($class->timetable && $class->timetable->weekly_schedule && isset($class->timetable->weekly_schedule[$dayName])) {
                    foreach ($class->timetable->weekly_schedule[$dayName] as $time) {
                        // Check if session already exists
                        $existingSession = $existingSessions->first(function ($session) use ($time, $class) {
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
                                'sort_key' => $date->format('Y-m-d').' '.$time,
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
                                'sort_key' => $date->format('Y-m-d').' '.$time,
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

        if (! $teacher) {
            return collect();
        }

        $recentSessions = ClassSession::whereHas('class', function ($query) use ($teacher) {
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
                'type' => 'session_'.$session->status,
                'message' => $this->getSessionActivityMessage($session),
                'time' => $session->updated_at->diffForHumans(),
                'icon' => $this->getSessionActivityIcon($session->status),
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
                session()->flash('error', 'Session cannot be started (status: '.$existingSession->status.')');
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

        $totalPossibleAttendance = $completedSessions->sum(function ($session) {
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
        return match ($session->status) {
            'completed' => "Completed session for {$session->class->course->name}",
            'cancelled' => "Cancelled session for {$session->class->course->name}",
            'no_show' => "No-show session for {$session->class->course->name}",
            default => "Session updated for {$session->class->course->name}"
        };
    }

    private function getSessionActivityIcon($status)
    {
        return match ($status) {
            'completed' => 'check-circle',
            'cancelled' => 'x-circle',
            'no_show' => 'exclamation-triangle',
            default => 'information-circle'
        };
    }
}; ?>

@php
    $todaySessions = $this->todaySessions;
    $nextSession = $todaySessions->first(fn ($i) => in_array($i['status'], ['scheduled', 'ongoing']));
    $ongoingCount = $todaySessions->where('status', 'ongoing')->count();
    $remainingCount = $todaySessions->whereIn('status', ['scheduled', 'ongoing'])->count();
    $weekly = $this->weeklyStats;
    $statusBorderClass = [
        'scheduled' => 'border-l-violet-500',
        'ongoing'   => 'border-l-emerald-500',
        'completed' => 'border-l-slate-300 dark:border-l-zinc-700',
        'cancelled' => 'border-l-rose-500',
        'no_show'   => 'border-l-amber-500',
    ];
    $hour = (int) now()->format('H');
    $greeting = $hour < 12 ? 'Selamat pagi' : ($hour < 18 ? 'Selamat petang' : 'Selamat malam');
@endphp

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
">
    {{-- ──────────────────────────────────────────────────────────
         HERO  -  gradient greeting card + next session CTA
         ────────────────────────────────────────────────────────── --}}
    <div class="teacher-hero relative overflow-hidden rounded-2xl text-white shadow-[0_18px_48px_-16px_rgba(79,70,229,0.55)]">
        <div class="teacher-grain absolute inset-0 pointer-events-none"></div>

        <div class="relative px-6 py-8 sm:px-8 sm:py-10 lg:py-12 lg:px-10 grid gap-8 lg:grid-cols-[1.4fr_1fr] items-center">
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-medium tracking-wide ring-1 ring-white/25 backdrop-blur">
                        <span class="teacher-live-dot"></span>
                        {{ now()->format('l, j F Y') }}
                    </span>
                    @if($ongoingCount > 0)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-400/90 px-3 py-1 text-xs font-semibold text-emerald-950">
                            {{ $ongoingCount }} ongoing now
                        </span>
                    @endif
                </div>

                <h1 class="teacher-display text-3xl sm:text-4xl lg:text-5xl font-bold leading-tight">
                    {{ $greeting }}, <span class="text-white/95">{{ explode(' ', auth()->user()->name)[0] }}</span>
                    <span class="inline-block animate-pulse">👋</span>
                </h1>
                <p class="mt-3 text-white/80 text-base sm:text-lg max-w-lg">
                    @if($remainingCount > 0)
                        You have <span class="font-semibold text-white">{{ $remainingCount }}</span> {{ Str::plural('session', $remainingCount) }} left today. Let's make them count.
                    @elseif($todaySessions->isNotEmpty())
                        All today's sessions are wrapped up. Nicely done — take a breather.
                    @else
                        No sessions today. Plan ahead or take a well-deserved break.
                    @endif
                </p>
            </div>

            {{-- Next session CTA card --}}
            @if($nextSession)
                <div class="teacher-pill-gradient rounded-2xl p-5 sm:p-6 shadow-xl shadow-black/10">
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <span class="text-[11px] font-bold tracking-[0.18em] text-violet-700 uppercase">
                            @if($nextSession['status'] === 'ongoing') In Session @else Up Next @endif
                        </span>
                        <span class="text-xs font-semibold text-violet-600 bg-violet-100 rounded-full px-2.5 py-1">
                            {{ $nextSession['display_time'] }}
                        </span>
                    </div>
                    <h3 class="teacher-display text-lg font-bold text-slate-900 leading-snug">
                        {{ $nextSession['class']->course->title ?? $nextSession['class']->course->name }}
                    </h3>
                    <p class="text-sm text-slate-600 mt-0.5">
                        {{ $nextSession['class']->title }} · {{ $nextSession['class']->activeStudents()->count() }} {{ Str::plural('student', $nextSession['class']->activeStudents()->count()) }}
                    </p>

                    @if(!$nextSession['is_scheduled_slot'] && $nextSession['session']->isOngoing())
                        <div class="mt-3 flex items-center gap-2.5 rounded-xl bg-emerald-500/10 ring-1 ring-emerald-500/30 px-3 py-2"
                             x-data="{
                                heroElapsed: 0,
                                heroTimer: null,
                                formatHero(seconds) {
                                    const hours = Math.floor(seconds / 3600);
                                    const minutes = Math.floor((seconds % 3600) / 60);
                                    const secs = seconds % 60;
                                    if (hours > 0) {
                                        return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
                                    } else {
                                        return `${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
                                    }
                                }
                             }"
                             x-init="
                                const startTime = new Date('{{ $nextSession['session']->started_at ? $nextSession['session']->started_at->toISOString() : now()->toISOString() }}').getTime();
                                heroElapsed = Math.floor((Date.now() - startTime) / 1000);
                                heroTimer = setInterval(() => {
                                    heroElapsed = Math.floor((Date.now() - startTime) / 1000);
                                }, 1000);
                             "
                             x-destroy="heroTimer && clearInterval(heroTimer)">
                            <span class="teacher-live-dot"></span>
                            <span class="text-[11px] font-bold uppercase tracking-[0.18em] text-emerald-700">Live</span>
                            <span class="ml-auto teacher-num font-mono text-base font-bold text-emerald-700" x-text="formatHero(heroElapsed)"></span>
                        </div>
                    @endif

                    <div class="mt-4 flex items-center gap-2">
                        @if($nextSession['is_scheduled_slot'])
                            <button
                                type="button"
                                wire:click="requestStartSessionFromScheduledSlot({{ $nextSession['class']->id }}, '{{ $nextSession['time'] }}')"
                                class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-violet-700 via-violet-600 to-violet-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-violet-500/30 hover:shadow-xl hover:shadow-violet-500/40 hover:-translate-y-px transition-all"
                            >
                                <flux:icon name="play" class="w-4 h-4" />
                                Start Session
                            </button>
                        @elseif($nextSession['session']->isScheduled())
                            <button
                                type="button"
                                wire:click="requestStartSession({{ $nextSession['session']->id }})"
                                class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-violet-700 via-violet-600 to-violet-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-violet-500/30 hover:shadow-xl hover:shadow-violet-500/40 hover:-translate-y-px transition-all"
                            >
                                <flux:icon name="play" class="w-4 h-4" />
                                Start Session
                            </button>
                        @elseif($nextSession['session']->isOngoing())
                            <button
                                type="button"
                                wire:click="selectSession({{ $nextSession['session']->id }})"
                                class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-emerald-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-emerald-500/30 hover:bg-emerald-600 hover:-translate-y-px transition-all"
                            >
                                <span class="teacher-live-dot bg-white !shadow-none"></span>
                                Continue
                            </button>
                        @endif
                        <a href="{{ route('teacher.timetable') }}" wire:navigate class="inline-flex items-center justify-center rounded-xl px-3 py-2.5 text-sm font-semibold text-violet-700 hover:bg-violet-50 transition">
                            <flux:icon name="calendar" class="w-4 h-4" />
                        </a>
                    </div>
                </div>
            @else
                <div class="teacher-pill-gradient rounded-2xl p-6 shadow-xl shadow-black/10 text-center">
                    <flux:icon name="sparkles" class="w-8 h-8 text-violet-500 mx-auto mb-2" />
                    <h3 class="teacher-display font-bold text-slate-900">All clear</h3>
                    <p class="text-sm text-slate-600 mt-1">No more sessions today.</p>
                    <a href="{{ route('teacher.timetable') }}" wire:navigate class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-violet-600 hover:text-violet-700">
                        View full timetable
                        <flux:icon name="arrow-right" class="w-4 h-4" />
                    </a>
                </div>
            @endif
        </div>
    </div>

    {{-- ──────────────────────────────────────────────────────────
         STAT STRIP  -  4 colorful gradient-tinted cards
         ────────────────────────────────────────────────────────── --}}
    <div class="mt-6 grid gap-4 grid-cols-2 lg:grid-cols-4">
        {{-- Today --}}
        <div class="teacher-card teacher-card-hover teacher-stat teacher-stat-indigo teacher-stat-hover p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-violet-700/80 dark:text-violet-300/90">Today</span>
                <div class="rounded-lg bg-violet-500/10 dark:bg-violet-400/15 p-1.5">
                    <flux:icon name="calendar-days" class="w-4 h-4 text-violet-600 dark:text-violet-300" />
                </div>
            </div>
            <div class="teacher-display teacher-num text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white">{{ $todaySessions->count() }}</div>
            <div class="mt-1 flex items-center gap-1.5 text-xs">
                <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ $todaySessions->where('status', 'completed')->count() }} completed</span>
                <span class="text-slate-400 dark:text-zinc-500">/ {{ $todaySessions->count() }} total</span>
            </div>
        </div>

        {{-- Week earnings --}}
        <div class="teacher-card teacher-card-hover teacher-stat teacher-stat-emerald teacher-stat-hover p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700/80 dark:text-emerald-300/90">Week Earnings</span>
                <div class="rounded-lg bg-emerald-500/10 dark:bg-emerald-400/15 p-1.5">
                    <flux:icon name="banknotes" class="w-4 h-4 text-emerald-600 dark:text-emerald-300" />
                </div>
            </div>
            <div class="teacher-display teacher-num text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white">
                <span class="text-base font-semibold text-emerald-700 dark:text-emerald-300 align-top">RM</span> {{ number_format($weekly['total_earnings'], 2) }}
            </div>
            <div class="mt-1 text-xs text-emerald-700/80 dark:text-emerald-300/80 font-medium">
                {{ $weekly['completed_sessions'] }} {{ Str::plural('session', $weekly['completed_sessions']) }} completed
            </div>
        </div>

        {{-- Active students --}}
        <div class="teacher-card teacher-card-hover teacher-stat teacher-stat-violet teacher-stat-hover p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-violet-700/80 dark:text-violet-300/90">Students</span>
                <div class="rounded-lg bg-violet-500/10 dark:bg-violet-400/15 p-1.5">
                    <flux:icon name="users" class="w-4 h-4 text-violet-600 dark:text-violet-300" />
                </div>
            </div>
            <div class="teacher-display teacher-num text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white">{{ $weekly['total_students'] }}</div>
            <div class="mt-1 text-xs text-violet-700/80 dark:text-violet-300/80 font-medium">
                Across all classes
            </div>
        </div>

        {{-- Attendance --}}
        <div class="teacher-card teacher-card-hover teacher-stat teacher-stat-amber teacher-stat-hover p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-amber-700/80 dark:text-amber-300/90">Attendance</span>
                <div class="rounded-lg bg-amber-500/10 dark:bg-amber-400/15 p-1.5">
                    <flux:icon name="chart-pie" class="w-4 h-4 text-amber-600 dark:text-amber-300" />
                </div>
            </div>
            <div class="teacher-display teacher-num text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white">{{ $weekly['attendance_rate'] }}%</div>
            <div class="mt-2 h-1.5 w-full rounded-full bg-amber-200/50 dark:bg-amber-900/40 overflow-hidden">
                <div class="h-full rounded-full bg-gradient-to-r from-amber-400 to-orange-500" style="width: {{ min(100, $weekly['attendance_rate']) }}%"></div>
            </div>
        </div>
    </div>

    {{-- ──────────────────────────────────────────────────────────
         MAIN GRID  -  Schedule timeline (2 cols) + Right rail
         ────────────────────────────────────────────────────────── --}}
    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- TIMELINE --}}
        <div class="lg:col-span-2 teacher-card p-5 sm:p-6">
            <div class="flex items-start justify-between mb-5">
                <div>
                    <h2 class="teacher-display text-xl font-bold text-slate-900 dark:text-white">Today's Schedule</h2>
                    <p class="text-sm text-slate-500 dark:text-zinc-400 mt-0.5">{{ now()->format('l, F j') }}</p>
                </div>
                <a href="{{ route('teacher.timetable') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-semibold text-violet-600 dark:text-violet-400 hover:text-violet-600 dark:hover:text-violet-300 transition">
                    View timetable
                    <flux:icon name="arrow-right" class="w-4 h-4" />
                </a>
            </div>

            @if($todaySessions->isNotEmpty())
                <div class="space-y-3">
                    @foreach($todaySessions as $item)
                        @php
                            $isOngoing = !$item['is_scheduled_slot'] && $item['session']->isOngoing();
                            $isCompleted = !$item['is_scheduled_slot'] && $item['session']->isCompleted();
                            $borderClass = $isOngoing ? 'border-l-emerald-500' : ($isCompleted ? 'border-l-slate-300 dark:border-l-zinc-600' : 'border-l-violet-500');
                            $cardTone = $isOngoing
                                ? 'bg-emerald-50/70 dark:bg-emerald-950/20 ring-emerald-200/60 dark:ring-emerald-800/40'
                                : ($isCompleted
                                    ? 'bg-slate-50 dark:bg-zinc-900/40 ring-slate-200/60 dark:ring-zinc-800'
                                    : 'bg-white dark:bg-zinc-900/40 ring-slate-200/70 dark:ring-zinc-800');
                        @endphp
                        <div wire:key="schedule-{{ $item['is_scheduled_slot'] ? 'slot-'.$item['class']->id.'-'.$item['time'] : 'sess-'.$item['session']->id }}"
                             class="group relative flex flex-col sm:flex-row sm:items-center gap-3 rounded-xl border-l-4 {{ $borderClass }} {{ $cardTone }} ring-1 px-4 py-3.5 hover:shadow-md hover:-translate-y-px transition-all">

                            {{-- Time block --}}
                            <div class="flex sm:flex-col sm:w-[72px] sm:items-start gap-2 sm:gap-0 shrink-0">
                                <div class="teacher-display teacher-num text-base font-bold text-slate-900 dark:text-white">
                                    {{ $item['display_time'] }}
                                </div>
                                <div class="text-xs text-slate-500 dark:text-zinc-400">
                                    @if($item['is_scheduled_slot'])
                                        {{ $item['duration'] }} min
                                    @else
                                        {{ $item['session']->formatted_duration }}
                                    @endif
                                </div>
                            </div>

                            {{-- Class info --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h3 class="font-semibold text-slate-900 dark:text-white truncate">
                                        {{ $item['class']->course->title ?? $item['class']->course->name }}
                                    </h3>

                                    {{-- Status pill --}}
                                    @if($item['is_scheduled_slot'])
                                        <span class="inline-flex items-center gap-1 rounded-full bg-violet-100 dark:bg-violet-500/15 text-violet-700 dark:text-violet-300 px-2 py-0.5 text-[11px] font-semibold">
                                            <flux:icon name="calendar" class="w-3 h-3" />
                                            Scheduled
                                        </span>
                                    @elseif($isOngoing)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 px-2 py-0.5 text-[11px] font-semibold">
                                            <span class="teacher-live-dot"></span>
                                            Live
                                        </span>
                                    @elseif($isCompleted)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 dark:bg-zinc-700/40 text-slate-600 dark:text-zinc-300 px-2 py-0.5 text-[11px] font-semibold">
                                            <flux:icon name="check" class="w-3 h-3" />
                                            Completed
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-violet-100 dark:bg-violet-500/15 text-violet-700 dark:text-violet-300 px-2 py-0.5 text-[11px] font-semibold">
                                            {{ ucfirst($item['status']) }}
                                        </span>
                                    @endif
                                </div>
                                <p class="text-xs text-slate-500 dark:text-zinc-400 mt-1 truncate">
                                    {{ $item['class']->title }}
                                    · {{ $item['class']->activeStudents()->count() }} {{ Str::plural('student', $item['class']->activeStudents()->count()) }}
                                    · {{ $item['class']->location ?? 'Online' }}
                                </p>
                            </div>

                            {{-- Action --}}
                            <div class="flex items-center gap-2 sm:justify-end shrink-0">
                                @if($item['is_scheduled_slot'])
                                    <button
                                        type="button"
                                        wire:click="requestStartSessionFromScheduledSlot({{ $item['class']->id }}, '{{ $item['time'] }}')"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-r from-violet-700 to-violet-600 px-3.5 py-2 text-xs font-semibold text-white shadow-md shadow-violet-500/25 hover:shadow-lg hover:from-violet-600 hover:to-violet-400 transition"
                                    >
                                        <flux:icon name="play" class="w-3.5 h-3.5" />
                                        Start
                                    </button>
                                @else
                                    @if($item['session']->isScheduled())
                                        <button
                                            type="button"
                                            wire:click="requestStartSession({{ $item['session']->id }})"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-r from-violet-700 to-violet-600 px-3.5 py-2 text-xs font-semibold text-white shadow-md shadow-violet-500/25 hover:shadow-lg hover:from-violet-600 hover:to-violet-400 transition"
                                        >
                                            <flux:icon name="play" class="w-3.5 h-3.5" />
                                            Start
                                        </button>
                                    @elseif($isOngoing)
                                        <div class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-100 dark:bg-emerald-500/15 px-2.5 py-1.5 ring-1 ring-emerald-200/70 dark:ring-emerald-800/50"
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
                                            <span class="teacher-live-dot"></span>
                                            <span class="font-mono font-bold text-xs text-emerald-700 dark:text-emerald-300" x-text="formatTime(elapsedTime)"></span>
                                        </div>
                                        <button
                                            type="button"
                                            wire:click="selectSession({{ $item['session']->id }})"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-r from-emerald-500 to-teal-600 px-3.5 py-2 text-xs font-semibold text-white shadow-md shadow-emerald-500/25 hover:shadow-lg transition"
                                        >
                                            <flux:icon name="check" class="w-3.5 h-3.5" />
                                            Complete
                                        </button>
                                    @else
                                        <button
                                            type="button"
                                            wire:click="selectSession({{ $item['session']->id }})"
                                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold text-slate-600 dark:text-zinc-300 ring-1 ring-slate-200 dark:ring-zinc-700 hover:bg-slate-50 dark:hover:bg-zinc-800 transition"
                                        >
                                            <flux:icon name="eye" class="w-3.5 h-3.5" />
                                            View
                                        </button>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-14 rounded-xl bg-gradient-to-br from-violet-50 to-violet-50 dark:from-violet-950/30 dark:to-violet-950/30 ring-1 ring-violet-100 dark:ring-violet-900/30">
                    <div class="inline-flex w-14 h-14 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-600 to-violet-400 text-white shadow-lg shadow-violet-500/30 mb-4">
                        <flux:icon name="calendar-days" class="w-7 h-7" />
                    </div>
                    <h3 class="teacher-display text-lg font-bold text-slate-900 dark:text-white">No sessions today</h3>
                    <p class="text-sm text-slate-500 dark:text-zinc-400 mt-1">Take a well-deserved break!</p>
                </div>
            @endif
        </div>

        {{-- ────────────────────────────────────────────
             RIGHT RAIL
             ──────────────────────────────────────────── --}}
        <div class="space-y-6">
            {{-- This Month --}}
            <div class="teacher-card p-5 sm:p-6 relative overflow-hidden">
                <div class="absolute -top-12 -right-12 w-44 h-44 rounded-full bg-gradient-to-br from-emerald-400/30 to-teal-500/20 blur-2xl pointer-events-none"></div>
                <div class="relative">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="teacher-display text-base font-bold text-slate-900 dark:text-white">This Month</h3>
                        <span class="text-xs font-medium text-slate-500 dark:text-zinc-400">{{ now()->format('F') }}</span>
                    </div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-emerald-700/80 dark:text-emerald-300/90 mb-1">Total Earnings</div>
                    <div class="teacher-display teacher-num text-4xl font-bold bg-gradient-to-r from-emerald-600 to-teal-600 dark:from-emerald-400 dark:to-teal-300 bg-clip-text text-transparent">
                        RM {{ number_format($this->monthlyEarnings, 2) }}
                    </div>

                    <div class="mt-5 grid grid-cols-2 gap-3">
                        <div class="rounded-xl bg-slate-50 dark:bg-zinc-800/50 px-3 py-2.5">
                            <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">Sessions</div>
                            <div class="teacher-num text-lg font-bold text-slate-900 dark:text-white mt-0.5">{{ $weekly['completed_sessions'] }}</div>
                        </div>
                        <div class="rounded-xl bg-slate-50 dark:bg-zinc-800/50 px-3 py-2.5">
                            <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">Attend.</div>
                            <div class="teacher-num text-lg font-bold text-slate-900 dark:text-white mt-0.5">{{ $weekly['attendance_rate'] }}%</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Recent Activity --}}
            <div class="teacher-card p-5 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="teacher-display text-base font-bold text-slate-900 dark:text-white">Recent Activity</h3>
                    <flux:icon name="clock" class="w-4 h-4 text-slate-400 dark:text-zinc-500" />
                </div>

                @if($this->recentActivities->isNotEmpty())
                    <div class="space-y-3">
                        @foreach($this->recentActivities as $activity)
                            @php
                                $iconBg = match(true) {
                                    str_starts_with($activity['type'], 'session_completed') => 'bg-gradient-to-br from-emerald-400 to-teal-500',
                                    str_starts_with($activity['type'], 'session_cancelled') => 'bg-gradient-to-br from-rose-400 to-red-500',
                                    str_starts_with($activity['type'], 'session_no_show')   => 'bg-gradient-to-br from-amber-400 to-orange-500',
                                    default => 'bg-gradient-to-br from-violet-500 to-violet-400',
                                };
                            @endphp
                            <div class="flex items-start gap-3">
                                <div class="shrink-0 w-9 h-9 rounded-xl {{ $iconBg }} text-white shadow-sm flex items-center justify-center">
                                    <flux:icon name="{{ $activity['icon'] }}" class="w-4 h-4" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-slate-800 dark:text-zinc-100 leading-snug">{{ $activity['message'] }}</p>
                                    <p class="text-xs text-slate-500 dark:text-zinc-500 mt-0.5">{{ $activity['time'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-slate-500 dark:text-zinc-400">No recent activity</p>
                @endif
            </div>

            {{-- Coming Up --}}
            <div class="teacher-card p-5 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="teacher-display text-base font-bold text-slate-900 dark:text-white">Coming Up</h3>
                    <flux:icon name="arrow-trending-up" class="w-4 h-4 text-slate-400 dark:text-zinc-500" />
                </div>

                @if($this->upcomingSessions->isNotEmpty())
                    <div class="space-y-2.5">
                        @foreach($this->upcomingSessions as $item)
                            <div class="flex items-center gap-3 rounded-xl px-3 py-2.5 bg-gradient-to-r from-slate-50 to-violet-50/40 dark:from-zinc-800/40 dark:to-violet-950/20 ring-1 ring-slate-200/60 dark:ring-zinc-800 hover:ring-violet-300/50 dark:hover:ring-violet-700/40 transition">
                                <div class="shrink-0 text-center w-12">
                                    <div class="teacher-display text-[11px] font-bold uppercase text-violet-600 dark:text-violet-400 leading-none">
                                        {{ $item['date']->format('M') }}
                                    </div>
                                    <div class="teacher-display teacher-num text-xl font-bold text-slate-900 dark:text-white leading-tight">
                                        {{ $item['date']->format('j') }}
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">
                                        {{ $item['class']->course->title ?? $item['class']->course->name }}
                                    </p>
                                    <p class="text-xs text-slate-500 dark:text-zinc-400 truncate">
                                        {{ $item['display_time'] }} · {{ $item['class']->title }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-slate-500 dark:text-zinc-400">No upcoming sessions</p>
                @endif

                <div class="mt-4 pt-4 border-t border-slate-100 dark:border-zinc-800">
                    <a href="{{ route('teacher.timetable') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-semibold text-violet-600 dark:text-violet-400 hover:text-violet-600 dark:hover:text-violet-300">
                        View timetable
                        <flux:icon name="arrow-right" class="w-4 h-4" />
                    </a>
                </div>
            </div>
        </div>
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
                    'ongoing'   => ['bg' => 'bg-emerald-400/95',  'text' => 'text-emerald-950', 'icon' => 'bolt',  'label' => 'Live now'],
                    'cancelled' => ['bg' => 'bg-rose-400/90',     'text' => 'text-rose-950',    'icon' => 'x-mark', 'label' => 'Cancelled'],
                    'no_show'   => ['bg' => 'bg-amber-400/95',    'text' => 'text-amber-950',   'icon' => 'exclamation-triangle', 'label' => 'No-show'],
                    default     => ['bg' => 'bg-white/95',        'text' => 'text-violet-700',  'icon' => 'calendar', 'label' => 'Scheduled'],
                };
            @endphp

            <div class="teacher-app">
                {{-- HERO HEADER --}}
                <div class="teacher-modal-hero relative px-6 pt-6 pb-7 sm:px-8 sm:pt-8 sm:pb-9 text-white">
                    <div class="teacher-grain absolute inset-0 pointer-events-none"></div>

                    {{-- close button (top right) --}}
                    <button
                        type="button"
                        wire:click="closeModal"
                        class="absolute top-4 right-4 w-9 h-9 rounded-full bg-white/15 hover:bg-white/25 ring-1 ring-white/20 backdrop-blur flex items-center justify-center transition"
                        aria-label="Close"
                    >
                        <flux:icon name="x-mark" class="w-4 h-4 text-white" />
                    </button>

                    <div class="relative">
                        {{-- status pill --}}
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

                        {{-- meta chips --}}
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
                            @if($selectedSession->class->location)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-medium ring-1 ring-white/20 backdrop-blur">
                                    <flux:icon name="map-pin" class="w-3.5 h-3.5" />
                                    {{ $selectedSession->class->location }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- BODY --}}
                <div class="bg-white dark:bg-zinc-900 px-6 py-6 sm:px-8 sm:py-7 space-y-6 max-h-[60vh] overflow-y-auto">

                    {{-- Live timer (only ongoing) --}}
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
                                <flux:icon name="document-text" class="w-4 h-4 text-violet-500" />
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
                                <flux:icon name="pencil-square" class="w-4 h-4 text-violet-500" />
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

                    {{-- Empty state when no notes + no attendances + not ongoing --}}
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
                            <button type="button" wire:click="startSession({{ $selectedSession->id }})" class="teacher-cta">
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
         START SESSION CONFIRMATION MODAL  -  compact, focused
         ────────────────────────────────────────────────────────── --}}
    <flux:modal wire:model="showStartConfirmation" class="max-w-md !p-0 overflow-hidden">
        <div class="teacher-app">
            {{-- top gradient stripe --}}
            <div class="teacher-modal-stripe"></div>

            <div class="bg-white dark:bg-zinc-900 px-6 pt-8 pb-6 text-center">
                {{-- gradient orb --}}
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

                {{-- briefing: syllabus, upsell, PIC, class context --}}
                @include('livewire.teacher._partials.start-session-briefing', ['briefing' => $this->startBriefing])

                {{-- info card --}}
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

            {{-- footer actions --}}
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