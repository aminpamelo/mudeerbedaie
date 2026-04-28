<?php

use App\Models\ClassSession;
use App\Support\TeacherStartBriefing;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.teacher')] class extends Component
{
    use WithPagination;

    public string $dateFilter = 'upcoming';

    public string $classFilter = 'all';

    public string $statusFilter = 'all';

    public string $search = '';

    public bool $showStartConfirmation = false;

    public ?int $sessionToStartId = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'dateFilter' => ['except' => 'upcoming'],
        'classFilter' => ['except' => 'all'],
        'statusFilter' => ['except' => 'all'],
    ];

    public function with()
    {
        $teacher = auth()->user()->teacher;

        if (! $teacher) {
            return [
                'sessions' => collect(),
                'classes' => collect(),
                'statistics' => $this->getEmptyStatistics(),
            ];
        }

        // Get teacher's classes
        $classes = $teacher->classes()->with('course')->get();

        // Build sessions query
        $query = ClassSession::with(['class.course', 'attendances.student.user'])
            ->whereHas('class', function ($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id);
            });

        // Apply date filter
        $today = now()->startOfDay();
        switch ($this->dateFilter) {
            case 'today':
                $query->whereDate('session_date', $today);
                break;
            case 'upcoming':
                $query->where('session_date', '>=', $today);
                break;
            case 'past':
                $query->where('session_date', '<', $today);
                break;
            case 'this_week':
                $query->whereBetween('session_date', [
                    $today->startOfWeek(),
                    $today->copy()->endOfWeek(),
                ]);
                break;
        }

        // Apply class filter
        if ($this->classFilter !== 'all') {
            $query->where('class_id', $this->classFilter);
        }

        // Apply status filter
        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        // Apply search filter
        if ($this->search) {
            $query->where(function ($q) {
                $q->whereHas('class', function ($classQuery) {
                    $classQuery->where('title', 'like', '%'.$this->search.'%')
                        ->orWhereHas('course', function ($courseQuery) {
                            $courseQuery->where('name', 'like', '%'.$this->search.'%');
                        });
                })
                    ->orWhere('teacher_notes', 'like', '%'.$this->search.'%')
                    ->orWhereHas('attendances.student.user', function ($userQuery) {
                        $userQuery->where('name', 'like', '%'.$this->search.'%');
                    });
            });
        }

        $sessions = $query->orderBy('session_date', 'desc')
            ->orderBy('session_time', 'desc')
            ->paginate(10);

        // Calculate statistics (using separate query for accurate counts)
        $statsQuery = ClassSession::whereHas('class', function ($q) use ($teacher) {
            $q->where('teacher_id', $teacher->id);
        });

        $statistics = [
            'total_sessions' => $statsQuery->count(),
            'upcoming_sessions' => $statsQuery->where('session_date', '>=', $today)->count(),
            'completed_sessions' => $statsQuery->where('status', 'completed')->count(),
            'cancelled_sessions' => $statsQuery->where('status', 'cancelled')->count(),
        ];

        return [
            'sessions' => $sessions,
            'classes' => $classes,
            'statistics' => $statistics,
        ];
    }

    private function getEmptyStatistics(): array
    {
        return [
            'total_sessions' => 0,
            'upcoming_sessions' => 0,
            'completed_sessions' => 0,
            'cancelled_sessions' => 0,
        ];
    }

    public function updatedDateFilter()
    {
        $this->resetPage();
    }

    public function updatedClassFilter()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function clearSearch()
    {
        $this->search = '';
        $this->resetPage();
    }

    public function closeStartConfirmation()
    {
        $this->showStartConfirmation = false;
        $this->sessionToStartId = null;
    }

    public function requestStartSession($sessionId)
    {
        $this->sessionToStartId = $sessionId;
        $this->showStartConfirmation = true;
    }

    public function confirmStartSession()
    {
        if ($this->sessionToStartId) {
            $this->startSession($this->sessionToStartId);
        }
        $this->closeStartConfirmation();
    }

    public function getStartBriefingProperty(): ?array
    {
        if (! $this->sessionToStartId) {
            return null;
        }

        $session = ClassSession::with(['class.course', 'class.pics', 'class.activeStudents'])->find($this->sessionToStartId);

        return TeacherStartBriefing::build($session, $session?->class);
    }

    public function startSession($sessionId)
    {
        $session = ClassSession::find($sessionId);
        if ($session && $session->isScheduled()) {
            $session->markAsOngoing();
            session()->flash('success', 'Session started successfully.');
        }
    }

    public function completeSession($sessionId)
    {
        $session = ClassSession::find($sessionId);
        if ($session && $session->isOngoing()) {
            $session->markCompleted();
            session()->flash('success', 'Session completed successfully.');
        }
    }

    public function cancelSession($sessionId)
    {
        $session = ClassSession::find($sessionId);
        if ($session && $session->isScheduled()) {
            $session->cancel();
            session()->flash('success', 'Session cancelled.');
        }
    }

    public function markAsNoShow($sessionId)
    {
        $session = ClassSession::find($sessionId);
        if ($session && ($session->isScheduled() || $session->isOngoing())) {
            $session->markAsNoShow();
            session()->flash('success', 'Session marked as no-show.');
        }
    }

    public function exportSessions()
    {
        $teacher = auth()->user()->teacher;

        if (! $teacher) {
            session()->flash('error', 'Teacher not found.');

            return;
        }

        // Build the same query as the main sessions query
        $query = ClassSession::with(['class.course', 'attendances.student.user'])
            ->whereHas('class', function ($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id);
            });

        // Apply the same filters
        $today = now()->startOfDay();
        switch ($this->dateFilter) {
            case 'today':
                $query->whereDate('session_date', $today);
                break;
            case 'upcoming':
                $query->where('session_date', '>=', $today);
                break;
            case 'past':
                $query->where('session_date', '<', $today);
                break;
            case 'this_week':
                $query->whereBetween('session_date', [
                    $today->startOfWeek(),
                    $today->copy()->endOfWeek(),
                ]);
                break;
        }

        if ($this->classFilter !== 'all') {
            $query->where('class_id', $this->classFilter);
        }

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->whereHas('class', function ($classQuery) {
                    $classQuery->where('title', 'like', '%'.$this->search.'%')
                        ->orWhereHas('course', function ($courseQuery) {
                            $courseQuery->where('name', 'like', '%'.$this->search.'%');
                        });
                })
                    ->orWhere('teacher_notes', 'like', '%'.$this->search.'%')
                    ->orWhereHas('attendances.student.user', function ($userQuery) {
                        $userQuery->where('name', 'like', '%'.$this->search.'%');
                    });
            });
        }

        $sessions = $query->orderBy('session_date', 'desc')
            ->orderBy('session_time', 'desc')
            ->get();

        // Create CSV content
        $csvContent = "Date,Time,Class,Course,Duration,Status,Students,Present,Allowance,Notes\n";

        foreach ($sessions as $session) {
            $attendanceCount = $session->attendances->count();
            $presentCount = $session->attendances->whereIn('status', ['present', 'late'])->count();
            $allowance = $session->allowance_amount ? 'RM'.number_format($session->allowance_amount, 2) : '';

            $csvContent .= sprintf(
                "%s,%s,%s,%s,%s,%s,%d,%d,%s,%s\n",
                $session->session_date->format('Y-m-d'),
                $session->session_time->format('H:i'),
                '"'.str_replace('"', '""', $session->class?->title ?? 'N/A').'"',
                '"'.str_replace('"', '""', $session->class->course?->name ?? 'N/A').'"',
                $session->duration_minutes.'min',
                $session->status,
                $attendanceCount,
                $presentCount,
                $allowance,
                '"'.str_replace('"', '""', $session->teacher_notes ?? '').'"'
            );
        }

        // Generate filename with current date and filters
        $filename = 'sessions_export_'.date('Y-m-d_H-i-s').'.csv';

        return response()->streamDownload(function () use ($csvContent) {
            echo $csvContent;
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}; ?>

<div class="teacher-app w-full">
    <x-teacher.page-header
        title="My Sessions"
        subtitle="All your teaching sessions"
    >
        <button type="button" wire:click="exportSessions" class="teacher-cta-ghost">
            <flux:icon name="arrow-down-tray" class="w-4 h-4" />
            Export CSV
        </button>
    </x-teacher.page-header>

    @if(session('success'))
        <div class="mb-6 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 ring-1 ring-emerald-200 dark:ring-emerald-800/50 px-4 py-3">
            <p class="text-sm text-emerald-800 dark:text-emerald-200">{{ session('success') }}</p>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 rounded-xl bg-rose-50 dark:bg-rose-950/30 ring-1 ring-rose-200 dark:ring-rose-800/50 px-4 py-3">
            <p class="text-sm text-rose-800 dark:text-rose-200">{{ session('error') }}</p>
        </div>
    @endif

    {{-- ──────────────────────────────────────────────────────────
         STAT STRIP
         ────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <x-teacher.stat-card
            eyebrow="Total Sessions"
            :value="$statistics['total_sessions']"
            tone="indigo"
            icon="clock"
        >
            <span class="text-violet-700/80 dark:text-violet-300/80 font-medium">All sessions</span>
        </x-teacher.stat-card>

        <x-teacher.stat-card
            eyebrow="Upcoming"
            :value="$statistics['upcoming_sessions']"
            tone="violet"
            icon="calendar"
        >
            <span class="text-violet-700/80 dark:text-violet-300/80 font-medium">Scheduled ahead</span>
        </x-teacher.stat-card>

        <x-teacher.stat-card
            eyebrow="Completed"
            :value="$statistics['completed_sessions']"
            tone="emerald"
            icon="check-circle"
        >
            <span class="text-emerald-700/80 dark:text-emerald-300/80 font-medium">Wrapped up</span>
        </x-teacher.stat-card>

        <x-teacher.stat-card
            eyebrow="Cancelled"
            :value="$statistics['cancelled_sessions']"
            tone="amber"
            icon="x-circle"
        >
            <span class="text-amber-700/80 dark:text-amber-300/80 font-medium">Did not run</span>
        </x-teacher.stat-card>
    </div>

    {{-- ──────────────────────────────────────────────────────────
         FILTER BAR
         ────────────────────────────────────────────────────────── --}}
    <x-teacher.filter-bar>
        <div class="flex-1 min-w-[220px] relative">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Search by class, course, notes, or student..."
                icon="magnifying-glass"
                class="w-full"
            />
            @if($search)
                <button
                    type="button"
                    wire:click="clearSearch"
                    class="absolute right-2 top-1/2 -translate-y-1/2 inline-flex items-center justify-center w-7 h-7 rounded-full text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-zinc-800 transition"
                    aria-label="Clear search"
                >
                    <flux:icon name="x-mark" class="w-4 h-4" />
                </button>
            @endif
        </div>

        <flux:select wire:model.live="dateFilter" class="min-w-40">
            <option value="upcoming">Upcoming Sessions</option>
            <option value="today">Today's Sessions</option>
            <option value="this_week">This Week</option>
            <option value="past">Past Sessions</option>
        </flux:select>

        <flux:select wire:model.live="classFilter" placeholder="All Classes" class="min-w-40">
            <option value="all">All Classes</option>
            @foreach($classes as $class)
                <option value="{{ $class->id }}">{{ $class->title }}</option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="statusFilter" placeholder="All Status" class="min-w-32">
            <option value="all">All Status</option>
            <option value="scheduled">Scheduled</option>
            <option value="ongoing">Ongoing</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
            <option value="no_show">No Show</option>
            <option value="rescheduled">Rescheduled</option>
        </flux:select>
    </x-teacher.filter-bar>

    @if($sessions->count() > 0)
        {{-- ──────────────────────────────────────────────────────────
             SESSIONS LIST
             ────────────────────────────────────────────────────────── --}}
        <div class="space-y-3">
            @foreach($sessions as $session)
                @php
                    $isUpcoming = $session->session_date >= now()->startOfDay();
                    $isToday = $session->session_date->isToday();
                    $attendanceCount = $session->attendances->count();
                    $presentCount = $session->attendances->whereIn('status', ['present', 'late'])->count();

                    $borderClass = match($session->status) {
                        'ongoing'   => 'border-l-emerald-500',
                        'completed' => 'border-l-slate-300 dark:border-l-zinc-700',
                        'cancelled' => 'border-l-rose-500',
                        'no_show'   => 'border-l-amber-500',
                        default     => 'border-l-violet-500',
                    };

                    $cardTone = match($session->status) {
                        'ongoing'   => 'bg-emerald-50/70 dark:bg-emerald-950/20 ring-emerald-200/60 dark:ring-emerald-800/40',
                        'completed' => 'bg-slate-50 dark:bg-zinc-900/40 ring-slate-200/60 dark:ring-zinc-800',
                        'cancelled' => 'bg-rose-50/60 dark:bg-rose-950/20 ring-rose-200/60 dark:ring-rose-800/40',
                        'no_show'   => 'bg-amber-50/60 dark:bg-amber-950/20 ring-amber-200/60 dark:ring-amber-800/40',
                        default     => 'bg-white dark:bg-zinc-900/40 ring-slate-200/70 dark:ring-zinc-800',
                    };
                @endphp

                <div
                    wire:key="session-row-{{ $session->id }}"
                    class="group relative flex flex-col lg:flex-row lg:items-center gap-4 rounded-xl border-l-4 {{ $borderClass }} {{ $cardTone }} ring-1 px-4 py-4 hover:shadow-md hover:-translate-y-px transition-all"
                >
                    {{-- Time block --}}
                    <div class="flex lg:flex-col lg:w-[88px] lg:items-start gap-2 lg:gap-0 shrink-0">
                        <div class="teacher-display teacher-num text-base font-bold text-slate-900 dark:text-white">
                            {{ $session->session_time->format('g:i A') }}
                        </div>
                        <div class="text-xs text-slate-500 dark:text-zinc-400">
                            {{ $session->session_date->format('M d, Y') }}
                        </div>
                        <div class="text-[11px] text-slate-400 dark:text-zinc-500 lg:mt-0.5">
                            {{ $session->duration_minutes }} min
                        </div>
                    </div>

                    {{-- Class info --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-semibold text-slate-900 dark:text-white truncate">
                                {{ $session->class->course?->name ?? 'N/A' }}
                            </h3>

                            <x-teacher.status-pill :status="$session->status" size="sm" />

                            @if($isToday)
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 px-2 py-0.5 text-[11px] font-semibold">
                                    <flux:icon name="sparkles" class="w-3 h-3" />
                                    Today
                                </span>
                            @endif
                        </div>

                        <p class="text-xs text-slate-500 dark:text-zinc-400 mt-1 truncate">
                            {{ $session->class->title }}
                            @if($session->topic)
                                · Topic: {{ $session->topic }}
                            @endif
                        </p>

                        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-zinc-400">
                            <span class="inline-flex items-center gap-1">
                                <flux:icon name="clock" class="w-3.5 h-3.5 text-violet-500 dark:text-violet-400" />
                                {{ $attendanceCount }} {{ Str::plural('student', $attendanceCount) }}
                            </span>
                            @if($session->status === 'completed')
                                <span class="inline-flex items-center gap-1">
                                    <flux:icon name="check-circle" class="w-3.5 h-3.5 text-emerald-500 dark:text-emerald-400" />
                                    {{ $attendanceCount > 0 ? round(($presentCount / $attendanceCount) * 100) : 0 }}% present
                                </span>
                                @if($session->allowance_amount)
                                    <span class="inline-flex items-center gap-1 font-semibold text-emerald-700 dark:text-emerald-300">
                                        RM{{ number_format($session->allowance_amount, 2) }}
                                    </span>
                                @endif
                            @endif
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex flex-wrap items-center gap-2 lg:justify-end shrink-0">
                        @if($session->status === 'ongoing')
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
                                    const startTime = new Date('{{ $session->started_at ? $session->started_at->toISOString() : now()->toISOString() }}').getTime();
                                    elapsedTime = Math.floor((Date.now() - startTime) / 1000);
                                    timer = setInterval(() => {
                                        elapsedTime = Math.floor((Date.now() - startTime) / 1000);
                                    }, 1000);
                                "
                                x-destroy="timer && clearInterval(timer)">
                                <span class="teacher-live-dot"></span>
                                <span class="font-mono font-bold text-xs text-emerald-700 dark:text-emerald-300" x-text="formatTime(elapsedTime)"></span>
                            </div>
                        @endif

                        @if($isUpcoming && $session->status === 'scheduled')
                            <button
                                type="button"
                                wire:click="requestStartSession({{ $session->id }})"
                                class="teacher-cta"
                            >
                                <flux:icon name="play" class="w-4 h-4" />
                                Start
                            </button>
                        @elseif($session->status === 'ongoing')
                            <button
                                type="button"
                                wire:click="completeSession({{ $session->id }})"
                                class="teacher-cta"
                            >
                                <flux:icon name="check-circle" class="w-4 h-4" />
                                Complete
                            </button>
                        @endif

                        <a
                            href="{{ route('teacher.sessions.show', $session) }}"
                            class="teacher-cta-ghost"
                        >
                            <flux:icon name="eye" class="w-4 h-4" />
                            View
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        <div class="mt-6">
            {{ $sessions->links() }}
        </div>
    @else
        @if($search || $dateFilter !== 'upcoming' || $classFilter !== 'all' || $statusFilter !== 'all')
            <x-teacher.empty-state
                icon="clock"
                title="No sessions found"
                message="No sessions match your current filters. Try adjusting them to see more results."
            >
                <button
                    type="button"
                    wire:click="$set('search', ''); $set('dateFilter', 'upcoming'); $set('classFilter', 'all'); $set('statusFilter', 'all')"
                    class="teacher-cta-ghost"
                >
                    Clear all filters
                </button>
            </x-teacher.empty-state>
        @else
            <x-teacher.empty-state
                icon="calendar"
                title="No sessions scheduled"
                message="You don't have any sessions yet. Sessions will appear here once they are created for your classes."
            >
                <a href="{{ route('teacher.classes.index') }}" wire:navigate class="teacher-cta">
                    <flux:icon name="arrow-right" class="w-4 h-4" />
                    View My Classes
                </a>
            </x-teacher.empty-state>
        @endif
    @endif

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