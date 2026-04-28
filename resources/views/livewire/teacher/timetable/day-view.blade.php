{{-- Day View --}}
<div class="teacher-card p-5 sm:p-6">
    @php
        $allDaySessions = collect($timeSlots)->flatMap(fn ($slot) => $slot['sessions']);
        $totalCount = $allDaySessions->count();
        $ongoingCount = $allDaySessions->where('status', 'ongoing')->count();
        $completedCount = $allDaySessions->where('status', 'completed')->count();
    @endphp

    {{-- Header --}}
    <div class="flex items-start justify-between mb-5 gap-3">
        <div class="min-w-0">
            <h2 class="teacher-display text-xl font-bold text-slate-900 dark:text-white">Day Schedule</h2>
            <p class="text-sm text-slate-500 dark:text-zinc-400 mt-0.5">
                @if($totalCount > 0)
                    <span class="font-semibold text-slate-700 dark:text-zinc-200">{{ $totalCount }}</span> {{ Str::plural('session', $totalCount) }}
                    @if($ongoingCount > 0)
                        <span class="mx-1.5 text-slate-300 dark:text-zinc-600">·</span>
                        <span class="inline-flex items-center gap-1 font-semibold text-emerald-600 dark:text-emerald-400">
                            <span class="teacher-live-dot"></span>
                            {{ $ongoingCount }} live
                        </span>
                    @endif
                    @if($completedCount > 0)
                        <span class="mx-1.5 text-slate-300 dark:text-zinc-600">·</span>
                        <span class="font-semibold text-slate-500 dark:text-zinc-400">{{ $completedCount }} done</span>
                    @endif
                @else
                    No sessions scheduled
                @endif
            </p>
        </div>
        @if($ongoingCount > 0)
            <span class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 px-3 py-1 text-xs font-bold ring-1 ring-emerald-200/70 dark:ring-emerald-800/50">
                <span class="teacher-live-dot"></span>
                In session
            </span>
        @endif
    </div>

    {{-- ──────────────────────────────────────────────
         DESKTOP TIMELINE
         ────────────────────────────────────────────── --}}
    <div class="hidden md:block">
        @forelse($timeSlots as $timeSlot)
            <div class="relative flex gap-4 py-3 border-b border-slate-100 dark:border-zinc-800/60 last:border-b-0">
                {{-- Time column --}}
                <div class="w-16 shrink-0 pt-1">
                    <div class="text-xs font-mono font-semibold text-violet-600 dark:text-violet-400 tabular-nums">
                        {{ $timeSlot['displayTime'] }}
                    </div>
                </div>

                {{-- Sessions column --}}
                <div class="flex-1 min-w-0 min-h-[44px]">
                    @if($timeSlot['sessions']->count() > 0)
                        <div class="space-y-2">
                            @foreach($timeSlot['sessions'] as $session)
                                @php
                                    $isOngoing = $session->status === 'ongoing';
                                    $isCompleted = $session->status === 'completed';
                                    $isCancelled = $session->status === 'cancelled';
                                    $isNoShow = $session->status === 'no_show';

                                    $accent = match(true) {
                                        $isOngoing   => 'border-l-emerald-500',
                                        $isCompleted => 'border-l-slate-300 dark:border-l-zinc-700',
                                        $isCancelled => 'border-l-rose-500',
                                        $isNoShow    => 'border-l-amber-500',
                                        default      => 'border-l-violet-500',
                                    };
                                    $tone = match(true) {
                                        $isOngoing   => 'bg-emerald-50/70 dark:bg-emerald-950/20 ring-emerald-200/60 dark:ring-emerald-800/40',
                                        $isCompleted => 'bg-slate-50 dark:bg-zinc-900/40 ring-slate-200/60 dark:ring-zinc-800',
                                        $isCancelled => 'bg-rose-50/60 dark:bg-rose-950/20 ring-rose-200/60 dark:ring-rose-900/40',
                                        $isNoShow    => 'bg-amber-50/60 dark:bg-amber-950/20 ring-amber-200/60 dark:ring-amber-900/40',
                                        default      => 'bg-white dark:bg-zinc-900/40 ring-slate-200/70 dark:ring-zinc-800',
                                    };
                                @endphp

                                <div wire:key="day-sess-{{ $session->id }}"
                                     class="group relative flex flex-col lg:flex-row lg:items-center gap-3 rounded-xl border-l-4 {{ $accent }} {{ $tone }} ring-1 px-4 py-3 hover:shadow-md hover:-translate-y-px transition-all cursor-pointer"
                                     wire:click="selectSession({{ $session->id }})">

                                    {{-- Class info --}}
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <h3 class="font-semibold text-slate-900 dark:text-white truncate">
                                                {{ $session->class->title }}
                                            </h3>
                                            <x-teacher.status-pill :status="$session->status" size="sm" />
                                        </div>
                                        <div class="mt-1 flex items-center gap-3 flex-wrap text-xs text-slate-500 dark:text-zinc-400">
                                            <span class="inline-flex items-center gap-1">
                                                <flux:icon name="sparkles" class="w-3.5 h-3.5 text-violet-500/80" />
                                                <span class="truncate">{{ $session->class->course->title ?? $session->class->course->name }}</span>
                                            </span>
                                            <span class="inline-flex items-center gap-1">
                                                <flux:icon name="clock" class="w-3.5 h-3.5" />
                                                <span class="teacher-num font-mono">
                                                    {{ $session->session_time->format('g:i A') }} – {{ $session->session_time->copy()->addMinutes($session->duration_minutes)->format('g:i A') }}
                                                </span>
                                                <span class="text-slate-400 dark:text-zinc-500">({{ $session->formatted_duration }})</span>
                                            </span>
                                            @if($session->attendances->count() > 0)
                                                <span class="inline-flex items-center gap-1">
                                                    <flux:icon name="check" class="w-3.5 h-3.5" />
                                                    {{ $session->attendances->count() }} {{ Str::plural('student', $session->attendances->count()) }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Live timer --}}
                                    @if($isOngoing)
                                        <div class="inline-flex items-center gap-2 rounded-lg bg-emerald-100 dark:bg-emerald-500/15 px-2.5 py-1.5 ring-1 ring-emerald-200/70 dark:ring-emerald-800/50 self-start lg:self-auto"
                                             x-data="{
                                                elapsedTime: 0,
                                                timer: null,
                                                formatTime(seconds) {
                                                    const hours = Math.floor(seconds / 3600);
                                                    const minutes = Math.floor((seconds % 3600) / 60);
                                                    const secs = seconds % 60;
                                                    if (hours > 0) {
                                                        return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
                                                    }
                                                    return `${minutes}:${secs.toString().padStart(2, '0')}`;
                                                }
                                             }"
                                             x-init="
                                                const startTime = new Date('{{ $session->started_at ? $session->started_at->toISOString() : now()->toISOString() }}').getTime();
                                                elapsedTime = Math.floor((Date.now() - startTime) / 1000);
                                                timer = setInterval(() => {
                                                    elapsedTime = Math.floor((Date.now() - startTime) / 1000);
                                                }, 1000);
                                             "
                                             x-destroy="timer && clearInterval(timer)"
                                             wire:click.stop>
                                            <span class="teacher-live-dot"></span>
                                            <span class="teacher-num font-mono font-bold text-xs text-emerald-700 dark:text-emerald-300" x-text="formatTime(elapsedTime)"></span>
                                        </div>
                                    @endif

                                    {{-- Actions --}}
                                    <div class="flex items-center gap-2 shrink-0 lg:opacity-0 lg:group-hover:opacity-100 transition-opacity">
                                        @if($session->isScheduled())
                                            <button
                                                type="button"
                                                wire:click.stop="requestStartSession({{ $session->id }})"
                                                class="inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-r from-violet-700 to-violet-600 px-3 py-1.5 text-xs font-semibold text-white shadow-md shadow-violet-500/25 hover:shadow-lg hover:from-violet-600 hover:to-violet-400 transition"
                                            >
                                                <flux:icon name="play" class="w-3.5 h-3.5" />
                                                Start
                                            </button>
                                        @elseif($session->isOngoing())
                                            <button
                                                type="button"
                                                wire:click.stop="completeSession({{ $session->id }})"
                                                class="inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-r from-emerald-500 to-teal-600 px-3 py-1.5 text-xs font-semibold text-white shadow-md shadow-emerald-500/25 hover:shadow-lg transition"
                                            >
                                                <flux:icon name="check" class="w-3.5 h-3.5" />
                                                Complete
                                            </button>
                                        @endif

                                        <button
                                            type="button"
                                            wire:click.stop="selectSession({{ $session->id }})"
                                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold text-slate-600 dark:text-zinc-300 ring-1 ring-slate-200 dark:ring-zinc-700 hover:bg-slate-50 dark:hover:bg-zinc-800 transition"
                                        >
                                            <flux:icon name="eye" class="w-3.5 h-3.5" />
                                            View
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-xs text-slate-300 dark:text-zinc-600 italic pt-1.5">
                            —
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <x-teacher.empty-state
                icon="calendar-days"
                title="No time slots available"
                message="The day timeline could not be built. Try switching dates or refresh." />
        @endforelse
    </div>

    {{-- ──────────────────────────────────────────────
         MOBILE CARD LIST
         ────────────────────────────────────────────── --}}
    <div class="md:hidden">
        @php
            $activeSessions = collect($timeSlots)->filter(fn ($slot) => $slot['sessions']->count() > 0);
        @endphp

        @if($activeSessions->isNotEmpty())
            <div class="space-y-3">
                @foreach($activeSessions as $timeSlot)
                    @foreach($timeSlot['sessions'] as $session)
                        @php
                            $isOngoing = $session->status === 'ongoing';
                            $isCompleted = $session->status === 'completed';
                            $isCancelled = $session->status === 'cancelled';
                            $isNoShow = $session->status === 'no_show';

                            $accent = match(true) {
                                $isOngoing   => 'border-l-emerald-500',
                                $isCompleted => 'border-l-slate-300 dark:border-l-zinc-700',
                                $isCancelled => 'border-l-rose-500',
                                $isNoShow    => 'border-l-amber-500',
                                default      => 'border-l-violet-500',
                            };
                            $tone = match(true) {
                                $isOngoing   => 'bg-emerald-50/70 dark:bg-emerald-950/20 ring-emerald-200/60 dark:ring-emerald-800/40',
                                $isCompleted => 'bg-slate-50 dark:bg-zinc-900/40 ring-slate-200/60 dark:ring-zinc-800',
                                $isCancelled => 'bg-rose-50/60 dark:bg-rose-950/20 ring-rose-200/60 dark:ring-rose-900/40',
                                $isNoShow    => 'bg-amber-50/60 dark:bg-amber-950/20 ring-amber-200/60 dark:ring-amber-900/40',
                                default      => 'bg-white dark:bg-zinc-900/40 ring-slate-200/70 dark:ring-zinc-800',
                            };
                        @endphp

                        <div wire:key="day-mob-{{ $session->id }}"
                             class="rounded-xl border-l-4 {{ $accent }} {{ $tone }} ring-1 px-4 py-3.5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <div class="text-xs font-mono font-semibold text-violet-600 dark:text-violet-400 tabular-nums">
                                        {{ $session->session_time->format('g:i A') }}
                                    </div>
                                    <h3 class="mt-1 font-semibold text-slate-900 dark:text-white truncate">
                                        {{ $session->class->title }}
                                    </h3>
                                    <p class="text-xs text-slate-500 dark:text-zinc-400 truncate mt-0.5">
                                        {{ $session->class->course->title ?? $session->class->course->name }}
                                    </p>
                                    <div class="mt-2 flex items-center gap-3 text-xs text-slate-500 dark:text-zinc-400 flex-wrap">
                                        <span class="inline-flex items-center gap-1">
                                            <flux:icon name="clock" class="w-3.5 h-3.5" />
                                            {{ $session->formatted_duration }}
                                        </span>
                                        @if($session->attendances->count() > 0)
                                            <span class="inline-flex items-center gap-1">
                                                <flux:icon name="check" class="w-3.5 h-3.5" />
                                                {{ $session->attendances->count() }} {{ Str::plural('student', $session->attendances->count()) }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <div class="shrink-0 flex flex-col items-end gap-2">
                                    <x-teacher.status-pill :status="$session->status" size="sm" />

                                    @if($isOngoing)
                                        <div class="inline-flex items-center gap-1.5 rounded-md bg-emerald-100 dark:bg-emerald-500/15 px-2 py-0.5 ring-1 ring-emerald-200/70 dark:ring-emerald-800/50"
                                             x-data="{
                                                elapsedTime: 0,
                                                timer: null,
                                                formatTime(seconds) {
                                                    const hours = Math.floor(seconds / 3600);
                                                    const minutes = Math.floor((seconds % 3600) / 60);
                                                    const secs = seconds % 60;
                                                    if (hours > 0) {
                                                        return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
                                                    }
                                                    return `${minutes}:${secs.toString().padStart(2, '0')}`;
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
                                            <span class="teacher-num font-mono font-bold text-[11px] text-emerald-700 dark:text-emerald-300" x-text="formatTime(elapsedTime)"></span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-3 pt-3 border-t border-slate-200/70 dark:border-zinc-800 flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    @if($session->isScheduled())
                                        <button
                                            type="button"
                                            wire:click="requestStartSession({{ $session->id }})"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-r from-violet-700 to-violet-600 px-3 py-1.5 text-xs font-semibold text-white shadow-md shadow-violet-500/25 hover:shadow-lg hover:from-violet-600 hover:to-violet-400 transition"
                                        >
                                            <flux:icon name="play" class="w-3.5 h-3.5" />
                                            Start
                                        </button>
                                    @elseif($session->isOngoing())
                                        <button
                                            type="button"
                                            wire:click="completeSession({{ $session->id }})"
                                            class="inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-r from-emerald-500 to-teal-600 px-3 py-1.5 text-xs font-semibold text-white shadow-md shadow-emerald-500/25 hover:shadow-lg transition"
                                        >
                                            <flux:icon name="check" class="w-3.5 h-3.5" />
                                            Complete
                                        </button>
                                    @endif
                                </div>

                                <button
                                    type="button"
                                    wire:click="selectSession({{ $session->id }})"
                                    class="inline-flex items-center gap-1 text-xs font-semibold text-violet-600 dark:text-violet-400 hover:text-violet-700 dark:hover:text-violet-300 transition"
                                >
                                    Details
                                    <flux:icon name="arrow-right" class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>
                    @endforeach
                @endforeach
            </div>
        @else
            <x-teacher.empty-state
                icon="calendar-days"
                title="No sessions today"
                message="You don't have any sessions scheduled for this day. Take a well-deserved break!" />
        @endif
    </div>
</div>
