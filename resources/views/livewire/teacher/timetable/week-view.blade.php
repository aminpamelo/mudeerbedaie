{{-- ──────────────────────────────────────────────────────────
     MOBILE WEEK VIEW  -  horizontal scroll-snap day cards
     Alpine x-data variable names preserved (parent depends on them):
       scrollContainer, currentDayIndex, scrollToDay, updateCurrentDay,
       previousDay, nextDay
     ────────────────────────────────────────────────────────── --}}
<div class="teacher-app md:hidden" x-data="{
    scrollContainer: null,
    currentDayIndex: 0,

    init() {
        this.scrollContainer = this.$refs.scrollContainer;
        // Find today's index
        const todayIndex = {{ collect($days)->search(function($day) { return $day['isToday']; }) ?: 0 }};
        this.currentDayIndex = todayIndex;
        this.$nextTick(() => {
            this.scrollToDay(todayIndex);
        });

        // Listen for scroll events to update current day indicator
        this.scrollContainer.addEventListener('scroll', this.updateCurrentDay.bind(this));
    },

    scrollToDay(dayIndex) {
        if (this.scrollContainer) {
            const dayWidth = this.scrollContainer.scrollWidth / {{ count($days) }};
            const scrollPosition = dayIndex * dayWidth;
            this.scrollContainer.scrollTo({
                left: scrollPosition,
                behavior: 'smooth'
            });
            this.currentDayIndex = dayIndex;
        }
    },

    updateCurrentDay() {
        if (this.scrollContainer) {
            const dayWidth = this.scrollContainer.scrollWidth / {{ count($days) }};
            const scrollLeft = this.scrollContainer.scrollLeft;
            const newDayIndex = Math.round(scrollLeft / dayWidth);
            this.currentDayIndex = Math.max(0, Math.min(newDayIndex, {{ count($days) - 1 }}));
        }
    },

    previousDay() {
        if (this.currentDayIndex > 0) {
            this.scrollToDay(this.currentDayIndex - 1);
        }
    },

    nextDay() {
        if (this.currentDayIndex < {{ count($days) - 1 }}) {
            this.scrollToDay(this.currentDayIndex + 1);
        }
    }
}">
    {{-- Day navigator --}}
    <div class="flex items-center justify-between mb-4 px-1">
        <button
            type="button"
            x-on:click="previousDay()"
            x-bind:disabled="currentDayIndex === 0"
            x-bind:class="currentDayIndex === 0 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-violet-50 dark:hover:bg-violet-500/10'"
            class="inline-flex items-center justify-center w-10 h-10 rounded-xl ring-1 ring-slate-200 dark:ring-zinc-700 text-slate-600 dark:text-zinc-300 transition"
            aria-label="Previous day"
        >
            <flux:icon name="chevron-left" class="w-5 h-5" />
        </button>

        <div class="text-center">
            <div class="teacher-display text-sm font-bold text-slate-900 dark:text-white" x-text="
                (() => {
                    const days = {{ collect($days)->map(function($day) { return $day['dayName'] . ', ' . $day['date']->format('M d'); })->toJson() }};
                    return days[currentDayIndex] || 'Current Day';
                })()">
            </div>
            <div class="text-[11px] font-medium text-slate-500 dark:text-zinc-500 mt-0.5">
                Day <span x-text="currentDayIndex + 1"></span> of {{ count($days) }}
            </div>
        </div>

        <button
            type="button"
            x-on:click="nextDay()"
            x-bind:disabled="currentDayIndex === {{ count($days) - 1 }}"
            x-bind:class="currentDayIndex === {{ count($days) - 1 }} ? 'opacity-40 cursor-not-allowed' : 'hover:bg-violet-50 dark:hover:bg-violet-500/10'"
            class="inline-flex items-center justify-center w-10 h-10 rounded-xl ring-1 ring-slate-200 dark:ring-zinc-700 text-slate-600 dark:text-zinc-300 transition"
            aria-label="Next day"
        >
            <flux:icon name="chevron-right" class="w-5 h-5" />
        </button>
    </div>

    {{-- Day dots indicator --}}
    <div class="flex justify-center items-center gap-1.5 mb-4">
        @foreach($days as $index => $day)
            <button
                type="button"
                class="h-2 rounded-full transition-all duration-200"
                x-bind:class="currentDayIndex === {{ $index }} ? 'bg-gradient-to-r from-violet-600 to-violet-400 w-7' : 'bg-slate-300 dark:bg-zinc-700 w-2 hover:bg-violet-300 dark:hover:bg-violet-700'"
                x-on:click="scrollToDay({{ $index }})"
                aria-label="{{ $day['dayName'] }}, {{ $day['date']->format('M d') }}"
            ></button>
        @endforeach
    </div>

    {{-- Horizontal scroll-snap container --}}
    <div
        x-ref="scrollContainer"
        class="overflow-x-auto scrollbar-hide scroll-smooth"
        style="scroll-snap-type: x mandatory;"
    >
        <div class="flex gap-4 pb-4" style="width: {{ count($days) * 100 }}%;">
            @foreach($days as $day)
                @php
                    $combinedItems = collect();
                    foreach($day['sessions'] as $session) {
                        $combinedItems->push([
                            'type' => 'session',
                            'time' => $session->session_time->format('H:i'),
                            'displayTime' => $session->session_time->format('g:i A'),
                            'session' => $session,
                            'class' => $session->class,
                        ]);
                    }
                    foreach($day['scheduledSlots'] as $slot) {
                        if (!$slot['session']) {
                            $combinedItems->push([
                                'type' => 'scheduled',
                                'time' => $slot['time'],
                                'displayTime' => \Carbon\Carbon::parse($slot['time'])->format('g:i A'),
                                'session' => null,
                                'class' => $slot['class'],
                                'isFirstClass' => $slot['isFirstClass'] ?? false,
                                'isLastClass' => $slot['isLastClass'] ?? false,
                                'startDate' => $slot['startDate'] ?? null,
                                'endDate' => $slot['endDate'] ?? null,
                            ]);
                        }
                    }
                    $sortedItems = $combinedItems->sortBy('time');
                    $totalCount = $sortedItems->count();
                @endphp

                <div
                    class="flex-shrink-0 teacher-card overflow-hidden {{ $day['isToday'] ? 'ring-2 ring-violet-500/70 dark:ring-violet-400/70' : '' }}"
                    style="width: calc(100% / {{ count($days) }} - 0.75rem); scroll-snap-align: start;"
                >
                    {{-- Day header strip - today gets the violet→fuchsia hero strip --}}
                    @if($day['isToday'])
                        <div class="teacher-hero relative px-4 py-4 text-white overflow-hidden">
                            <div class="teacher-grain absolute inset-0 pointer-events-none"></div>
                            <div class="relative text-center">
                                <div class="text-[10px] font-bold uppercase tracking-[0.18em] text-white/80">
                                    {{ $day['dayName'] }}
                                </div>
                                <div class="teacher-display teacher-num text-3xl font-bold mt-0.5 leading-none">
                                    {{ $day['dayNumber'] }}
                                </div>
                                <span class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-white/20 ring-1 ring-white/30 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider backdrop-blur">
                                    <span class="teacher-live-dot"></span>
                                    Today
                                </span>
                            </div>
                        </div>
                    @else
                        <div class="px-4 py-4 border-b border-slate-100 dark:border-zinc-800 bg-gradient-to-b from-slate-50/40 to-transparent dark:from-zinc-800/30">
                            <div class="text-center">
                                <div class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500 dark:text-zinc-500">
                                    {{ $day['dayName'] }}
                                </div>
                                <div class="teacher-display teacher-num text-3xl font-bold mt-0.5 leading-none text-slate-900 dark:text-white">
                                    {{ $day['dayNumber'] }}
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Session count chip --}}
                    <div class="px-4 pt-3 pb-1 text-center">
                        @if($totalCount > 0)
                            <span class="inline-flex items-center gap-1 rounded-full bg-violet-100 dark:bg-violet-500/15 text-violet-700 dark:text-violet-300 px-2.5 py-0.5 text-[11px] font-semibold">
                                <flux:icon name="calendar-days" class="w-3 h-3" />
                                {{ $totalCount }} {{ Str::plural('session', $totalCount) }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-zinc-400 px-2.5 py-0.5 text-[11px] font-semibold">
                                <flux:icon name="sparkles" class="w-3 h-3" />
                                Free day
                            </span>
                        @endif
                    </div>

                    {{-- Sessions / scheduled slots list --}}
                    <div class="p-3 min-h-[300px] space-y-2">
                        @forelse($sortedItems as $item)
                            @if($item['type'] === 'session')
                                @php
                                    $session = $item['session'];
                                    $isOngoing = $session->status === 'ongoing';
                                    $isCompleted = $session->status === 'completed';
                                    $isCancelled = $session->status === 'cancelled';
                                    $borderClass = match($session->status) {
                                        'ongoing'   => 'border-l-emerald-500',
                                        'completed' => 'border-l-slate-300 dark:border-l-zinc-600',
                                        'cancelled' => 'border-l-rose-500',
                                        'no_show'   => 'border-l-amber-500',
                                        default     => 'border-l-violet-500',
                                    };
                                    $cardTone = match($session->status) {
                                        'ongoing'   => 'bg-emerald-50/70 dark:bg-emerald-950/20 ring-emerald-200/60 dark:ring-emerald-800/40',
                                        'completed' => 'bg-slate-50 dark:bg-zinc-900/40 ring-slate-200/60 dark:ring-zinc-800',
                                        'cancelled' => 'bg-rose-50/70 dark:bg-rose-950/20 ring-rose-200/60 dark:ring-rose-800/40',
                                        'no_show'   => 'bg-amber-50/70 dark:bg-amber-950/20 ring-amber-200/60 dark:ring-amber-800/40',
                                        default     => 'bg-white dark:bg-zinc-900/40 ring-slate-200/70 dark:ring-zinc-800',
                                    };
                                @endphp

                                <div
                                    wire:key="m-sess-{{ $session->id }}"
                                    class="group cursor-pointer rounded-xl border-l-4 {{ $borderClass }} {{ $cardTone }} ring-1 p-3 text-sm transition-all hover:shadow-md hover:-translate-y-px"
                                    wire:click="selectSession({{ $session->id }})"
                                >
                                    <div class="flex items-center justify-between mb-1">
                                        <div class="teacher-display teacher-num text-sm font-bold text-slate-900 dark:text-white">
                                            {{ $item['displayTime'] }}
                                        </div>
                                        <x-teacher.status-pill :status="$session->status" size="sm" />
                                    </div>

                                    <div class="font-semibold text-slate-900 dark:text-white truncate" title="{{ $session->class->title }}">
                                        {{ $session->class->title }}
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-zinc-400 truncate mt-0.5" title="{{ $session->class->course->title ?? $session->class->course->name }}">
                                        {{ $session->class->course->title ?? $session->class->course->name }}
                                    </div>

                                    <div class="flex items-center justify-between mt-2 text-[11px] text-slate-500 dark:text-zinc-400">
                                        <span class="inline-flex items-center gap-1">
                                            <flux:icon name="clock" class="w-3 h-3" />
                                            {{ $session->formatted_duration }}
                                        </span>
                                        @if($session->attendances->count() > 0)
                                            <span class="inline-flex items-center gap-1">
                                                <flux:icon name="users" class="w-3 h-3" />
                                                {{ $session->attendances->count() }}
                                            </span>
                                        @endif
                                    </div>

                                    {{-- Ongoing live timer --}}
                                    @if($isOngoing)
                                        <div class="mt-2 inline-flex w-full items-center gap-2 rounded-lg bg-emerald-500/10 ring-1 ring-emerald-500/30 px-2.5 py-1.5"
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
                                            <span class="text-[10px] font-bold uppercase tracking-[0.16em] text-emerald-700 dark:text-emerald-300">Live</span>
                                            <span class="ml-auto teacher-num font-mono text-xs font-bold text-emerald-700 dark:text-emerald-300" x-text="formatTime(elapsedTime)"></span>
                                        </div>
                                    @endif
                                </div>
                            @else
                                @php
                                    $class = $item['class'];
                                @endphp
                                <div
                                    wire:key="m-slot-{{ $class->id }}-{{ $item['time'] }}"
                                    class="rounded-xl border-l-4 border-l-violet-500 bg-violet-50/60 dark:bg-violet-950/25 ring-1 ring-violet-200/60 dark:ring-violet-800/40 p-3 text-sm transition-all hover:shadow-md hover:-translate-y-px"
                                >
                                    <div class="flex items-center justify-between mb-1">
                                        <div class="teacher-display teacher-num text-sm font-bold text-slate-900 dark:text-white">
                                            {{ $item['displayTime'] }}
                                        </div>
                                        <x-teacher.status-pill status="scheduled" size="sm" />
                                    </div>

                                    <div class="font-semibold text-slate-900 dark:text-white truncate" title="{{ $class->title }}">
                                        {{ $class->title }}
                                    </div>
                                    <div class="text-xs text-slate-500 dark:text-zinc-400 truncate mt-0.5" title="{{ $class->course->title ?? $class->course->name }}">
                                        {{ $class->course->title ?? $class->course->name }}
                                    </div>

                                    {{-- First / last class chips --}}
                                    @if($item['isFirstClass'] || $item['isLastClass'])
                                        <div class="flex flex-wrap gap-1 mt-2">
                                            @if($item['isFirstClass'])
                                                <span class="inline-flex items-center gap-1 rounded-full bg-violet-100 dark:bg-violet-500/20 text-violet-700 dark:text-violet-300 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider">
                                                    <flux:icon name="sparkles" class="w-3 h-3" />
                                                    First class
                                                </span>
                                            @endif
                                            @if($item['isLastClass'])
                                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider">
                                                    <flux:icon name="bolt" class="w-3 h-3" />
                                                    Last class
                                                </span>
                                            @endif
                                        </div>
                                    @endif

                                    <button
                                        type="button"
                                        wire:click.stop="requestStartSessionFromTimetable({{ $class->id }}, '{{ $day['date']->toDateString() }}', '{{ $item['time'] }}')"
                                        class="mt-3 inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-gradient-to-r from-violet-700 via-violet-600 to-violet-500 px-3 py-2 text-xs font-semibold text-white shadow-md shadow-violet-500/25 hover:shadow-lg hover:from-violet-600 hover:to-violet-400 transition"
                                    >
                                        <flux:icon name="play" class="w-3.5 h-3.5" />
                                        Start session
                                    </button>
                                </div>
                            @endif
                        @empty
                            <div class="text-center py-10 rounded-xl bg-gradient-to-br from-slate-50 to-violet-50/40 dark:from-zinc-800/40 dark:to-violet-950/20 ring-1 ring-slate-200/60 dark:ring-zinc-800">
                                <div class="inline-flex w-10 h-10 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-violet-400 text-white shadow-md shadow-violet-500/30 mb-2">
                                    <flux:icon name="calendar" class="w-5 h-5" />
                                </div>
                                <div class="text-xs font-semibold text-slate-700 dark:text-zinc-200">No sessions</div>
                                <div class="text-[11px] text-slate-500 dark:text-zinc-400 mt-0.5">Free day</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- ──────────────────────────────────────────────────────────
     DESKTOP WEEK VIEW  -  7-column grid of teacher-card day tiles
     ────────────────────────────────────────────────────────── --}}
