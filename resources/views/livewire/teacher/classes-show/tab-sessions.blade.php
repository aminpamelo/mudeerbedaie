@php
    $ongoingSession = $class->sessions->where('status', 'ongoing')->first();
    $nextSession = $class->sessions
        ->where('status', 'scheduled')
        ->where('session_date', '>', now()->toDateString())
        ->sortBy('session_date')
        ->first();
    $activeNowCount = $class->sessions->where('status', 'ongoing')->count();
    $sessionsByMonth = $this->sessions_by_month;
    $hasAnySessions = count($sessionsByMonth) > 0;
@endphp

<div class="space-y-6">
    {{-- ──────────────────────────────────────────────────────────
         STAT STRIP
         ────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <x-teacher.stat-card
            eyebrow="Total Sessions"
            :value="$this->total_sessions_count"
            tone="violet"
            icon="calendar"
        >
            <span class="text-violet-700/80 dark:text-violet-300/80 font-medium">All time</span>
        </x-teacher.stat-card>

        <x-teacher.stat-card
            eyebrow="Completed"
            :value="$this->completed_sessions_count"
            tone="emerald"
            icon="check-circle"
        >
            <span class="text-emerald-700/80 dark:text-emerald-300/80 font-medium">Wrapped up</span>
        </x-teacher.stat-card>

        <x-teacher.stat-card
            eyebrow="Upcoming"
            :value="$this->upcoming_sessions_count"
            tone="indigo"
            icon="clock"
        >
            <span class="text-violet-700/80 dark:text-violet-300/80 font-medium">Scheduled ahead</span>
        </x-teacher.stat-card>

        <x-teacher.stat-card
            eyebrow="Active Now"
            :value="$activeNowCount"
            tone="amber"
            icon="play"
        >
            <span class="text-amber-700/80 dark:text-amber-300/80 font-medium">Live sessions</span>
        </x-teacher.stat-card>
    </div>

    {{-- ──────────────────────────────────────────────────────────
         QUICK ACTION CARDS  -  Quick Start + Active Session
         ────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- Quick Start / Create CTA --}}
        @if($this->upcoming_sessions_count > 0 && $nextSession)
            <div class="teacher-card teacher-card-hover relative overflow-hidden p-5 sm:p-6">
                <div class="absolute -top-12 -right-12 w-44 h-44 rounded-full bg-gradient-to-br from-violet-400/30 to-violet-500/20 blur-2xl pointer-events-none"></div>
                <div class="relative">
                    <div class="flex items-center gap-2.5 mb-3">
                        <div class="rounded-lg bg-violet-500/10 dark:bg-violet-400/15 p-2">
                            <flux:icon name="play" class="w-4 h-4 text-violet-600 dark:text-violet-300" variant="solid" />
                        </div>
                        <h3 class="teacher-display text-base font-bold text-slate-900 dark:text-white">Quick Start</h3>
                    </div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-violet-700/80 dark:text-violet-300/90">Next session</div>
                    <div class="teacher-display teacher-num text-xl font-bold text-slate-900 dark:text-white mt-1">
                        {{ $nextSession->formatted_date_time }}
                    </div>
                    <p class="text-xs text-slate-500 dark:text-zinc-400 mt-1">
                        {{ $nextSession->formatted_duration }} · {{ $class->title }}
                    </p>
                    <button
                        type="button"
                        wire:click="markSessionAsOngoing({{ $nextSession->id }})"
                        class="teacher-cta mt-4 w-full justify-center"
                    >
                        <flux:icon name="play" class="w-4 h-4" variant="solid" />
                        Start Session Now
                    </button>
                </div>
            </div>
        @else
            <div class="teacher-card teacher-card-hover relative overflow-hidden p-5 sm:p-6">
                <div class="absolute -top-12 -right-12 w-44 h-44 rounded-full bg-gradient-to-br from-violet-400/30 to-violet-500/20 blur-2xl pointer-events-none"></div>
                <div class="relative text-center py-2">
                    <div class="inline-flex w-12 h-12 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-600 to-violet-500 text-white shadow-lg shadow-violet-500/30 mb-3">
                        <flux:icon name="plus" class="w-5 h-5" />
                    </div>
                    <h3 class="teacher-display text-base font-bold text-slate-900 dark:text-white">No Upcoming Sessions</h3>
                    <p class="text-sm text-slate-500 dark:text-zinc-400 mt-1">Create a new session to get started.</p>
                    <button
                        type="button"
                        wire:click="openCreateSessionModal"
                        class="teacher-cta mt-4 w-full justify-center"
                    >
                        <flux:icon name="plus" class="w-4 h-4" />
                        Create New Session
                    </button>
                </div>
            </div>
        @endif

        {{-- Active Session Management --}}
        @if($ongoingSession)
            <div class="teacher-card teacher-card-hover relative overflow-hidden border-l-4 border-l-emerald-500 p-5 sm:p-6"
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
                    const startTime = new Date('{{ $ongoingSession->started_at ? $ongoingSession->started_at->toISOString() : now()->toISOString() }}').getTime();
                    elapsedTime = Math.floor((Date.now() - startTime) / 1000);
                    timer = setInterval(() => {
                        elapsedTime = Math.floor((Date.now() - startTime) / 1000);
                    }, 1000);
                 "
                 x-destroy="timer && clearInterval(timer)">
                <div class="absolute -top-12 -right-12 w-44 h-44 rounded-full bg-gradient-to-br from-emerald-400/30 to-teal-500/20 blur-2xl pointer-events-none"></div>
                <div class="relative">
                    <div class="flex items-center gap-2.5 mb-3">
                        <span class="teacher-live-dot"></span>
                        <h3 class="teacher-display text-base font-bold text-slate-900 dark:text-white">Session Running</h3>
                        <span class="ml-auto inline-flex items-center gap-1.5 rounded-full bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 px-2.5 py-0.5 text-[11px] font-semibold">
                            Live
                        </span>
                    </div>

                    <div class="text-xs font-semibold uppercase tracking-wider text-emerald-700/80 dark:text-emerald-300/90">Started</div>
                    <div class="teacher-display teacher-num text-base font-bold text-slate-900 dark:text-white mt-0.5">
                        {{ $ongoingSession->formatted_date_time }}
                    </div>

                    <div class="mt-3 rounded-xl bg-emerald-50/70 dark:bg-emerald-950/30 ring-1 ring-emerald-200/70 dark:ring-emerald-800/50 px-4 py-3 flex items-center justify-between">
                        <span class="text-[11px] font-bold uppercase tracking-[0.18em] text-emerald-700 dark:text-emerald-300">Elapsed</span>
                        <span class="teacher-num font-mono text-xl font-bold text-emerald-700 dark:text-emerald-300" x-text="formatTime(elapsedTime)"></span>
                    </div>

                    @if($ongoingSession->hasBookmark())
                        <div class="mt-3 rounded-xl bg-sky-50 dark:bg-sky-950/30 ring-1 ring-sky-200/70 dark:ring-sky-800/50 px-4 py-2.5">
                            <div class="text-[11px] font-bold uppercase tracking-wider text-sky-700 dark:text-sky-300 mb-0.5">Current progress</div>
                            <div class="text-xs text-sky-900 dark:text-sky-100">{{ $ongoingSession->formatted_bookmark }}</div>
                        </div>
                    @endif

                    <div class="mt-4 flex gap-2">
                        <button
                            type="button"
                            wire:click="openSessionModal({{ $ongoingSession->id }})"
                            class="teacher-cta-ghost flex-1 justify-center"
                        >
                            <flux:icon name="users" class="w-4 h-4" />
                            Manage
                        </button>
                        <button
                            type="button"
                            wire:click="openCompletionModal({{ $ongoingSession->id }})"
                            class="teacher-cta flex-1 justify-center"
                        >
                            <flux:icon name="check" class="w-4 h-4" />
                            Complete
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- ──────────────────────────────────────────────────────────
         SESSIONS TIMELINE  -  grouped by month
         ────────────────────────────────────────────────────────── --}}
    @if($this->total_sessions_count > 0 && $hasAnySessions)
        <div class="teacher-card p-5 sm:p-6">
            <div class="flex items-start justify-between gap-3 mb-5 flex-wrap">
                <div>
                    <h2 class="teacher-display text-xl font-bold text-slate-900 dark:text-white">Session Calendar</h2>
                    <p class="text-sm text-slate-500 dark:text-zinc-400 mt-0.5">{{ $this->total_sessions_count }} total {{ Str::plural('session', $this->total_sessions_count) }}</p>
                </div>
                <button
                    type="button"
                    wire:click="openCreateSessionModal"
                    class="teacher-cta"
                >
                    <flux:icon name="plus" class="w-4 h-4" />
                    Create Session
                </button>
            </div>

            <div class="space-y-6">
                @foreach($sessionsByMonth as $monthData)
                    {{-- Month group header --}}
                    <div wire:key="month-{{ $monthData['year'] }}-{{ $monthData['month'] }}">
                        <div class="flex items-center justify-between gap-3 mb-3 flex-wrap">
                            <div class="flex items-center gap-2.5">
                                <div class="rounded-lg bg-violet-500/10 dark:bg-violet-400/15 p-1.5">
                                    <flux:icon name="calendar" class="w-4 h-4 text-violet-600 dark:text-violet-300" />
                                </div>
                                <h3 class="teacher-display text-base font-bold text-slate-900 dark:text-white">
                                    {{ $monthData['month_name'] }} {{ $monthData['year'] }}
                                </h3>
                                <span class="inline-flex items-center rounded-full bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-300 px-2 py-0.5 text-[11px] font-semibold">
                                    {{ $monthData['stats']['total'] }} {{ Str::plural('session', $monthData['stats']['total']) }}
                                </span>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                @if($monthData['stats']['ongoing'] > 0)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 px-2 py-0.5 text-[11px] font-semibold">
                                        <span class="teacher-live-dot"></span>
                                        {{ $monthData['stats']['ongoing'] }} live
                                    </span>
                                @endif
                                @if($monthData['stats']['completed'] > 0)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 px-2 py-0.5 text-[11px] font-semibold">
                                        <flux:icon name="check" class="w-3 h-3" />
                                        {{ $monthData['stats']['completed'] }} done
                                    </span>
                                @endif
                                @if($monthData['stats']['upcoming'] > 0)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-violet-100 dark:bg-violet-500/15 text-violet-700 dark:text-violet-300 px-2 py-0.5 text-[11px] font-semibold">
                                        <flux:icon name="clock" class="w-3 h-3" />
                                        {{ $monthData['stats']['upcoming'] }} upcoming
                                    </span>
                                @endif
                                @if($monthData['stats']['cancelled'] > 0)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300 px-2 py-0.5 text-[11px] font-semibold">
                                        <flux:icon name="x-mark" class="w-3 h-3" />
                                        {{ $monthData['stats']['cancelled'] }} cancelled
                                    </span>
                                @endif
                                @if($monthData['stats']['no_show'] > 0)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 px-2 py-0.5 text-[11px] font-semibold">
                                        <flux:icon name="exclamation-triangle" class="w-3 h-3" />
                                        {{ $monthData['stats']['no_show'] }} no-show
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Session rows --}}
                        <div class="space-y-3">
                            @foreach($monthData['sessions'] as $session)
                                @php
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

                                    $presentCount = $session->attendances->where('status', 'present')->count();
                                    $totalCount = $session->attendances->count();
                                    $attendanceRate = $totalCount > 0 ? round(($presentCount / $totalCount) * 100) : 0;
                                @endphp

                                <div
                                    wire:key="session-row-{{ $session->id }}"
                                    class="group relative flex flex-col lg:flex-row lg:items-center gap-4 rounded-xl border-l-4 {{ $borderClass }} {{ $cardTone }} ring-1 px-4 py-4 hover:shadow-md hover:-translate-y-px transition-all"
                                >
                                    {{-- Time block --}}
                                    <div class="flex lg:flex-col lg:w-[96px] lg:items-start gap-2 lg:gap-0 shrink-0">
                                        <div class="teacher-display teacher-num text-base font-bold text-slate-900 dark:text-white">
                                            {{ $session->session_time->format('g:i A') }}
                                        </div>
                                        <div class="text-xs text-slate-500 dark:text-zinc-400">
                                            {{ $session->session_date->format('M d') }}
                                        </div>
                                        <div class="text-[11px] text-slate-400 dark:text-zinc-500 lg:mt-0.5">
                                            {{ $session->formatted_duration }}
                                        </div>
                                    </div>

                                    {{-- Class info --}}
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <h4 class="font-semibold text-slate-900 dark:text-white truncate">
                                                {{ $session->formatted_date_time }}
                                            </h4>

                                            <x-teacher.status-pill :status="$session->status" size="sm" />

                                            @if($session->isOngoing())
                                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 px-2 py-0.5 text-[11px] font-semibold"
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
                                                    <span class="font-mono font-bold" x-text="formatTime(elapsedTime)"></span>
                                                </span>
                                            @endif
                                        </div>

                                        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-slate-500 dark:text-zinc-400">
                                            @if($session->hasBookmark())
                                                <span class="inline-flex items-center gap-1.5 max-w-xs truncate" title="{{ $session->bookmark }}">
                                                    <flux:icon name="bookmark" class="w-3.5 h-3.5 text-amber-500 dark:text-amber-400 shrink-0" />
                                                    <span class="text-slate-700 dark:text-zinc-200 truncate">{{ $session->formatted_bookmark }}</span>
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1.5 text-slate-400 dark:text-zinc-500">
                                                    <flux:icon name="bookmark" class="w-3.5 h-3.5" />
                                                    No progress notes
                                                </span>
                                            @endif

                                            @if($totalCount === 0)
                                                <span class="inline-flex items-center gap-1.5 text-slate-400 dark:text-zinc-500">
                                                    <flux:icon name="users" class="w-3.5 h-3.5" />
                                                    Attendance not recorded
                                                </span>
                                            @elseif($totalCount === 1)
                                                @if($presentCount === 1)
                                                    <span class="inline-flex items-center gap-1.5 text-emerald-700 dark:text-emerald-300 font-semibold">
                                                        <flux:icon name="check" class="w-3.5 h-3.5" />
                                                        Present
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1.5 text-rose-700 dark:text-rose-300 font-semibold">
                                                        <flux:icon name="x-mark" class="w-3.5 h-3.5" />
                                                        Absent
                                                    </span>
                                                @endif
                                            @else
                                                @php
                                                    $rateColor = $attendanceRate >= 80
                                                        ? 'text-emerald-700 dark:text-emerald-300'
                                                        : ($attendanceRate >= 60 ? 'text-amber-700 dark:text-amber-300' : 'text-rose-700 dark:text-rose-300');
                                                    $rateBar = $attendanceRate >= 80
                                                        ? 'bg-emerald-500'
                                                        : ($attendanceRate >= 60 ? 'bg-amber-500' : 'bg-rose-500');
                                                @endphp
                                                <span class="inline-flex items-center gap-2">
                                                    <flux:icon name="users" class="w-3.5 h-3.5 text-violet-500 dark:text-violet-400" />
                                                    <span class="font-semibold {{ $rateColor }}">{{ $presentCount }}/{{ $totalCount }}</span>
                                                    <span class="inline-block w-16 h-1.5 rounded-full bg-slate-200 dark:bg-zinc-700 overflow-hidden">
                                                        <span class="block h-full rounded-full {{ $rateBar }}" style="width: {{ $attendanceRate }}%"></span>
                                                    </span>
                                                    <span class="text-[11px] text-slate-400 dark:text-zinc-500">{{ $attendanceRate }}%</span>
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Actions --}}
                                    <div class="flex flex-wrap items-center gap-2 lg:justify-end shrink-0">
                                        @if($session->isScheduled())
                                            <button
                                                type="button"
                                                wire:click="markSessionAsOngoing({{ $session->id }})"
                                                class="teacher-cta"
                                            >
                                                <flux:icon name="play" class="w-4 h-4" />
                                                Start
                                            </button>
                                        @elseif($session->isOngoing())
                                            <button
                                                type="button"
                                                wire:click="openSessionModal({{ $session->id }})"
                                                class="teacher-cta-ghost"
                                            >
                                                <flux:icon name="users" class="w-4 h-4" />
                                                Manage
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="openCompletionModal({{ $session->id }})"
                                                class="teacher-cta"
                                            >
                                                <flux:icon name="check" class="w-4 h-4" />
                                                Complete
                                            </button>
                                        @elseif($session->isCompleted())
                                            <button
                                                type="button"
                                                wire:click="openAttendanceViewModal({{ $session->id }})"
                                                class="teacher-cta-ghost"
                                            >
                                                <flux:icon name="eye" class="w-4 h-4" />
                                                View Details
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <x-teacher.empty-state
            icon="calendar"
            title="No sessions scheduled yet"
            message="This class doesn't have any sessions yet. Create one to start tracking attendance and progress."
        >
            <button
                type="button"
                wire:click="openCreateSessionModal"
                class="teacher-cta"
            >
                <flux:icon name="plus" class="w-4 h-4" />
                Create New Session
            </button>
        </x-teacher.empty-state>
    @endif
</div>
