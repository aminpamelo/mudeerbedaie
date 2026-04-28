@php
    $totalSessions = $this->total_sessions_count;
    $completedSessions = $this->completed_sessions_count;
    $upcomingSessions = $this->upcoming_sessions_count;
    $totalRecords = $this->total_attendance_records;
    $presentCount = $this->total_present_count;
    $absentCount = $this->total_absent_count;
    $lateCount = $this->total_late_count;
    $attendanceRate = $totalRecords > 0 ? $this->overall_attendance_rate : 0;

    $nextSession = $class->sessions
        ->where('status', 'scheduled')
        ->where('session_date', '>', now()->toDateString())
        ->sortBy('session_date')
        ->first();
    $ongoingSession = $class->sessions->where('status', 'ongoing')->first();

    $statusKey = strtolower($class->status);
@endphp

<div class="teacher-app space-y-6">
    {{-- ──────────────────────────────────────────────────────────
         CLASS HERO  -  gradient summary card
         ────────────────────────────────────────────────────────── --}}
    <div class="teacher-hero relative overflow-hidden rounded-2xl text-white shadow-[0_18px_48px_-16px_rgba(79,70,229,0.55)]">
        <div class="teacher-grain absolute inset-0 pointer-events-none"></div>

        <div class="relative px-6 py-6 sm:px-8 sm:py-8 grid gap-6 lg:grid-cols-[1.6fr_1fr] items-center">
            <div>
                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-medium tracking-wide ring-1 ring-white/25 backdrop-blur">
                        <flux:icon name="academic-cap" class="w-3.5 h-3.5" />
                        {{ $class->course->name }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-medium ring-1 ring-white/25 backdrop-blur">
                        @if($class->isIndividual())
                            <flux:icon name="user" class="w-3.5 h-3.5" />
                            Individual
                        @else
                            <flux:icon name="users" class="w-3.5 h-3.5" />
                            Group
                            @if($class->max_capacity)
                                · {{ $class->max_capacity }} max
                            @endif
                        @endif
                    </span>
                    @if($statusKey === 'active')
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-400/90 px-3 py-1 text-xs font-bold text-emerald-950">
                            <span class="teacher-live-dot bg-emerald-700 !shadow-none"></span>
                            Active
                        </span>
                    @elseif($statusKey === 'draft')
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/95 text-violet-700 px-3 py-1 text-xs font-bold">
                            <flux:icon name="pencil" class="w-3 h-3" />
                            Draft
                        </span>
                    @elseif($statusKey === 'completed')
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/95 text-violet-700 px-3 py-1 text-xs font-bold">
                            <flux:icon name="check" class="w-3 h-3" />
                            Completed
                        </span>
                    @elseif($statusKey === 'suspended')
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-400/95 text-amber-950 px-3 py-1 text-xs font-bold">
                            <flux:icon name="exclamation-triangle" class="w-3 h-3" />
                            Suspended
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-400/90 text-rose-950 px-3 py-1 text-xs font-bold">
                            {{ ucfirst($class->status) }}
                        </span>
                    @endif
                </div>

                <h1 class="teacher-display text-2xl sm:text-3xl lg:text-4xl font-bold leading-tight">
                    {{ $class->title }}
                </h1>
                @if($class->description)
                    <p class="mt-2 text-white/80 text-sm sm:text-base max-w-2xl line-clamp-2">{{ $class->description }}</p>
                @endif

                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-medium ring-1 ring-white/20 backdrop-blur">
                        <flux:icon name="clock" class="w-3.5 h-3.5" />
                        {{ $class->formatted_duration }}
                    </span>
                    @if($class->location)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-medium ring-1 ring-white/20 backdrop-blur">
                            <flux:icon name="map-pin" class="w-3.5 h-3.5" />
                            {{ $class->location }}
                        </span>
                    @endif
                    @if($class->meeting_url)
                        <a href="{{ $class->meeting_url }}" target="_blank"
                            class="inline-flex items-center gap-1.5 rounded-full bg-white/95 text-violet-700 px-3 py-1 text-xs font-bold hover:bg-white transition">
                            <flux:icon name="arrow-right" class="w-3.5 h-3.5" />
                            Join Meeting
                        </a>
                    @endif
                </div>
            </div>

            {{-- Quick action card --}}
            @if($ongoingSession)
                <div class="teacher-pill-gradient rounded-2xl p-5 sm:p-6 shadow-xl shadow-black/10">
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <span class="text-[11px] font-bold tracking-[0.18em] text-emerald-700 uppercase inline-flex items-center gap-1.5">
                            <span class="teacher-live-dot"></span>
                            Live now
                        </span>
                        <span class="text-xs font-semibold text-violet-600 bg-violet-100 rounded-full px-2.5 py-1">
                            {{ $ongoingSession->formatted_date_time }}
                        </span>
                    </div>

                    <div
                        x-data="sessionTimer('{{ $ongoingSession->started_at ? $ongoingSession->started_at->toISOString() : now()->toISOString() }}')"
                        x-init="startTimer()"
                        class="flex items-center gap-2.5 rounded-xl bg-emerald-500/10 ring-1 ring-emerald-500/30 px-3 py-2 mb-4">
                        <span class="teacher-live-dot"></span>
                        <span class="text-[11px] font-bold uppercase tracking-[0.18em] text-emerald-700">Running</span>
                        <span class="ml-auto teacher-num font-mono text-base font-bold text-emerald-700" x-text="formattedTime"></span>
                    </div>

                    @if($ongoingSession->hasBookmark())
                        <div class="mb-3 rounded-lg bg-amber-50 ring-1 ring-amber-200 px-3 py-2">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-amber-700 mb-0.5">Bookmark</div>
                            <div class="text-sm text-amber-900 font-medium line-clamp-2">{{ $ongoingSession->bookmark }}</div>
                        </div>
                    @endif

                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="openSessionModal({{ $ongoingSession->id }})"
                            class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl px-3 py-2.5 text-sm font-semibold text-violet-700 ring-1 ring-violet-200 hover:bg-violet-50 transition">
                            <flux:icon name="users" class="w-4 h-4" />
                            Manage
                        </button>
                        <button type="button" wire:click="switchToCompletionState()"
                            class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 px-3 py-2.5 text-sm font-semibold text-white shadow-md shadow-emerald-500/25 hover:shadow-lg transition">
                            <flux:icon name="check" class="w-4 h-4" />
                            Complete
                        </button>
                    </div>
                </div>
            @elseif($nextSession)
                <div class="teacher-pill-gradient rounded-2xl p-5 sm:p-6 shadow-xl shadow-black/10">
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <span class="text-[11px] font-bold tracking-[0.18em] text-violet-700 uppercase">Up Next</span>
                        <span class="text-xs font-semibold text-violet-600 bg-violet-100 rounded-full px-2.5 py-1">
                            {{ $nextSession->session_date->format('D, j M') }}
                        </span>
                    </div>
                    <h3 class="teacher-display text-lg font-bold text-slate-900 leading-snug">
                        {{ $nextSession->session_time->format('g:i A') }}
                    </h3>
                    <p class="text-sm text-slate-600 mt-0.5">
                        {{ $nextSession->formatted_duration }} · {{ $class->activeStudents->count() }} {{ Str::plural('student', $class->activeStudents->count()) }}
                    </p>

                    <div class="mt-4">
                        <button type="button" wire:click="markSessionAsOngoing({{ $nextSession->id }})"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-violet-700 via-violet-600 to-violet-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-violet-500/30 hover:shadow-xl hover:-translate-y-px transition-all">
                            <flux:icon name="play" class="w-4 h-4" />
                            Start Session
                        </button>
                    </div>
                </div>
            @else
                <div class="teacher-pill-gradient rounded-2xl p-6 shadow-xl shadow-black/10 text-center">
                    <flux:icon name="sparkles" class="w-8 h-8 text-violet-500 mx-auto mb-2" />
                    <h3 class="teacher-display font-bold text-slate-900">No upcoming session</h3>
                    <p class="text-sm text-slate-600 mt-1">Create one to get started.</p>
                    <button type="button" wire:click="openCreateSessionModal"
                        class="mt-3 inline-flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-violet-700 to-violet-500 px-4 py-2 text-sm font-semibold text-white shadow-md shadow-violet-500/25 hover:shadow-lg transition">
                        <flux:icon name="calendar" class="w-4 h-4" />
                        New Session
                    </button>
                </div>
            @endif
        </div>
    </div>

    {{-- ──────────────────────────────────────────────────────────
         STAT STRIP  -  4 colorful stat cards
         ────────────────────────────────────────────────────────── --}}
    <div class="grid gap-4 grid-cols-2 lg:grid-cols-4">
        <x-teacher.stat-card eyebrow="Total Sessions" :value="$totalSessions" tone="indigo" icon="calendar-days">
            <span class="font-semibold text-violet-700/80 dark:text-violet-300/80">{{ $upcomingSessions }} upcoming</span>
            <span class="text-slate-400 dark:text-zinc-500">· {{ $completedSessions }} done</span>
        </x-teacher.stat-card>

        <x-teacher.stat-card eyebrow="Completed" :value="$completedSessions" tone="emerald" icon="check-circle">
            <span class="font-semibold text-emerald-700/80 dark:text-emerald-300/80">
                {{ $totalSessions > 0 ? round(($completedSessions / $totalSessions) * 100) : 0 }}% of total
            </span>
        </x-teacher.stat-card>

        <x-teacher.stat-card eyebrow="Students" :value="$class->activeStudents->count()" tone="violet" icon="users">
            <span class="font-semibold text-violet-700/80 dark:text-violet-300/80">
                @if($class->max_capacity)
                    of {{ $class->max_capacity }} max
                @else
                    Active enrollments
                @endif
            </span>
        </x-teacher.stat-card>

        <div class="teacher-card teacher-card-hover teacher-stat teacher-stat-amber teacher-stat-hover p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-amber-700/80 dark:text-amber-300/90">Attendance</span>
                <div class="rounded-lg bg-amber-500/10 dark:bg-amber-400/15 p-1.5">
                    <flux:icon name="chart-pie" class="w-4 h-4 text-amber-600 dark:text-amber-300" />
                </div>
            </div>
            <div class="teacher-display teacher-num text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white">{{ $attendanceRate }}%</div>
            <div class="mt-2 h-1.5 w-full rounded-full bg-amber-200/50 dark:bg-amber-900/40 overflow-hidden">
                <div class="h-full rounded-full bg-gradient-to-r from-amber-400 to-orange-500" style="width: {{ min(100, $attendanceRate) }}%"></div>
            </div>
        </div>
    </div>

    {{-- ──────────────────────────────────────────────────────────
         MAIN GRID  -  Class info (2 cols) + Right rail
         ────────────────────────────────────────────────────────── --}}
    <div class="grid gap-6 lg:grid-cols-3">
        {{-- LEFT: Class details --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Class Information --}}
            <div class="teacher-card p-5 sm:p-6">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h2 class="teacher-display text-xl font-bold text-slate-900 dark:text-white">Class Information</h2>
                        <p class="text-sm text-slate-500 dark:text-zinc-400 mt-0.5">Course details and configuration</p>
                    </div>
                    <div class="rounded-xl bg-violet-500/10 dark:bg-violet-400/15 p-2">
                        <flux:icon name="book-open" class="w-5 h-5 text-violet-600 dark:text-violet-300" />
                    </div>
                </div>

                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="rounded-xl bg-slate-50 dark:bg-zinc-800/50 px-4 py-3">
                        <dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">Course</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $class->course->name }}</dd>
                    </div>

                    <div class="rounded-xl bg-slate-50 dark:bg-zinc-800/50 px-4 py-3">
                        <dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">Duration</dt>
                        <dd class="mt-1 teacher-num text-sm font-semibold text-slate-900 dark:text-white">{{ $class->formatted_duration }}</dd>
                    </div>

                    <div class="rounded-xl bg-slate-50 dark:bg-zinc-800/50 px-4 py-3">
                        <dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">Type</dt>
                        <dd class="mt-1 flex items-center gap-2">
                            @if($class->isIndividual())
                                <flux:icon name="user" class="w-4 h-4 text-violet-500" />
                                <span class="text-sm font-semibold text-slate-900 dark:text-white">Individual</span>
                            @else
                                <flux:icon name="users" class="w-4 h-4 text-emerald-500" />
                                <span class="text-sm font-semibold text-slate-900 dark:text-white">Group</span>
                                @if($class->max_capacity)
                                    <span class="text-xs text-slate-500 dark:text-zinc-400">(Max {{ $class->max_capacity }})</span>
                                @endif
                            @endif
                        </dd>
                    </div>

                    <div class="rounded-xl bg-slate-50 dark:bg-zinc-800/50 px-4 py-3">
                        <dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">Status</dt>
                        <dd class="mt-1">
                            @if($statusKey === 'active')
                                <x-teacher.status-pill status="active" />
                            @elseif($statusKey === 'completed')
                                <x-teacher.status-pill status="completed" />
                            @elseif($statusKey === 'suspended')
                                <x-teacher.status-pill status="no_show" label="Suspended" />
                            @elseif($statusKey === 'draft')
                                <x-teacher.status-pill status="inactive" label="Draft" />
                            @else
                                <x-teacher.status-pill :status="$statusKey" />
                            @endif
                        </dd>
                    </div>

                    @if($class->location)
                        <div class="rounded-xl bg-slate-50 dark:bg-zinc-800/50 px-4 py-3">
                            <dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">Location</dt>
                            <dd class="mt-1 flex items-center gap-1.5">
                                <flux:icon name="map-pin" class="w-4 h-4 text-violet-500" />
                                <span class="text-sm font-semibold text-slate-900 dark:text-white">{{ $class->location }}</span>
                            </dd>
                        </div>
                    @endif

                    @if($class->meeting_url)
                        <div class="rounded-xl bg-slate-50 dark:bg-zinc-800/50 px-4 py-3">
                            <dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">Meeting</dt>
                            <dd class="mt-1">
                                <a href="{{ $class->meeting_url }}" target="_blank"
                                    class="inline-flex items-center gap-1 text-sm font-semibold text-violet-600 dark:text-violet-400 hover:text-violet-700 dark:hover:text-violet-300 transition">
                                    <flux:icon name="arrow-right" class="w-3.5 h-3.5" />
                                    Join meeting
                                </a>
                            </dd>
                        </div>
                    @endif

                    @if($class->description)
                        <div class="sm:col-span-2 rounded-xl bg-slate-50 dark:bg-zinc-800/50 px-4 py-3">
                            <dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">Description</dt>
                            <dd class="mt-1 text-sm text-slate-700 dark:text-zinc-200 leading-relaxed">{{ $class->description }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            {{-- Recent Sessions Timeline --}}
            <div class="teacher-card p-5 sm:p-6">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h2 class="teacher-display text-xl font-bold text-slate-900 dark:text-white">Recent Sessions</h2>
                        <p class="text-sm text-slate-500 dark:text-zinc-400 mt-0.5">Latest 5 sessions for this class</p>
                    </div>
                    <button type="button" wire:click="setActiveTab('sessions')"
                        class="inline-flex items-center gap-1 text-sm font-semibold text-violet-600 dark:text-violet-400 hover:text-violet-700 dark:hover:text-violet-300 transition">
                        View all
                        <flux:icon name="arrow-right" class="w-4 h-4" />
                    </button>
                </div>

                @if($totalSessions > 0)
                    @php
                        $recentSessions = $class->sessions->sortByDesc(function($s) {
                            return $s->session_date->format('Y-m-d') . ' ' . $s->session_time->format('H:i:s');
                        })->take(5);
                    @endphp

                    <div class="space-y-3">
                        @foreach($recentSessions as $session)
                            @php
                                $sStatus = $session->status;
                                $borderClass = match($sStatus) {
                                    'ongoing'   => 'border-l-emerald-500',
                                    'completed' => 'border-l-slate-300 dark:border-l-zinc-600',
                                    'cancelled' => 'border-l-rose-500',
                                    'no_show'   => 'border-l-amber-500',
                                    default     => 'border-l-violet-500',
                                };
                                $cardTone = match($sStatus) {
                                    'ongoing'   => 'bg-emerald-50/70 dark:bg-emerald-950/20 ring-emerald-200/60 dark:ring-emerald-800/40',
                                    'completed' => 'bg-slate-50 dark:bg-zinc-900/40 ring-slate-200/60 dark:ring-zinc-800',
                                    'cancelled' => 'bg-rose-50/60 dark:bg-rose-950/20 ring-rose-200/60 dark:ring-rose-900/40',
                                    'no_show'   => 'bg-amber-50/60 dark:bg-amber-950/20 ring-amber-200/60 dark:ring-amber-900/40',
                                    default     => 'bg-white dark:bg-zinc-900/40 ring-slate-200/70 dark:ring-zinc-800',
                                };
                                $sPresent = $session->attendances->where('status', 'present')->count();
                                $sTotal = $session->attendances->count();
                                $sRate = $sTotal > 0 ? round(($sPresent / $sTotal) * 100) : 0;
                            @endphp
                            <div wire:key="overview-session-{{ $session->id }}"
                                class="group relative flex flex-col sm:flex-row sm:items-center gap-3 rounded-xl border-l-4 {{ $borderClass }} {{ $cardTone }} ring-1 px-4 py-3.5 hover:shadow-md hover:-translate-y-px transition-all">

                                <div class="flex sm:flex-col sm:w-[80px] sm:items-start gap-2 sm:gap-0 shrink-0">
                                    <div class="teacher-display teacher-num text-base font-bold text-slate-900 dark:text-white">
                                        {{ $session->session_time->format('g:i A') }}
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-zinc-400">
                                        {{ $session->session_date->format('j M') }}
                                    </div>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        @if($session->isOngoing())
                                            <div
                                                x-data="sessionTimer('{{ $session->started_at ? $session->started_at->toISOString() : now()->toISOString() }}')"
                                                x-init="startTimer()"
                                                class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 px-2.5 py-0.5 text-[11px] font-semibold">
                                                <span class="teacher-live-dot"></span>
                                                <span>Live</span>
                                                <span class="teacher-num font-mono font-bold" x-text="formattedTime"></span>
                                            </div>
                                        @else
                                            <x-teacher.status-pill :status="$sStatus" />
                                        @endif
                                        <span class="teacher-num text-xs text-slate-500 dark:text-zinc-400">
                                            {{ $session->formatted_duration }}
                                        </span>
                                    </div>

                                    <div class="mt-1.5 flex items-center gap-3 text-xs text-slate-500 dark:text-zinc-400">
                                        @if($sTotal > 0)
                                            <span class="inline-flex items-center gap-1">
                                                <flux:icon name="users" class="w-3.5 h-3.5" />
                                                <span class="teacher-num font-semibold {{ $sRate >= 80 ? 'text-emerald-600 dark:text-emerald-400' : ($sRate >= 60 ? 'text-amber-600 dark:text-amber-400' : 'text-rose-600 dark:text-rose-400') }}">{{ $sPresent }}</span>
                                                <span class="teacher-num">/ {{ $sTotal }} present</span>
                                            </span>
                                        @endif
                                        @if($session->hasBookmark())
                                            <span class="inline-flex items-center gap-1 text-amber-600 dark:text-amber-400 truncate max-w-[180px]" title="{{ $session->bookmark }}">
                                                <flux:icon name="document-text" class="w-3.5 h-3.5" />
                                                <span class="truncate">{{ $session->formatted_bookmark }}</span>
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 sm:justify-end shrink-0">
                                    @if($session->isScheduled())
                                        <button type="button" wire:click="markSessionAsOngoing({{ $session->id }})"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-r from-violet-700 to-violet-600 px-3 py-1.5 text-xs font-semibold text-white shadow-md shadow-violet-500/25 hover:shadow-lg transition">
                                            <flux:icon name="play" class="w-3.5 h-3.5" />
                                            Start
                                        </button>
                                    @elseif($session->isOngoing())
                                        <button type="button" wire:click="openSessionModal({{ $session->id }})"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-r from-emerald-500 to-teal-600 px-3 py-1.5 text-xs font-semibold text-white shadow-md shadow-emerald-500/25 hover:shadow-lg transition">
                                            <flux:icon name="users" class="w-3.5 h-3.5" />
                                            Manage
                                        </button>
                                    @elseif($session->isCompleted())
                                        <button type="button" wire:click="openAttendanceViewModal({{ $session->id }})"
                                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold text-slate-600 dark:text-zinc-300 ring-1 ring-slate-200 dark:ring-zinc-700 hover:bg-slate-50 dark:hover:bg-zinc-800 transition">
                                            <flux:icon name="eye" class="w-3.5 h-3.5" />
                                            View
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <x-teacher.empty-state icon="calendar-days" title="No sessions yet" message="Create the first session to get started.">
                        <button type="button" wire:click="openCreateSessionModal" class="teacher-cta">
                            <flux:icon name="calendar" class="w-4 h-4" />
                            Create Session
                        </button>
                    </x-teacher.empty-state>
                @endif
            </div>
        </div>

        {{-- RIGHT RAIL --}}
        <div class="space-y-6">
            {{-- Attendance Breakdown --}}
            <div class="teacher-card p-5 sm:p-6 relative overflow-hidden">
                <div class="absolute -top-12 -right-12 w-44 h-44 rounded-full bg-gradient-to-br from-amber-400/30 to-orange-500/20 blur-2xl pointer-events-none"></div>
                <div class="relative">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="teacher-display text-base font-bold text-slate-900 dark:text-white">Attendance</h3>
                        <flux:icon name="chart-pie" class="w-4 h-4 text-slate-400 dark:text-zinc-500" />
                    </div>

                    <div class="text-xs font-semibold uppercase tracking-wider text-amber-700/80 dark:text-amber-300/90 mb-1">Overall Rate</div>
                    <div class="teacher-display teacher-num text-4xl font-bold bg-gradient-to-r from-amber-600 to-orange-600 dark:from-amber-400 dark:to-orange-300 bg-clip-text text-transparent">
                        {{ $attendanceRate }}%
                    </div>
                    <div class="mt-3 h-2 w-full rounded-full bg-amber-100 dark:bg-amber-900/40 overflow-hidden">
                        <div class="h-full rounded-full bg-gradient-to-r from-amber-400 to-orange-500" style="width: {{ min(100, $attendanceRate) }}%"></div>
                    </div>

                    @if($totalRecords > 0)
                        <div class="mt-5 grid grid-cols-3 gap-2">
                            <div class="rounded-xl bg-emerald-50 dark:bg-emerald-950/30 ring-1 ring-emerald-100 dark:ring-emerald-900/40 px-3 py-2.5 text-center">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">Present</div>
                                <div class="teacher-num text-lg font-bold text-emerald-700 dark:text-emerald-300 mt-0.5">{{ $presentCount }}</div>
                            </div>
                            <div class="rounded-xl bg-amber-50 dark:bg-amber-950/30 ring-1 ring-amber-100 dark:ring-amber-900/40 px-3 py-2.5 text-center">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-300">Late</div>
                                <div class="teacher-num text-lg font-bold text-amber-700 dark:text-amber-300 mt-0.5">{{ $lateCount }}</div>
                            </div>
                            <div class="rounded-xl bg-rose-50 dark:bg-rose-950/30 ring-1 ring-rose-100 dark:ring-rose-900/40 px-3 py-2.5 text-center">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-rose-700 dark:text-rose-300">Absent</div>
                                <div class="teacher-num text-lg font-bold text-rose-700 dark:text-rose-300 mt-0.5">{{ $absentCount }}</div>
                            </div>
                        </div>

                        <div class="mt-4 pt-4 border-t border-slate-100 dark:border-zinc-800">
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-slate-500 dark:text-zinc-400">Total records</span>
                                <span class="teacher-num font-bold text-slate-900 dark:text-white">{{ $totalRecords }}</span>
                            </div>
                        </div>
                    @else
                        <p class="mt-4 text-sm text-slate-500 dark:text-zinc-400">No attendance data yet.</p>
                    @endif
                </div>
            </div>

            {{-- Session Counters --}}
            <div class="teacher-card p-5 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="teacher-display text-base font-bold text-slate-900 dark:text-white">Session Snapshot</h3>
                    <flux:icon name="calendar-days" class="w-4 h-4 text-slate-400 dark:text-zinc-500" />
                </div>

                <div class="space-y-2.5">
                    <div class="flex items-center justify-between rounded-xl px-3 py-2.5 bg-slate-50 dark:bg-zinc-800/50">
                        <span class="text-sm text-slate-600 dark:text-zinc-300">Total sessions</span>
                        <span class="teacher-num font-bold text-slate-900 dark:text-white">{{ $totalSessions }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded-xl px-3 py-2.5 bg-emerald-50/60 dark:bg-emerald-950/20 ring-1 ring-emerald-100/70 dark:ring-emerald-900/30">
                        <span class="text-sm text-emerald-700 dark:text-emerald-300 inline-flex items-center gap-1.5">
                            <flux:icon name="check-circle" class="w-3.5 h-3.5" />
                            Completed
                        </span>
                        <span class="teacher-num font-bold text-emerald-700 dark:text-emerald-300">{{ $completedSessions }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded-xl px-3 py-2.5 bg-violet-50/60 dark:bg-violet-950/20 ring-1 ring-violet-100/70 dark:ring-violet-900/30">
                        <span class="text-sm text-violet-700 dark:text-violet-300 inline-flex items-center gap-1.5">
                            <flux:icon name="calendar" class="w-3.5 h-3.5" />
                            Upcoming
                        </span>
                        <span class="teacher-num font-bold text-violet-700 dark:text-violet-300">{{ $upcomingSessions }}</span>
                    </div>
                </div>
            </div>

            {{-- Enrolled Students Preview --}}
            <div class="teacher-card p-5 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="teacher-display text-base font-bold text-slate-900 dark:text-white">Students</h3>
                        <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">
                            {{ $class->activeStudents->count() }} enrolled
                            @if($class->max_capacity)
                                / {{ $class->max_capacity }} max
                            @endif
                        </p>
                    </div>
                    <button type="button" wire:click="setActiveTab('students')"
                        class="text-xs font-semibold text-violet-600 dark:text-violet-400 hover:text-violet-700 dark:hover:text-violet-300 transition inline-flex items-center gap-1">
                        See all
                        <flux:icon name="arrow-right" class="w-3.5 h-3.5" />
                    </button>
                </div>

                @if($class->activeStudents->count() > 0)
                    <div class="space-y-2">
                        @foreach($class->activeStudents->take(5) as $classStudent)
                            @php
                                $student = $classStudent->student;
                                $studentAttendances = collect();
                                foreach($class->sessions as $session) {
                                    $attendance = $session->attendances->where('student_id', $student->id)->first();
                                    if($attendance) {
                                        $studentAttendances->push($attendance);
                                    }
                                }
                                $sPresent = $studentAttendances->where('status', 'present')->count();
                                $sTotal = $studentAttendances->count();
                                $sRate = $sTotal > 0 ? round(($sPresent / $sTotal) * 100) : 0;
                                $initials = collect(explode(' ', trim($student->fullName)))
                                    ->take(2)
                                    ->map(fn ($p) => strtoupper(substr($p, 0, 1)))
                                    ->join('');
                                $avatarVariant = ($loop->index % 6) + 1;
                            @endphp
                            <div wire:key="overview-student-{{ $student->id }}"
                                class="flex items-center gap-3 rounded-xl px-3 py-2 ring-1 ring-slate-200/70 dark:ring-zinc-800 bg-slate-50/60 dark:bg-zinc-800/40 hover:ring-violet-300 dark:hover:ring-violet-700/60 transition">
                                <div class="teacher-avatar teacher-avatar-{{ $avatarVariant }}">{{ $initials }}</div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">{{ $student->fullName }}</p>
                                    <p class="text-[11px] text-slate-500 dark:text-zinc-400 truncate">{{ $student->student_id }}</p>
                                </div>
                                @if($sTotal > 0)
                                    <span class="teacher-num text-xs font-bold {{ $sRate >= 80 ? 'text-emerald-600 dark:text-emerald-400' : ($sRate >= 60 ? 'text-amber-600 dark:text-amber-400' : 'text-rose-600 dark:text-rose-400') }}">
                                        {{ $sRate }}%
                                    </span>
                                @else
                                    <span class="text-[11px] text-slate-400 dark:text-zinc-500">—</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-slate-500 dark:text-zinc-400">No students enrolled yet.</p>
                @endif
            </div>
        </div>
    </div>
</div>