<div class="teacher-app hidden md:block overflow-x-auto scrollbar-hide">
    <div class="grid gap-3 pb-2" style="grid-template-columns: repeat(7, minmax(280px, 1fr)); min-width: 1960px;">
        @foreach($days as $day)
            @php
                $combinedItems = collect();
                foreach($day['sessions'] as $session) {
                    $combinedItems->push([
                        'type' => 'session',
                        'time' => $session->session_time->format('H:i'),
                        'displayTime' => $session->session_time->format('g:i A'),
                        'session' => $session,
                        'class' => $session->class,
                    ]);
                }
                foreach($day['scheduledSlots'] as $slot) {
                    if (!$slot['session']) {
                        $combinedItems->push([
                            'type' => 'scheduled',
                            'time' => $slot['time'],
                            'displayTime' => \Carbon\Carbon::parse($slot['time'])->format('g:i A'),
                            'session' => null,
                            'class' => $slot['class'],
                            'isFirstClass' => $slot['isFirstClass'] ?? false,
                            'isLastClass' => $slot['isLastClass'] ?? false,
                            'startDate' => $slot['startDate'] ?? null,
                            'endDate' => $slot['endDate'] ?? null,
                        ]);
                    }
                }
                $sortedItems = $combinedItems->sortBy('time');
                $totalCount = $sortedItems->count();
            @endphp

            <div class="teacher-card teacher-card-hover overflow-hidden flex flex-col {{ $day['isToday'] ? 'ring-2 ring-violet-500/70 dark:ring-violet-400/70' : '' }}">
                {{-- Day header (mini-hero for today) --}}
                @if($day['isToday'])
                    <div class="teacher-hero relative px-4 py-4 text-white overflow-hidden">
                        <div class="teacher-grain absolute inset-0 pointer-events-none"></div>
                        <div class="relative flex items-center justify-between gap-3">
                            <div>
                                <div class="text-[10px] font-bold uppercase tracking-[0.18em] text-white/80">
                                    {{ $day['dayName'] }}
                                </div>
                                <div class="teacher-display teacher-num text-3xl font-bold mt-0.5 leading-none">
                                    {{ $day['dayNumber'] }}
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/20 ring-1 ring-white/30 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider backdrop-blur">
                                <span class="teacher-live-dot"></span>
                                Today
                            </span>
                        </div>
                        <div class="relative mt-3">
                            @if($totalCount > 0)
                                <span class="inline-flex items-center gap-1 rounded-full bg-white/20 ring-1 ring-white/30 px-2 py-0.5 text-[11px] font-semibold backdrop-blur">
                                    <flux:icon name="calendar-days" class="w-3 h-3" />
                                    {{ $totalCount }} {{ Str::plural('session', $totalCount) }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full bg-white/20 ring-1 ring-white/30 px-2 py-0.5 text-[11px] font-semibold backdrop-blur">
                                    <flux:icon name="sparkles" class="w-3 h-3" />
                                    Free day
                                </span>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="px-4 py-4 border-b border-slate-100 dark:border-zinc-800 bg-gradient-to-b from-slate-50/40 to-transparent dark:from-zinc-800/30">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-500 dark:text-zinc-500">
                                    {{ $day['dayName'] }}
                                </div>
                                <div class="teacher-display teacher-num text-3xl font-bold mt-0.5 leading-none text-slate-900 dark:text-white">
                                    {{ $day['dayNumber'] }}
                                </div>
                            </div>
                            @if($totalCount > 0)
                                <span class="inline-flex items-center gap-1 rounded-full bg-violet-100 dark:bg-violet-500/15 text-violet-700 dark:text-violet-300 px-2 py-0.5 text-[11px] font-semibold">
                                    <flux:icon name="calendar-days" class="w-3 h-3" />
                                    {{ $totalCount }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-zinc-400 px-2 py-0.5 text-[11px] font-semibold">
                                    <flux:icon name="sparkles" class="w-3 h-3" />
                                    Free
                                </span>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Sessions / slots list --}}
                <div class="p-2.5 min-h-[260px] space-y-2 flex-1">
                    @forelse($sortedItems as $item)
                        @if($item['type'] === 'session')
                            @php
                                $session = $item['session'];
                                $isOngoing = $session->status === 'ongoing';
                                $isCompleted = $session->status === 'completed';
                                $borderClass = match($session->status) {
                                    'ongoing'   => 'border-l-emerald-500',
                                    'completed' => 'border-l-slate-300 dark:border-l-zinc-600',
                                    'cancelled' => 'border-l-rose-500',
                                    'no_show'   => 'border-l-amber-500',
                                    default     => 'border-l-violet-500',
                                };
                                $cardTone = match($session->status) {
                                    'ongoing'   => 'bg-emerald-50/70 dark:bg-emerald-950/20 ring-emerald-200/60 dark:ring-emerald-800/40',
                                    'completed' => 'bg-slate-50 dark:bg-zinc-900/40 ring-slate-200/60 dark:ring-zinc-800',
                                    'cancelled' => 'bg-rose-50/70 dark:bg-rose-950/20 ring-rose-200/60 dark:ring-rose-800/40',
                                    'no_show'   => 'bg-amber-50/70 dark:bg-amber-950/20 ring-amber-200/60 dark:ring-amber-800/40',
                                    default     => 'bg-white dark:bg-zinc-900/40 ring-slate-200/70 dark:ring-zinc-800',
                                };
                            @endphp

                            <div
                                wire:key="d-sess-{{ $session->id }}"
                                class="group cursor-pointer rounded-xl border-l-4 {{ $borderClass }} {{ $cardTone }} ring-1 p-2.5 text-xs transition-all hover:shadow-md hover:-translate-y-px"
                                wire:click="selectSession({{ $session->id }})"
                            >
                                <div class="flex items-center justify-between mb-1">
                                    <div class="teacher-display teacher-num text-xs font-bold text-slate-900 dark:text-white">
                                        {{ $item['displayTime'] }}
                                    </div>
                                    <x-teacher.status-pill :status="$session->status" size="sm" />
                                </div>

                                <div class="font-semibold text-slate-900 dark:text-white truncate" title="{{ $session->class->title }}">
                                    {{ $session->class->title }}
                                </div>
                                <div class="text-[11px] text-slate-500 dark:text-zinc-400 truncate" title="{{ $session->class->course->title ?? $session->class->course->name }}">
                                    {{ $session->class->course->title ?? $session->class->course->name }}
                                </div>

                                <div class="flex items-center justify-between mt-1.5 text-[11px] text-slate-500 dark:text-zinc-400">
                                    <span class="inline-flex items-center gap-1">
                                        <flux:icon name="clock" class="w-3 h-3" />
                                        {{ $session->formatted_duration }}
                                    </span>
                                    @if($session->attendances->count() > 0)
                                        <span class="inline-flex items-center gap-1">
                                            <flux:icon name="users" class="w-3 h-3" />
                                            {{ $session->attendances->count() }}
                                        </span>
                                    @endif
                                </div>

                                @if($isOngoing)
                                    <div class="mt-2 inline-flex w-full items-center gap-2 rounded-lg bg-emerald-500/10 ring-1 ring-emerald-500/30 px-2 py-1.5"
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
                                        <span class="text-[10px] font-bold uppercase tracking-[0.16em] text-emerald-700 dark:text-emerald-300">Live</span>
                                        <span class="ml-auto teacher-num font-mono text-[11px] font-bold text-emerald-700 dark:text-emerald-300" x-text="formatTime(elapsedTime)"></span>
                                    </div>
                                @endif
                            </div>
                        @else
                            @php
                                $class = $item['class'];
                            @endphp
                            <div
                                wire:key="d-slot-{{ $class->id }}-{{ $item['time'] }}"
                                class="rounded-xl border-l-4 border-l-violet-500 bg-violet-50/60 dark:bg-violet-950/25 ring-1 ring-violet-200/60 dark:ring-violet-800/40 p-2.5 text-xs transition-all hover:shadow-md hover:-translate-y-px"
                            >
                                <div class="flex items-center justify-between mb-1">
                                    <div class="teacher-display teacher-num text-xs font-bold text-slate-900 dark:text-white">
                                        {{ $item['displayTime'] }}
                                    </div>
                                    <x-teacher.status-pill status="scheduled" size="sm" />
                                </div>

                                <div class="font-semibold text-slate-900 dark:text-white truncate" title="{{ $class->title }}">
                                    {{ $class->title }}
                                </div>
                                <div class="text-[11px] text-slate-500 dark:text-zinc-400 truncate" title="{{ $class->course->title ?? $class->course->name }}">
                                    {{ $class->course->title ?? $class->course->name }}
                                </div>

                                @if($item['isFirstClass'] || $item['isLastClass'])
                                    <div class="flex flex-wrap gap-1 mt-1.5">
                                        @if($item['isFirstClass'])
                                            <span class="inline-flex items-center gap-1 rounded-full bg-violet-100 dark:bg-violet-500/20 text-violet-700 dark:text-violet-300 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider">
                                                <flux:icon name="sparkles" class="w-3 h-3" />
                                                First
                                            </span>
                                        @endif
                                        @if($item['isLastClass'])
                                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider">
                                                <flux:icon name="bolt" class="w-3 h-3" />
                                                Last
                                            </span>
                                        @endif
                                    </div>
                                @endif

                                <button
                                    type="button"
                                    wire:click.stop="requestStartSessionFromTimetable({{ $class->id }}, '{{ $day['date']->toDateString() }}', '{{ $item['time'] }}')"
                                    class="mt-2 inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-gradient-to-r from-violet-700 via-violet-600 to-violet-500 px-2.5 py-1.5 text-[11px] font-semibold text-white shadow-md shadow-violet-500/25 hover:shadow-lg hover:from-violet-600 hover:to-violet-400 transition"
                                >
                                    <flux:icon name="play" class="w-3 h-3" />
                                    Start session
                                </button>
                            </div>
                        @endif
                    @empty
                        <div class="text-center py-10 rounded-xl bg-gradient-to-br from-slate-50 to-violet-50/40 dark:from-zinc-800/40 dark:to-violet-950/20 ring-1 ring-slate-200/60 dark:ring-zinc-800">
                            <div class="inline-flex w-10 h-10 items-center justify-center rounded-2xl bg-gradient-to-br from-violet-500 to-violet-400 text-white shadow-md shadow-violet-500/30 mb-2">
                                <flux:icon name="calendar" class="w-5 h-5" />
                            </div>
                            <div class="text-xs font-semibold text-slate-700 dark:text-zinc-200">Free day</div>
                            <div class="text-[11px] text-slate-500 dark:text-zinc-400 mt-0.5">No sessions</div>
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- Custom CSS for smooth scrolling --}}
<style>
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
</style>
