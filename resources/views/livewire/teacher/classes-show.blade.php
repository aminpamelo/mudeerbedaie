<?php

use App\Models\ClassModel;
use App\Models\ClassSession;
use App\Support\TeacherStartBriefing;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.teacher')] class extends Component
{
    public ClassModel $class;

    public Carbon $currentDate;

    public bool $showStartConfirmation = false;

    public ?string $dateToStart = null;

    public ?string $timeToStart = null;

    public function mount(ClassModel $class): void
    {
        // Ensure this class belongs to the current teacher
        $teacher = auth()->user()->teacher;
        if (! $teacher || $class->teacher_id !== $teacher->id) {
            abort(403, 'You are not authorized to view this class.');
        }

        $this->class = $class->load([
            'course',
            'teacher.user',
            'sessions.attendances.student.user',
            'activeStudents.student.user',
            'timetable',
        ]);

        $this->currentDate = Carbon::now();

        // Set active tab based on URL parameter
        $requestedTab = request()->query('tab', 'overview');
        $validTabs = ['overview', 'sessions', 'students', 'timetable'];

        if (in_array($requestedTab, $validTabs)) {
            $this->activeTab = $requestedTab;
        }
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
            ->groupBy(function ($session) {
                return $session->session_date->format('Y-m');
            })
            ->map(function ($sessions, $key) {
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
                    ],
                ];
            })
            ->toArray();
    }

    public $activeTab = 'overview';

    // Session management properties
    public $showSessionModal = false;

    public $sessionModalState = 'management'; // 'management' or 'completion'

    public $showAttendanceViewModal = false;

    public $currentSession = null;

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
                'activeStudents.student.user',
            ]);

            session()->flash('success', 'Session started successfully.');
        }
    }

    public function switchToCompletionState(): void
    {
        if ($this->currentSession && ($this->currentSession->isScheduled() || $this->currentSession->isOngoing())) {
            // Only use existing bookmark from the session if it exists
            $this->completionBookmark = $this->currentSession->bookmark ?? '';
            $this->sessionModalState = 'completion';
        }
    }

    public function switchToManagementState(): void
    {
        $this->sessionModalState = 'management';
    }

    public function openCompletionModal($sessionId): void
    {
        $this->currentSession = \App\Models\ClassSession::with(['attendances.student.user'])->find($sessionId);
        if ($this->currentSession && ($this->currentSession->isScheduled() || $this->currentSession->isOngoing())) {
            // Only use existing bookmark from the session if it exists
            $this->completionBookmark = $this->currentSession->bookmark ?? '';
            $this->sessionModalState = 'completion';
            $this->showSessionModal = true;
        }
    }

    public function completeSessionWithBookmark(): void
    {
        $this->validate([
            'completionBookmark' => 'required|string|min:3|max:500',
        ], [
            'completionBookmark.required' => 'Bookmark is required before completing the session.',
            'completionBookmark.min' => 'Bookmark must be at least 3 characters.',
            'completionBookmark.max' => 'Bookmark cannot exceed 500 characters.',
        ]);

        if ($this->currentSession && ($this->currentSession->isScheduled() || $this->currentSession->isOngoing())) {
            $this->currentSession->markCompleted($this->completionBookmark);
            session()->flash('success', 'Session completed with bookmark.');

            // Close session modal
            $this->closeSessionModal();

            // Refresh data
            $this->class->refresh();
            $this->class->load([
                'course',
                'teacher.user',
                'sessions.attendances.student.user',
                'activeStudents.student.user',
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
                'activeStudents.student.user',
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
                'activeStudents.student.user',
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
        $this->sessionModalState = 'management';
        $this->currentSession = null;
        $this->completionBookmark = '';
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

    // Method to handle clicking on any session in the timetable
    public function selectSession($sessionId): void
    {
        $session = \App\Models\ClassSession::with(['attendances.student.user'])->find($sessionId);

        if (! $session) {
            return;
        }

        if ($session->isOngoing()) {
            $this->openSessionModal($sessionId);
        } elseif ($session->isCompleted()) {
            $this->openAttendanceViewModal($sessionId);
        } elseif ($session->isScheduled()) {
            $this->openSessionModal($sessionId);
        }
    }

    public function updateStudentAttendance($studentId, $status): void
    {
        if (! $this->currentSession || ! $this->currentSession->isOngoing()) {
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
                'activeStudents.student.user',
            ]);

            session()->flash('success', 'Attendance updated successfully.');
        }
    }

    public $bookmarkText = '';

    public function updateSessionBookmark(): void
    {
        if (! $this->currentSession || ! $this->currentSession->isOngoing()) {
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
            'activeStudents.student.user',
        ]);

        session()->flash('success', 'Bookmark updated successfully.');
    }

    public function setActiveTab($tab): void
    {
        $validTabs = ['overview', 'sessions', 'students', 'timetable'];

        if (in_array($tab, $validTabs)) {
            $this->activeTab = $tab;

            // Update URL without page reload
            $this->dispatch('update-url', tab: $tab);
        }
    }

    // Timetable tab computed properties
    public function getClassSessionsCountProperty(): int
    {
        return $this->class->sessions()->count();
    }

    public function getUpcomingSessionsProperty()
    {
        return $this->class->sessions()
            ->where('status', 'scheduled')
            ->where('session_date', '>', now()->toDateString())
            ->orderBy('session_date')
            ->orderBy('session_time')
            ->get();
    }

    public function getRecentSessionsProperty()
    {
        return $this->class->sessions()
            ->whereIn('status', ['completed', 'cancelled', 'no_show'])
            ->orderBy('session_date', 'desc')
            ->orderBy('session_time', 'desc')
            ->get();
    }

    public function getWeeklyHoursProperty(): float
    {
        $completedSessions = $this->class->sessions()
            ->where('status', 'completed')
            ->get();

        if ($completedSessions->isEmpty()) {
            return 0;
        }

        $totalMinutes = $completedSessions->sum('duration_minutes');

        $totalWeeks = $completedSessions->groupBy(function ($session) {
            return $session->session_date->format('Y-W');
        })->count();

        if ($totalWeeks === 0) {
            return 0;
        }

        return ($totalMinutes / 60) / $totalWeeks;
    }

    // Weekly calendar properties
    public function getWeeklyCalendarDataProperty(): array
    {
        // Get the class timetable schedule
        $timetable = $this->class->timetable;
        if (! $timetable || ! $timetable->weekly_schedule) {
            return [];
        }

        // Get current week dates
        $currentWeek = $this->currentDate->copy()->startOfWeek();
        $weekDays = [];

        for ($i = 0; $i < 7; $i++) {
            $date = $currentWeek->copy()->addDays($i);
            $dayName = strtolower($date->format('l'));

            $weekDays[$dayName] = [
                'date' => $date,
                'day_name' => $date->format('D'),
                'day_number' => $date->format('j'),
                'is_today' => $date->isToday(),
                'scheduled_times' => $timetable->weekly_schedule[$dayName] ?? [],
                'sessions' => $this->getSessionsForDate($date),
            ];
        }

        return $weekDays;
    }

    private function getSessionsForDate($date): \Illuminate\Support\Collection
    {
        return $this->class->sessions()
            ->whereDate('session_date', $date->toDateString())
            ->orderBy('session_time')
            ->get();
    }

    // Week navigation methods for timetable
    public function previousWeek(): void
    {
        $this->currentDate = $this->currentDate->subWeek();
    }

    public function nextWeek(): void
    {
        $this->currentDate = $this->currentDate->addWeek();
    }

    public function goToCurrentWeek(): void
    {
        $this->currentDate = Carbon::now();
    }

    public function getCurrentWeekLabelProperty(): string
    {
        $start = $this->currentDate->startOfWeek();
        $end = $this->currentDate->endOfWeek();

        return $start->format('M d').' - '.$end->format('M d, Y');
    }

    public function closeStartConfirmation()
    {
        $this->showStartConfirmation = false;
        $this->dateToStart = null;
        $this->timeToStart = null;
    }

    public function requestStartSessionFromTimetable($date, $time)
    {
        $this->dateToStart = $date;
        $this->timeToStart = $time;
        $this->showStartConfirmation = true;
    }

    public function confirmStartSession()
    {
        if ($this->dateToStart && $this->timeToStart) {
            $this->startSessionFromTimetable($this->dateToStart, $this->timeToStart);
        }
        $this->closeStartConfirmation();
    }

    public function getStartBriefingProperty(): ?array
    {
        if (! $this->dateToStart || ! $this->timeToStart) {
            return null;
        }

        $this->class->loadMissing(['course', 'pics', 'activeStudents']);

        $when = Carbon::parse($this->dateToStart.' '.$this->timeToStart);

        $session = ClassSession::where('class_id', $this->class->id)
            ->whereDate('session_date', $when->toDateString())
            ->whereTime('session_time', $when->format('H:i:s'))
            ->first();

        return TeacherStartBriefing::build($session, $this->class, $when);
    }

    // Create or start session from timetable and open modal
    public function startSessionFromTimetable($date, $time): void
    {
        $dateObj = \Carbon\Carbon::parse($date);
        $timeObj = \Carbon\Carbon::parse($time);

        // Check if session already exists for this date/time
        $existingSession = $this->class->sessions()
            ->whereDate('session_date', $dateObj->toDateString())
            ->whereTime('session_time', $timeObj->format('H:i:s'))
            ->first();

        if ($existingSession) {
            // If session exists and is scheduled, start it
            if ($existingSession->isScheduled()) {
                $existingSession->markAsOngoing();

                // Open the session management modal
                $this->openSessionModal($existingSession->id);

                session()->flash('success', 'Session started successfully!');
            } elseif ($existingSession->isOngoing()) {
                // Open the session management modal for ongoing session
                $this->openSessionModal($existingSession->id);
            } else {
                session()->flash('warning', 'Session cannot be started (status: '.$existingSession->status.')');
            }
        } else {
            // Create new session and start it
            $newSession = $this->class->sessions()->create([
                'session_date' => $dateObj->toDateString(),
                'session_time' => $timeObj->format('H:i:s'),
                'duration_minutes' => $this->class->duration_minutes ?? 60,
                'status' => 'ongoing',
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Auto-create attendance records
            $newSession->createAttendanceRecords();

            // Open the session management modal
            $this->openSessionModal($newSession->id);

            session()->flash('success', 'New session created and started successfully!');
        }

        // Refresh class data
        $this->class->refresh();
        $this->class->load([
            'course',
            'teacher.user',
            'sessions.attendances.student.user',
            'activeStudents.student.user',
            'timetable',
        ]);
    }

    // Session creation properties
    public $showCreateSessionModal = false;

    public $newSessionDate = '';

    public $newSessionTime = '09:00';

    public $newSessionDuration = 60;

    public function openCreateSessionModal(): void
    {
        // Set default date to tomorrow if today is past, otherwise today
        $this->newSessionDate = now()->addDay()->format('Y-m-d');
        $this->newSessionTime = '09:00';
        $this->newSessionDuration = $this->class->duration_minutes ?? 60;
        $this->showCreateSessionModal = true;
    }

    public function closeCreateSessionModal(): void
    {
        $this->showCreateSessionModal = false;
        $this->newSessionDate = '';
        $this->newSessionTime = '09:00';
        $this->newSessionDuration = 60;
        $this->resetErrorBag();
    }

    public function createSession(): void
    {
        $this->validate([
            'newSessionDate' => 'required|date|after_or_equal:today',
            'newSessionTime' => 'required',
            'newSessionDuration' => 'required|integer|min:15|max:300',
        ], [
            'newSessionDate.required' => 'Session date is required.',
            'newSessionDate.date' => 'Please enter a valid date.',
            'newSessionDate.after_or_equal' => 'Session date cannot be in the past.',
            'newSessionTime.required' => 'Session time is required.',
            'newSessionDuration.required' => 'Session duration is required.',
            'newSessionDuration.integer' => 'Duration must be a number.',
            'newSessionDuration.min' => 'Duration must be at least 15 minutes.',
            'newSessionDuration.max' => 'Duration cannot exceed 5 hours.',
        ]);

        // Create new session
        \App\Models\ClassSession::create([
            'class_id' => $this->class->id,
            'session_date' => $this->newSessionDate,
            'session_time' => $this->newSessionTime,
            'duration_minutes' => $this->newSessionDuration,
            'status' => 'scheduled',
        ]);

        session()->flash('success', 'New session created successfully.');
        $this->closeCreateSessionModal();

        // Refresh class data
        $this->class->refresh();
        $this->class->load([
            'course',
            'teacher.user',
            'sessions.attendances.student.user',
            'activeStudents.student.user',
            'timetable',
        ]);
    }
}; ?>

@php
    $statusKey = $class->status ?? 'active';
    $statusBadge = match($statusKey) {
        'active'    => ['bg' => 'bg-emerald-400/95', 'text' => 'text-emerald-950', 'icon' => 'check',                'label' => 'Active'],
        'completed' => ['bg' => 'bg-emerald-400/95', 'text' => 'text-emerald-950', 'icon' => 'check',                'label' => 'Completed'],
        'cancelled' => ['bg' => 'bg-rose-400/95',    'text' => 'text-rose-950',    'icon' => 'x-mark',               'label' => 'Cancelled'],
        'suspended' => ['bg' => 'bg-amber-400/95',   'text' => 'text-amber-950',   'icon' => 'pause',                'label' => 'Suspended'],
        'draft'     => ['bg' => 'bg-white/95',       'text' => 'text-violet-700',  'icon' => 'pencil-square',        'label' => 'Draft'],
        default     => ['bg' => 'bg-white/95',       'text' => 'text-violet-700',  'icon' => 'sparkles',             'label' => ucfirst($statusKey)],
    };

    $startDate = $class->date_time ?? optional($class->sessions->sortBy('session_date')->first())->session_date;
@endphp

<div class="teacher-app w-full space-y-6">
    {{-- ──────────────────────────────────────────────────────────
         FLASH SUCCESS
         ────────────────────────────────────────────────────────── --}}
    @if(session('success'))
        <div class="rounded-2xl bg-emerald-50 dark:bg-emerald-950/30 ring-1 ring-emerald-200/70 dark:ring-emerald-800/50 px-4 py-3 flex items-center gap-2.5">
            <div class="shrink-0 w-7 h-7 rounded-full bg-emerald-500 text-white flex items-center justify-center">
                <flux:icon name="check" class="w-4 h-4" />
            </div>
            <p class="text-sm font-medium text-emerald-800 dark:text-emerald-200">{{ session('success') }}</p>
        </div>
    @endif

    @if(session('warning'))
        <div class="rounded-2xl bg-amber-50 dark:bg-amber-950/30 ring-1 ring-amber-200/70 dark:ring-amber-800/50 px-4 py-3 flex items-center gap-2.5">
            <div class="shrink-0 w-7 h-7 rounded-full bg-amber-500 text-white flex items-center justify-center">
                <flux:icon name="exclamation-triangle" class="w-4 h-4" />
            </div>
            <p class="text-sm font-medium text-amber-800 dark:text-amber-200">{{ session('warning') }}</p>
        </div>
    @endif

    {{-- ──────────────────────────────────────────────────────────
         GRADIENT HERO HEADER
         ────────────────────────────────────────────────────────── --}}
    <div class="teacher-modal-hero relative overflow-hidden rounded-2xl px-6 py-7 sm:px-8 sm:py-8 text-white">
        <div class="teacher-grain absolute inset-0 pointer-events-none"></div>

        <div class="relative">
            {{-- Back link --}}
            <a href="{{ route('teacher.classes.index') }}" wire:navigate class="inline-flex items-center gap-1 text-xs font-semibold text-white/80 hover:text-white mb-3 transition">
                <flux:icon name="arrow-left" class="w-3.5 h-3.5" />
                Back to classes
            </a>

            {{-- Status pill --}}
            <span class="inline-flex items-center gap-1.5 rounded-full {{ $statusBadge['bg'] }} {{ $statusBadge['text'] }} px-3 py-1 text-xs font-bold ring-1 ring-white/40">
                <flux:icon name="{{ $statusBadge['icon'] }}" class="w-3 h-3" />
                {{ $statusBadge['label'] }}
            </span>

            {{-- Title --}}
            <h1 class="teacher-display mt-3 text-2xl sm:text-3xl font-bold leading-tight">
                {{ $class->title }}
            </h1>
            <p class="text-white/80 text-sm sm:text-base mt-1">{{ $class->course->title ?? $class->course->name }}</p>

            {{-- Meta chips --}}
            <div class="mt-4 flex flex-wrap gap-2">
                @if($class->class_type)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 ring-1 ring-white/20 backdrop-blur px-3 py-1 text-xs font-medium">
                        <flux:icon name="tag" class="w-3.5 h-3.5" />
                        {{ ucfirst(str_replace('_', ' ', $class->class_type)) }}
                    </span>
                @endif
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 ring-1 ring-white/20 backdrop-blur px-3 py-1 text-xs font-medium">
                    <flux:icon name="users" class="w-3.5 h-3.5" />
                    {{ $this->enrolled_students_count }} {{ Str::plural('student', $this->enrolled_students_count) }}
                </span>
                @if($class->duration_minutes)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 ring-1 ring-white/20 backdrop-blur px-3 py-1 text-xs font-medium">
                        <flux:icon name="clock" class="w-3.5 h-3.5" />
                        {{ $class->duration_minutes }} min
                    </span>
                @endif
                @if($class->location)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 ring-1 ring-white/20 backdrop-blur px-3 py-1 text-xs font-medium">
                        <flux:icon name="map-pin" class="w-3.5 h-3.5" />
                        {{ $class->location }}
                    </span>
                @endif
                @if($startDate)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 ring-1 ring-white/20 backdrop-blur px-3 py-1 text-xs font-medium">
                        <flux:icon name="calendar" class="w-3.5 h-3.5" />
                        {{ \Carbon\Carbon::parse($startDate)->format('j M Y') }}
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- ──────────────────────────────────────────────────────────
         STAT STRIP
         ────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <x-teacher.stat-card
            eyebrow="Students"
            :value="$this->enrolled_students_count"
            tone="indigo"
            icon="users"
        >
            <span class="text-slate-500 dark:text-zinc-400">Active enrolments</span>
        </x-teacher.stat-card>

        <x-teacher.stat-card
            eyebrow="Completed"
            :value="$this->completed_sessions_count"
            tone="emerald"
            icon="check-circle"
        >
            <span class="text-slate-500 dark:text-zinc-400">of {{ $this->total_sessions_count }} {{ Str::plural('session', $this->total_sessions_count) }}</span>
        </x-teacher.stat-card>

        <x-teacher.stat-card
            eyebrow="Upcoming"
            :value="$this->upcoming_sessions_count"
            tone="violet"
            icon="calendar"
        >
            <span class="text-slate-500 dark:text-zinc-400">Scheduled ahead</span>
        </x-teacher.stat-card>

        <x-teacher.stat-card
            eyebrow="Attendance"
            :value="$this->overall_attendance_rate . '%'"
            tone="amber"
            icon="chart-bar"
        >
            <span class="text-slate-500 dark:text-zinc-400">{{ $this->total_present_count }} / {{ $this->total_attendance_records }} present</span>
        </x-teacher.stat-card>
    </div>

    {{-- ──────────────────────────────────────────────────────────
         TAB NAVIGATION
         ────────────────────────────────────────────────────────── --}}
    <x-teacher.tabs
        :tabs="[
            ['key' => 'overview', 'label' => 'Overview', 'icon' => 'home'],
            ['key' => 'sessions', 'label' => 'Sessions', 'icon' => 'clock', 'badge' => $this->total_sessions_count ?: null],
            ['key' => 'students', 'label' => 'Students', 'icon' => 'users', 'badge' => $this->enrolled_students_count ?: null],
            ['key' => 'timetable', 'label' => 'Timetable', 'icon' => 'calendar'],
        ]"
        :active="$activeTab"
    />

    {{-- ──────────────────────────────────────────────────────────
         TAB CONTENT  -  do not modify the @switch / @include calls
         ────────────────────────────────────────────────────────── --}}
    <div>
        @switch($activeTab)
            @case('overview')
                @include('livewire.teacher.classes-show.tab-overview')
                @break

            @case('sessions')
                @include('livewire.teacher.classes-show.tab-sessions')
                @break

            @case('students')
                @include('livewire.teacher.classes-show.tab-students')
                @break

            @case('timetable')
                @include('livewire.teacher.classes-show.tab-timetable')
                @break
        @endswitch
    </div>

    {{-- ──────────────────────────────────────────────────────────
         SESSION MANAGEMENT MODAL  -  manage attendance / complete
         ────────────────────────────────────────────────────────── --}}
    <flux:modal name="session-management" :show="$showSessionModal" wire:model="showSessionModal" class="max-w-3xl !p-0 overflow-hidden">
        @if($currentSession)
            <div class="teacher-app">
                @if($sessionModalState === 'management')
                    {{-- HERO --}}
                    <div class="teacher-modal-hero relative px-6 pt-6 pb-7 sm:px-8 sm:pt-8 sm:pb-8 text-white">
                        <div class="teacher-grain absolute inset-0 pointer-events-none"></div>

                        <button
                            type="button"
                            wire:click="closeSessionModal"
                            class="absolute top-4 right-4 w-9 h-9 rounded-full bg-white/15 hover:bg-white/25 ring-1 ring-white/20 backdrop-blur flex items-center justify-center transition"
                            aria-label="Close"
                        >
                            <flux:icon name="x-mark" class="w-4 h-4 text-white" />
                        </button>

                        <div class="relative">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-400/95 text-emerald-950 ring-1 ring-white/40 px-3 py-1 text-xs font-bold">
                                <span class="teacher-live-dot bg-emerald-700 !shadow-none"></span>
                                Live now
                            </span>

                            <h2 class="teacher-display mt-3 text-2xl sm:text-3xl font-bold leading-tight pr-10">
                                Manage Session
                            </h2>
                            <p class="text-white/80 text-sm sm:text-base mt-1">
                                {{ $currentSession->formatted_date_time }}
                            </p>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 ring-1 ring-white/20 backdrop-blur px-3 py-1 text-xs font-medium">
                                    <flux:icon name="users" class="w-3.5 h-3.5" />
                                    {{ $currentSession->attendances->count() }} {{ Str::plural('student', $currentSession->attendances->count()) }}
                                </span>
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 ring-1 ring-white/20 backdrop-blur px-3 py-1 text-xs font-medium">
                                    <flux:icon name="check" class="w-3.5 h-3.5" />
                                    {{ $currentSession->attendances->where('status', 'present')->count() }} present
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- BODY --}}
                    <div class="bg-white dark:bg-zinc-900 px-6 py-6 sm:px-8 sm:py-7 space-y-5 max-h-[60vh] overflow-y-auto">

                        {{-- Live timer --}}
                        <div
                            x-data="sessionTimer('{{ $currentSession->started_at ? $currentSession->started_at->toISOString() : now()->toISOString() }}')"
                            x-init="startTimer()"
                            class="teacher-modal-timer rounded-2xl px-5 py-5 sm:px-6"
                        >
                            <div class="relative flex items-center justify-between gap-4">
                                <div>
                                    <div class="flex items-center gap-2 text-emerald-100/95 text-xs font-bold uppercase tracking-[0.2em]">
                                        <span class="teacher-live-dot bg-emerald-300"></span>
                                        Session live
                                    </div>
                                    <p class="mt-1 text-emerald-50/80 text-sm">Mark each student's attendance below.</p>
                                </div>
                                <div class="text-right">
                                    <div class="teacher-num text-3xl sm:text-4xl font-mono font-bold text-white tracking-tight" x-text="formattedTime"></div>
                                    <div class="text-emerald-200/80 text-[10px] font-bold uppercase tracking-[0.18em] mt-0.5">Elapsed</div>
                                </div>
                            </div>
                        </div>

                        {{-- Student attendance list --}}
                        <div>
                            <h3 class="teacher-display font-bold text-slate-900 dark:text-white text-sm flex items-center gap-1.5 mb-3">
                                <flux:icon name="users" class="w-4 h-4 text-violet-500" />
                                Attendance
                            </h3>
                            <div class="space-y-2">
                                @foreach($currentSession->attendances as $i => $attendance)
                                    @php
                                        $initials = collect(explode(' ', trim($attendance->student->fullName)))
                                            ->take(2)
                                            ->map(fn ($p) => strtoupper(substr($p, 0, 1)))
                                            ->join('');
                                        $avatarVariant = ($i % 6) + 1;
                                    @endphp
                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-xl px-3 py-2.5 ring-1 ring-slate-200/70 dark:ring-zinc-800 bg-slate-50/60 dark:bg-zinc-800/40 hover:ring-violet-300 dark:hover:ring-violet-700/60 transition">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="teacher-avatar teacher-avatar-{{ $avatarVariant }}">{{ $initials }}</div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ $attendance->student->fullName }}</p>
                                                <p class="text-[11px] text-slate-500 dark:text-zinc-400 truncate">{{ $attendance->student->student_id }}</p>
                                            </div>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-1.5">
                                            @php
                                                $statusButtons = [
                                                    'present' => ['active' => 'bg-emerald-500 text-white', 'idle' => 'text-emerald-700 ring-emerald-200 hover:bg-emerald-50 dark:text-emerald-300 dark:ring-emerald-700/40 dark:hover:bg-emerald-500/10'],
                                                    'late'    => ['active' => 'bg-amber-500 text-white',   'idle' => 'text-amber-700 ring-amber-200 hover:bg-amber-50 dark:text-amber-300 dark:ring-amber-700/40 dark:hover:bg-amber-500/10'],
                                                    'absent'  => ['active' => 'bg-rose-500 text-white',    'idle' => 'text-rose-700 ring-rose-200 hover:bg-rose-50 dark:text-rose-300 dark:ring-rose-700/40 dark:hover:bg-rose-500/10'],
                                                    'excused' => ['active' => 'bg-sky-500 text-white',     'idle' => 'text-sky-700 ring-sky-200 hover:bg-sky-50 dark:text-sky-300 dark:ring-sky-700/40 dark:hover:bg-sky-500/10'],
                                                ];
                                            @endphp
                                            @foreach($statusButtons as $status => $tones)
                                                @php
                                                    $isActive = ($attendance->status === $status);
                                                    $classes = $isActive
                                                        ? "ring-0 shadow-sm {$tones['active']}"
                                                        : "bg-white dark:bg-zinc-900 ring-1 {$tones['idle']}";
                                                @endphp
                                                <button
                                                    type="button"
                                                    wire:click="updateStudentAttendance({{ $attendance->student_id }}, '{{ $status }}')"
                                                    class="rounded-lg px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider transition {{ $classes }}"
                                                >
                                                    {{ ucfirst($status) }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- FOOTER --}}
                    <div class="bg-slate-50/80 dark:bg-zinc-950/60 px-6 py-4 sm:px-8 border-t border-slate-200/70 dark:border-zinc-800 flex items-center justify-between gap-3">
                        <button type="button" wire:click="closeSessionModal" class="teacher-cta-ghost">
                            Close
                        </button>
                        <button type="button" wire:click="switchToCompletionState()" class="teacher-cta">
                            <flux:icon name="check" class="w-4 h-4" />
                            Complete Session
                        </button>
                    </div>

                @else
                    {{-- COMPLETION STATE --}}
                    <div class="teacher-modal-stripe"></div>

                    <div class="bg-white dark:bg-zinc-900 px-6 pt-8 pb-6 sm:px-8 sm:pt-9 sm:pb-7">
                        <div class="flex justify-center mb-5">
                            <div class="teacher-modal-orb">
                                <flux:icon name="check" class="w-9 h-9" variant="solid" />
                            </div>
                        </div>

                        <div class="text-center">
                            <h2 class="teacher-display text-2xl font-bold text-slate-900 dark:text-white">Complete Session</h2>
                            <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400 leading-relaxed">
                                {{ $currentSession->formatted_date_time }}
                            </p>
                        </div>

                        <div class="mt-6 space-y-4">
                            <div>
                                <label class="teacher-display text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-zinc-300 flex items-center gap-1.5 mb-2">
                                    <flux:icon name="bookmark" class="w-3.5 h-3.5 text-violet-500" />
                                    Session Bookmark <span class="text-rose-500">*</span>
                                </label>
                                <textarea
                                    wire:model="completionBookmark"
                                    rows="3"
                                    placeholder="e.g., Completed Chapter 3, stopped at page 45, reviewed exercises 1-10"
                                    class="w-full rounded-xl border-0 ring-1 ring-slate-200 dark:ring-zinc-700 bg-white dark:bg-zinc-900 text-sm text-slate-900 dark:text-zinc-100 placeholder:text-slate-400 dark:placeholder:text-zinc-500 px-4 py-3 focus:ring-2 focus:ring-violet-500 dark:focus:ring-violet-400 transition"
                                ></textarea>
                                @error('completionBookmark')
                                    <p class="text-xs text-rose-600 dark:text-rose-400 mt-1.5">{{ $message }}</p>
                                @enderror
                                <p class="text-[11px] text-slate-500 dark:text-zinc-400 mt-1.5">Describe what was covered or where you stopped in this session.</p>
                            </div>

                            @if($currentSession->attendances->count() > 0)
                                <div class="rounded-2xl bg-gradient-to-br from-emerald-50 via-emerald-100/60 to-emerald-200/30 dark:from-emerald-950/50 dark:via-emerald-900/30 dark:to-emerald-800/20 ring-1 ring-emerald-100 dark:ring-emerald-900/40 px-4 py-4">
                                    <div class="flex items-start gap-3">
                                        <div class="shrink-0 w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-600 to-emerald-500 text-white shadow-lg shadow-emerald-500/30 flex items-center justify-center">
                                            <flux:icon name="check-circle" class="w-4 h-4" variant="solid" />
                                        </div>
                                        <div class="flex-1 text-xs leading-relaxed">
                                            <p class="font-semibold text-emerald-900 dark:text-emerald-200 text-[13px] mb-0.5">Attendance recorded</p>
                                            <p class="text-emerald-700/80 dark:text-emerald-300/80">{{ $currentSession->attendances->where('status', 'present')->count() }} of {{ $currentSession->attendances->count() }} students marked as present.</p>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="bg-slate-50/80 dark:bg-zinc-950/60 px-6 py-4 border-t border-slate-200/70 dark:border-zinc-800 flex gap-2 justify-between">
                        <button type="button" wire:click="switchToManagementState()" class="teacher-cta-ghost">
                            <flux:icon name="arrow-left" class="w-4 h-4" />
                            Back
                        </button>
                        <button type="button" wire:click="completeSessionWithBookmark" class="teacher-cta">
                            <flux:icon name="check" class="w-4 h-4" />
                            Complete Session
                        </button>
                    </div>
                @endif
            </div>
        @endif
    </flux:modal>

    {{-- ──────────────────────────────────────────────────────────
         ATTENDANCE VIEW MODAL  -  read-only completed session
         ────────────────────────────────────────────────────────── --}}
    <flux:modal name="attendance-view" :show="$showAttendanceViewModal" wire:model="showAttendanceViewModal" class="max-w-2xl !p-0 overflow-hidden">
        @if($viewingSession)
            <div class="teacher-app">
                {{-- HERO --}}
                <div class="teacher-modal-hero relative px-6 pt-6 pb-7 sm:px-8 sm:pt-8 sm:pb-8 text-white">
                    <div class="teacher-grain absolute inset-0 pointer-events-none"></div>

                    <button
                        type="button"
                        wire:click="closeAttendanceViewModal"
                        class="absolute top-4 right-4 w-9 h-9 rounded-full bg-white/15 hover:bg-white/25 ring-1 ring-white/20 backdrop-blur flex items-center justify-center transition"
                        aria-label="Close"
                    >
                        <flux:icon name="x-mark" class="w-4 h-4 text-white" />
                    </button>

                    <div class="relative">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-400/95 text-emerald-950 ring-1 ring-white/40 px-3 py-1 text-xs font-bold">
                            <flux:icon name="check" class="w-3 h-3" />
                            Completed
                        </span>

                        <h2 class="teacher-display mt-3 text-2xl sm:text-3xl font-bold leading-tight pr-10">
                            Session Details
                        </h2>
                        <p class="text-white/80 text-sm sm:text-base mt-1">
                            {{ $viewingSession->formatted_date_time }}
                        </p>
                    </div>
                </div>

                {{-- BODY --}}
                <div class="bg-white dark:bg-zinc-900 px-6 py-6 sm:px-8 sm:py-7 space-y-5 max-h-[60vh] overflow-y-auto">
                    @if($viewingSession->hasBookmark())
                        <div>
                            <h3 class="teacher-display font-bold text-slate-900 dark:text-white text-sm flex items-center gap-1.5 mb-2">
                                <flux:icon name="bookmark" class="w-4 h-4 text-violet-500" />
                                Session Bookmark
                            </h3>
                            <div class="rounded-xl px-4 py-3 bg-gradient-to-br from-violet-50/70 to-violet-50/40 dark:from-violet-950/30 dark:to-violet-950/20 ring-1 ring-violet-100/80 dark:ring-violet-900/40">
                                <p class="text-sm text-slate-700 dark:text-zinc-200 whitespace-pre-wrap leading-relaxed">{{ $viewingSession->bookmark }}</p>
                            </div>
                        </div>
                    @endif

                    @if($viewingSession->attendances->count() > 0)
                        <div>
                            <h3 class="teacher-display font-bold text-slate-900 dark:text-white text-sm flex items-center gap-1.5 mb-3">
                                <flux:icon name="users" class="w-4 h-4 text-violet-500" />
                                Students <span class="text-slate-400 dark:text-zinc-500 font-medium">({{ $viewingSession->attendances->count() }})</span>
                            </h3>
                            <div class="grid sm:grid-cols-2 gap-2">
                                @foreach($viewingSession->attendances as $i => $attendance)
                                    @php
                                        $statusTone = match($attendance->status) {
                                            'present' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
                                            'absent'  => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
                                            'late'    => 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
                                            'excused' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
                                            default   => 'bg-slate-100 text-slate-600 dark:bg-zinc-700/50 dark:text-zinc-300',
                                        };
                                        $initials = collect(explode(' ', trim($attendance->student->fullName)))
                                            ->take(2)
                                            ->map(fn ($p) => strtoupper(substr($p, 0, 1)))
                                            ->join('');
                                        $avatarVariant = ($i % 6) + 1;
                                    @endphp
                                    <div class="flex items-center gap-3 rounded-xl px-3 py-2.5 ring-1 ring-slate-200/70 dark:ring-zinc-800 bg-slate-50/60 dark:bg-zinc-800/40">
                                        <div class="teacher-avatar teacher-avatar-{{ $avatarVariant }}">{{ $initials }}</div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ $attendance->student->fullName }}</p>
                                        </div>
                                        <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider {{ $statusTone }}">
                                            {{ ucfirst($attendance->status) }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                {{-- FOOTER --}}
                <div class="bg-slate-50/80 dark:bg-zinc-950/60 px-6 py-4 sm:px-8 border-t border-slate-200/70 dark:border-zinc-800 flex justify-end">
                    <button type="button" wire:click="closeAttendanceViewModal" class="teacher-cta-ghost">
                        Close
                    </button>
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- ──────────────────────────────────────────────────────────
         CREATE SESSION MODAL  -  schedule a brand new session
         ────────────────────────────────────────────────────────── --}}
    <flux:modal wire:model="showCreateSessionModal" class="max-w-md !p-0 overflow-hidden">
        <div class="teacher-app">
            <div class="teacher-modal-stripe"></div>

            <form wire:submit="createSession">
                <div class="bg-white dark:bg-zinc-900 px-6 pt-8 pb-6 sm:px-8">
                    <div class="flex justify-center mb-5">
                        <div class="teacher-modal-orb">
                            <flux:icon name="calendar-days" class="w-9 h-9" variant="solid" />
                        </div>
                    </div>

                    <div class="text-center">
                        <h2 class="teacher-display text-2xl font-bold text-slate-900 dark:text-white">Create New Session</h2>
                        <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400 leading-relaxed">
                            Schedule a session for {{ $class->title }}.
                        </p>
                    </div>

                    <div class="mt-6 space-y-4 text-left">
                        <div>
                            <label for="newSessionDate" class="teacher-display text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-zinc-300 flex items-center gap-1.5 mb-2">
                                <flux:icon name="calendar" class="w-3.5 h-3.5 text-violet-500" />
                                Session Date
                            </label>
                            <input
                                wire:model="newSessionDate"
                                id="newSessionDate"
                                name="newSessionDate"
                                type="date"
                                required
                                class="w-full rounded-xl border-0 ring-1 ring-slate-200 dark:ring-zinc-700 bg-white dark:bg-zinc-900 text-sm text-slate-900 dark:text-zinc-100 px-4 py-2.5 focus:ring-2 focus:ring-violet-500 dark:focus:ring-violet-400 transition"
                            />
                            @error('newSessionDate')
                                <p class="text-xs text-rose-600 dark:text-rose-400 mt-1.5">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="newSessionTime" class="teacher-display text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-zinc-300 flex items-center gap-1.5 mb-2">
                                <flux:icon name="clock" class="w-3.5 h-3.5 text-violet-500" />
                                Session Time
                            </label>
                            <input
                                wire:model="newSessionTime"
                                id="newSessionTime"
                                name="newSessionTime"
                                type="time"
                                required
                                class="w-full rounded-xl border-0 ring-1 ring-slate-200 dark:ring-zinc-700 bg-white dark:bg-zinc-900 text-sm text-slate-900 dark:text-zinc-100 px-4 py-2.5 focus:ring-2 focus:ring-violet-500 dark:focus:ring-violet-400 transition"
                            />
                            @error('newSessionTime')
                                <p class="text-xs text-rose-600 dark:text-rose-400 mt-1.5">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="newSessionDuration" class="teacher-display text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-zinc-300 flex items-center gap-1.5 mb-2">
                                <flux:icon name="bolt" class="w-3.5 h-3.5 text-violet-500" />
                                Duration
                            </label>
                            <select
                                wire:model="newSessionDuration"
                                id="newSessionDuration"
                                name="newSessionDuration"
                                required
                                class="w-full rounded-xl border-0 ring-1 ring-slate-200 dark:ring-zinc-700 bg-white dark:bg-zinc-900 text-sm text-slate-900 dark:text-zinc-100 px-4 py-2.5 focus:ring-2 focus:ring-violet-500 dark:focus:ring-violet-400 transition"
                            >
                                <option value="30">30 minutes</option>
                                <option value="45">45 minutes</option>
                                <option value="60">1 hour</option>
                                <option value="90">1.5 hours</option>
                                <option value="120">2 hours</option>
                                <option value="180">3 hours</option>
                            </select>
                            @error('newSessionDuration')
                                <p class="text-xs text-rose-600 dark:text-rose-400 mt-1.5">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50/80 dark:bg-zinc-950/60 px-6 py-4 border-t border-slate-200/70 dark:border-zinc-800 flex gap-2 justify-end">
                    <button type="button" wire:click="closeCreateSessionModal" class="teacher-cta-ghost">
                        Cancel
                    </button>
                    <button type="submit" class="teacher-cta">
                        <flux:icon name="plus" class="w-4 h-4" />
                        Create Session
                    </button>
                </div>
            </form>
        </div>
    </flux:modal>

    {{-- ──────────────────────────────────────────────────────────
         START SESSION CONFIRMATION MODAL  -  compact, focused
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

// Listen for tab changes and update URL
document.addEventListener('livewire:init', () => {
    Livewire.on('update-url', (event) => {
        const tab = event.tab;
        const currentUrl = new URL(window.location);
        
        if (tab === 'overview') {
            // Remove tab parameter if returning to overview (default)
            currentUrl.searchParams.delete('tab');
        } else {
            // Set tab parameter for other tabs
            currentUrl.searchParams.set('tab', tab);
        }
        
        // Update URL without page reload
        window.history.pushState({}, '', currentUrl.toString());
    });
});
</script>