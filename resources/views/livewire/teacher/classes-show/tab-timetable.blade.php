{{-- ──────────────────────────────────────────────────────────
     TIMETABLE TAB — weekly schedule for this class
     ────────────────────────────────────────────────────────── --}}
<div class="space-y-6">
    {{-- Stat strip --}}
    <div class="grid gap-4 grid-cols-2 lg:grid-cols-4">
        {{-- Total sessions --}}
        <div class="teacher-card teacher-card-hover teacher-stat teacher-stat-violet teacher-stat-hover p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-violet-700/80 dark:text-violet-300/90">Total Sessions</span>
                <div class="rounded-lg bg-violet-500/10 dark:bg-violet-400/15 p-1.5">
                    <flux:icon name="calendar" class="w-4 h-4 text-violet-600 dark:text-violet-300" />
                </div>
            </div>
            <div class="teacher-display teacher-num text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white">{{ $this->class_sessions_count }}</div>
            <div class="mt-1 text-xs text-violet-700/80 dark:text-violet-300/80 font-medium">All time</div>
        </div>

        {{-- Upcoming --}}
        <div class="teacher-card teacher-card-hover teacher-stat teacher-stat-emerald teacher-stat-hover p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700/80 dark:text-emerald-300/90">Upcoming</span>
                <div class="rounded-lg bg-emerald-500/10 dark:bg-emerald-400/15 p-1.5">
                    <flux:icon name="clock" class="w-4 h-4 text-emerald-600 dark:text-emerald-300" />
                </div>
            </div>
            <div class="teacher-display teacher-num text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white">{{ $this->upcoming_sessions_count }}</div>
            <div class="mt-1 text-xs text-emerald-700/80 dark:text-emerald-300/80 font-medium">Next 7 days</div>
        </div>

        {{-- Completed --}}
        <div class="teacher-card teacher-card-hover teacher-stat teacher-stat-indigo teacher-stat-hover p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-violet-700/80 dark:text-violet-300/90">Completed</span>
                <div class="rounded-lg bg-violet-500/10 dark:bg-violet-400/15 p-1.5">
                    <flux:icon name="check" class="w-4 h-4 text-violet-600 dark:text-violet-300" />
                </div>
            </div>
            <div class="teacher-display teacher-num text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white">{{ $this->completed_sessions_count }}</div>
            <div class="mt-1 text-xs text-violet-700/80 dark:text-violet-300/80 font-medium">Finished</div>
        </div>

        {{-- Weekly hours --}}
        <div class="teacher-card teacher-card-hover teacher-stat teacher-stat-amber teacher-stat-hover p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold uppercase tracking-wider text-amber-700/80 dark:text-amber-300/90">Weekly Hours</span>
                <div class="rounded-lg bg-amber-500/10 dark:bg-amber-400/15 p-1.5">
                    <flux:icon name="bolt" class="w-4 h-4 text-amber-600 dark:text-amber-300" />
                </div>
            </div>
            <div class="teacher-display teacher-num text-3xl sm:text-4xl font-bold text-slate-900 dark:text-white">{{ round($this->weekly_hours, 1) }}</div>
            <div class="mt-1 text-xs text-amber-700/80 dark:text-amber-300/80 font-medium">Average</div>
        </div>
    </div>

    {{-- Weekly Timetable --}}
    <div class="teacher-card p-5 sm:p-6">
        {{-- Header --}}
        <div class="mb-5 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
            <div>
                <span class="text-xs font-semibold uppercase tracking-[0.18em] text-violet-700 dark:text-violet-300">Weekly Timetable</span>
                <h2 class="teacher-display text-xl font-bold text-slate-900 dark:text-white mt-1">Regular Schedule</h2>
                <p class="text-sm text-slate-500 dark:text-zinc-400 mt-0.5">Recurring class schedule with the ability to start sessions</p>
            </div>
            <button
                type="button"
                wire:click="openCreateSessionModal"
                class="teacher-cta self-start sm:self-auto"
            >
                <flux:icon name="plus" class="w-4 h-4" />
                Add Session
            </button>
        </div>

        {{-- Week Navigation --}}
        <div class="mb-5 flex flex-wrap items-center gap-2 rounded-xl bg-slate-50 dark:bg-zinc-800/40 ring-1 ring-slate-200/70 dark:ring-zinc-800 p-2">
            <button
                type="button"
                wire:click="previousWeek"
                title="Previous Week"
                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold text-slate-700 dark:text-zinc-200 ring-1 ring-slate-200 dark:ring-zinc-700 bg-white dark:bg-zinc-900 hover:bg-violet-50 dark:hover:bg-violet-950/40 hover:text-violet-700 dark:hover:text-violet-300 transition"
            >
                <flux:icon name="arrow-right" class="w-3.5 h-3.5 rotate-180" />
                Previous
            </button>

            <div class="flex-1 min-w-[180px] rounded-lg bg-white dark:bg-zinc-900 ring-1 ring-slate-200 dark:ring-zinc-700 px-3 py-1.5 text-center">
                <div class="text-[10px] font-bold uppercase tracking-[0.18em] text-violet-700 dark:text-violet-300">Current Week</div>
                <div class="teacher-display text-sm font-semibold text-slate-900 dark:text-white mt-0.5">{{ $this->current_week_label }}</div>
            </div>

            <button
                type="button"
                wire:click="nextWeek"
                title="Next Week"
                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold text-slate-700 dark:text-zinc-200 ring-1 ring-slate-200 dark:ring-zinc-700 bg-white dark:bg-zinc-900 hover:bg-violet-50 dark:hover:bg-violet-950/40 hover:text-violet-700 dark:hover:text-violet-300 transition"
            >
                Next
                <flux:icon name="arrow-right" class="w-3.5 h-3.5" />
            </button>

            <button
                type="button"
                wire:click="goToCurrentWeek"
                title="Go to current week"
                class="inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-r from-violet-700 to-violet-600 px-3 py-2 text-xs font-semibold text-white shadow-md shadow-violet-500/25 hover:shadow-lg hover:from-violet-600 hover:to-violet-400 transition"
            >
                <flux:icon name="calendar-days" class="w-3.5 h-3.5" />
                This Week
            </button>
        </div>

        @if($this->class->timetable && $this->class->timetable->weekly_schedule)
            @php
                $timeSlots = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00'];
                $dayKeys = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            @endphp

            {{-- Mobile / stacked: per-day mini cards --}}
            <div class="grid gap-3 lg:hidden">
                @foreach($dayKeys as $dayKey)
                    @php
                        $dayData = $this->weekly_calendar_data[$dayKey] ?? null;
                        $dayLabel = ucfirst($dayKey);
                        $scheduledTimes = $dayData['scheduled_times'] ?? [];
                    @endphp
                    <div wire:key="mini-day-{{ $dayKey }}" class="teacher-card p-4 {{ ($dayData['is_today'] ?? false) ? 'ring-2 ring-violet-400 dark:ring-violet-500/60' : '' }}">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-violet-700 dark:text-violet-300">{{ $dayLabel }}</div>
                                @if($dayData)
                                    <div class="teacher-display teacher-num text-base font-bold {{ $dayData['is_today'] ? 'text-violet-700 dark:text-violet-300' : 'text-slate-900 dark:text-white' }} mt-0.5">
                                        {{ $dayData['day_number'] }}
                                    </div>
                                @endif
                            </div>
                            @if($dayData && $dayData['is_today'])
                                <span class="inline-flex items-center gap-1 rounded-full bg-violet-100 dark:bg-violet-500/15 text-violet-700 dark:text-violet-300 px-2 py-0.5 text-[11px] font-semibold">
                                    <span class="teacher-live-dot"></span>
                                    Today
                                </span>
                            @endif
                        </div>

                        @if(empty($scheduledTimes))
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-zinc-400 px-3 py-1 text-xs font-medium">
                                No sessions
                            </span>
                        @else
                            <div class="flex flex-wrap gap-2">
                                @foreach($scheduledTimes as $timeSlot)
                                    @php
                                        $session = null;
                                        if ($dayData) {
                                            $session = $dayData['sessions']->first(function($s) use ($timeSlot) {
                                                return $s->session_time->format('H:i') === $timeSlot;
                                            });
                                        }
                                        $tone = 'bg-violet-100 dark:bg-violet-500/15 text-violet-700 dark:text-violet-300';
                                        if ($session) {
                                            $tone = match($session->status) {
                                                'completed' => 'bg-slate-100 dark:bg-zinc-700/40 text-slate-600 dark:text-zinc-300',
                                                'ongoing'   => 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
                                                'cancelled' => 'bg-rose-100 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300',
                                                'no_show'   => 'bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300',
                                                default     => 'bg-violet-100 dark:bg-violet-500/15 text-violet-700 dark:text-violet-300',
                                            };
                                        }
                                    @endphp
                                    @if($session)
                                        <button
                                            type="button"
                                            wire:click="selectSession({{ $session->id }})"
                                            class="inline-flex items-center gap-1 rounded-full {{ $tone }} px-3 py-1 text-xs font-semibold ring-1 ring-inset ring-current/10 hover:ring-current/30 transition"
                                        >
                                            <flux:icon name="clock" class="w-3 h-3" />
                                            {{ \Carbon\Carbon::parse($timeSlot)->format('g:i A') }}
                                        </button>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full {{ $tone }} px-3 py-1 text-xs font-semibold">
                                            <flux:icon name="clock" class="w-3 h-3" />
                                            {{ \Carbon\Carbon::parse($timeSlot)->format('g:i A') }}
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Desktop: 8-column calendar grid --}}
            <div class="hidden lg:block rounded-2xl ring-1 ring-slate-200/70 dark:ring-zinc-800 overflow-hidden">
                {{-- Header row --}}
                <div class="grid grid-cols-8 bg-slate-50 dark:bg-zinc-900/60 border-b border-slate-200 dark:border-zinc-800">
                    <div class="p-3 text-[11px] font-bold uppercase tracking-[0.18em] text-violet-700 dark:text-violet-300 border-r border-slate-200 dark:border-zinc-800">
                        Time
                    </div>
                    @foreach($dayKeys as $dayKey)
                        @php
                            $dayData = $this->weekly_calendar_data[$dayKey] ?? null;
                            $isToday = $dayData['is_today'] ?? false;
                        @endphp
                        <div class="p-3 text-center border-r border-slate-200 dark:border-zinc-800 last:border-r-0 {{ $isToday ? 'bg-violet-50 dark:bg-violet-950/30' : '' }}">
                            <div class="text-[11px] font-bold uppercase tracking-[0.18em] {{ $isToday ? 'text-violet-700 dark:text-violet-300' : 'text-slate-600 dark:text-zinc-400' }}">
                                {{ ucfirst(substr($dayKey, 0, 3)) }}
                            </div>
                            @if($dayData)
                                <div class="teacher-display teacher-num mt-0.5 text-sm font-bold {{ $isToday ? 'text-violet-700 dark:text-violet-300' : 'text-slate-900 dark:text-white' }}">
                                    {{ $dayData['day_number'] }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Time slot rows --}}
                @foreach($timeSlots as $timeSlot)
                    <div class="grid grid-cols-8 border-b border-slate-100 dark:border-zinc-800/70 last:border-b-0">
                        {{-- Time label --}}
                        <div class="p-3 text-xs font-semibold text-slate-600 dark:text-zinc-400 border-r border-slate-200 dark:border-zinc-800 bg-slate-50/60 dark:bg-zinc-900/40">
                            {{ \Carbon\Carbon::parse($timeSlot)->format('g:i A') }}
                        </div>

                        {{-- Day cells --}}
                        @foreach($dayKeys as $dayKey)
                            @php
                                $dayData = $this->weekly_calendar_data[$dayKey] ?? null;
                                $isScheduled = $dayData && in_array($timeSlot, $dayData['scheduled_times']);
                                $isToday = $dayData['is_today'] ?? false;
                                $session = null;
                                if ($dayData) {
                                    $session = $dayData['sessions']->first(function($s) use ($timeSlot) {
                                        return $s->session_time->format('H:i') === $timeSlot;
                                    });
                                }
                            @endphp

                            <div class="p-2 border-r border-slate-200 dark:border-zinc-800 last:border-r-0 min-h-[64px] relative {{ $isToday ? 'bg-violet-50/40 dark:bg-violet-950/15' : '' }}">
                                @if($isScheduled)
                                    @if($session)
                                        @php
                                            $sessionTone = match($session->status) {
                                                'completed' => 'bg-slate-50 dark:bg-zinc-800/60 ring-slate-200 dark:ring-zinc-700 text-slate-700 dark:text-zinc-200',
                                                'ongoing'   => 'bg-emerald-50 dark:bg-emerald-950/30 ring-emerald-200 dark:ring-emerald-800/50 text-emerald-800 dark:text-emerald-200',
                                                'cancelled' => 'bg-rose-50 dark:bg-rose-950/30 ring-rose-200 dark:ring-rose-800/50 text-rose-800 dark:text-rose-200',
                                                'no_show'   => 'bg-amber-50 dark:bg-amber-950/30 ring-amber-200 dark:ring-amber-800/50 text-amber-800 dark:text-amber-200',
                                                default     => 'bg-violet-50 dark:bg-violet-950/30 ring-violet-200 dark:ring-violet-800/50 text-violet-800 dark:text-violet-200',
                                            };
                                        @endphp
                                        <div
                                            wire:click="selectSession({{ $session->id }})"
                                            class="h-full p-2 rounded-lg ring-1 {{ $sessionTone }} cursor-pointer hover:shadow-md transition"
                                        >
                                            <div class="text-[11px] font-semibold truncate">{{ $class->course->name }}</div>
                                            <div class="mt-1.5">
                                                <x-teacher.status-pill :status="$session->status" size="sm" />
                                            </div>

                                            @if($session->isScheduled())
                                                <button
                                                    type="button"
                                                    wire:click.stop="requestStartSessionFromTimetable('{{ $dayData['date']->toDateString() }}', '{{ $timeSlot }}')"
                                                    class="mt-2 w-full inline-flex items-center justify-center gap-1 rounded-md bg-gradient-to-r from-violet-700 to-violet-600 px-2 py-1 text-[11px] font-semibold text-white shadow-sm shadow-violet-500/25 hover:from-violet-600 hover:to-violet-400 transition"
                                                >
                                                    <flux:icon name="bolt" class="w-3 h-3" />
                                                    Start
                                                </button>
                                            @elseif($session->isOngoing())
                                                <button
                                                    type="button"
                                                    wire:click.stop="openSessionModal({{ $session->id }})"
                                                    class="mt-2 w-full inline-flex items-center justify-center gap-1 rounded-md bg-gradient-to-r from-emerald-500 to-teal-600 px-2 py-1 text-[11px] font-semibold text-white shadow-sm shadow-emerald-500/25 hover:shadow-md transition"
                                                >
                                                    <flux:icon name="sparkles" class="w-3 h-3" />
                                                    Manage
                                                </button>
                                            @elseif($session->isCompleted())
                                                <div class="mt-2 inline-flex items-center gap-1 text-[10px] font-medium text-slate-500 dark:text-zinc-400">
                                                    <flux:icon name="eye" class="w-3 h-3" />
                                                    View details
                                                </div>
                                            @endif
                                        </div>
                                    @else
                                        {{-- Scheduled slot without a session --}}
                                        <div class="h-full p-2 rounded-lg ring-1 bg-violet-50 dark:bg-violet-950/30 ring-violet-200 dark:ring-violet-800/50 text-violet-800 dark:text-violet-200 flex flex-col justify-between">
                                            <div>
                                                <div class="text-[11px] font-semibold truncate">{{ $class->course->name }}</div>
                                                <div class="mt-1">
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-violet-100 dark:bg-violet-500/15 text-violet-700 dark:text-violet-300 px-2 py-0.5 text-[10px] font-semibold">
                                                        <flux:icon name="calendar" class="w-3 h-3" />
                                                        Scheduled
                                                    </span>
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                wire:click="requestStartSessionFromTimetable('{{ $dayData['date']->toDateString() }}', '{{ $timeSlot }}')"
                                                class="mt-2 w-full inline-flex items-center justify-center gap-1 rounded-md bg-gradient-to-r from-violet-700 to-violet-600 px-2 py-1 text-[11px] font-semibold text-white shadow-sm shadow-violet-500/25 hover:from-violet-600 hover:to-violet-400 transition"
                                            >
                                                <flux:icon name="bolt" class="w-3 h-3" />
                                                Start
                                            </button>
                                        </div>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @else
            <x-teacher.empty-state
                icon="calendar-days"
                title="No timetable configured"
                message="Set up a regular schedule for this class to use the weekly timetable view."
            >
                <button type="button" wire:click="openCreateSessionModal" class="teacher-cta">
                    <flux:icon name="plus" class="w-4 h-4" />
                    Add Manual Session
                </button>
            </x-teacher.empty-state>
        @endif
    </div>
</div>
