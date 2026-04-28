{{-- List View --}}
<div class="teacher-app">
    @php
        $statusBorderMap = [
            'scheduled' => 'border-l-violet-500',
            'ongoing'   => 'border-l-emerald-500',
            'completed' => 'border-l-slate-300 dark:border-l-zinc-700',
            'cancelled' => 'border-l-rose-500',
            'no_show'   => 'border-l-amber-500',
        ];
    @endphp

    @if($sessions->isNotEmpty())
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <h2 class="teacher-display text-xl font-bold text-slate-900 dark:text-white">Upcoming sessions</h2>
                <p class="text-sm text-slate-500 dark:text-zinc-400 mt-0.5">
                    {{ $sessions->count() }} {{ Str::plural('session', $sessions->count()) }} on the horizon
                </p>
            </div>
            <span class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-violet-100 dark:bg-violet-500/15 text-violet-700 dark:text-violet-300 px-3 py-1 text-xs font-semibold">
                <flux:icon name="sparkles" class="w-3.5 h-3.5" />
                {{ now()->format('l, j M') }}
            </span>
        </div>
    @endif

    <div class="space-y-3">
        @forelse($sessions as $index => $session)
            @php
                $statusKey = $session->status;
                $borderClass = $statusBorderMap[$statusKey] ?? 'border-l-violet-500';
                $isOngoing = $session->isOngoing();
                $isScheduled = $session->isScheduled();
                $isCompleted = $session->isCompleted();
                $cardTone = $isOngoing
                    ? 'bg-emerald-50/70 dark:bg-emerald-950/20 ring-emerald-200/60 dark:ring-emerald-800/40'
                    : ($isCompleted
                        ? 'bg-slate-50/80 dark:bg-zinc-900/40 ring-slate-200/70 dark:ring-zinc-800'
                        : ($statusKey === 'cancelled'
                            ? 'bg-rose-50/60 dark:bg-rose-950/20 ring-rose-200/50 dark:ring-rose-900/40'
                            : ($statusKey === 'no_show'
                                ? 'bg-amber-50/60 dark:bg-amber-950/20 ring-amber-200/50 dark:ring-amber-900/40'
                                : 'bg-white dark:bg-zinc-900/40 ring-slate-200/70 dark:ring-zinc-800')));
                $studentCount = $session->attendances->count();
                $relativeLabel = $session->session_date->isToday()
                    ? 'today at ' . $session->session_time->format('g:i A')
                    : ($session->session_date->isTomorrow()
                        ? 'tomorrow at ' . $session->session_time->format('g:i A')
                        : ($session->session_date->isFuture()
                            ? $session->session_date->diffForHumans()
                            : $session->session_date->diffForHumans()));
            @endphp

            <div
                wire:key="list-session-{{ $session->id }}"
                wire:click="selectSession({{ $session->id }})"
                class="teacher-card teacher-card-hover group relative flex flex-col sm:flex-row sm:items-center gap-4 rounded-xl border-l-4 {{ $borderClass }} {{ $cardTone }} ring-1 px-4 py-4 sm:px-5 cursor-pointer transition-all"
            >
                {{-- Date tile --}}
                <div class="shrink-0 flex sm:flex-col items-center sm:items-start gap-3 sm:gap-0 sm:w-[68px]">
                    <div class="rounded-xl bg-white dark:bg-zinc-900 ring-1 ring-violet-100 dark:ring-violet-900/40 px-3 py-2 sm:py-2.5 shadow-sm text-center min-w-[60px]">
                        <div class="teacher-display text-[10px] font-bold tracking-[0.18em] uppercase text-violet-600 dark:text-violet-400 leading-none">
                            {{ $session->session_date->format('M') }}
                        </div>
                        <div class="teacher-display teacher-num text-2xl font-bold text-slate-900 dark:text-white leading-tight mt-0.5">
                            {{ $session->session_date->format('j') }}
                        </div>
                        <div class="text-[10px] text-slate-400 dark:text-zinc-500 leading-none mt-0.5">
                            {{ $session->session_date->format('Y') }}
                        </div>
                    </div>
                </div>

                {{-- Class info --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-start gap-2 flex-wrap">
                        <h3 class="font-semibold text-slate-900 dark:text-white truncate group-hover:text-violet-600 dark:group-hover:text-violet-300 transition-colors">
                            {{ $session->class->title }}
                        </h3>
                        <x-teacher.status-pill :status="$statusKey" />

                        @if($isOngoing)
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 ring-1 ring-emerald-400/30 px-2 py-0.5 text-[10px] font-mono font-bold">
                                {{ $session->formatted_elapsed_time }}
                            </span>
                        @endif
                    </div>

                    <p class="text-xs text-slate-500 dark:text-zinc-400 mt-1 truncate">
                        {{ $session->class->course->title ?? $session->class->course->name }}
                    </p>

                    <div class="mt-2.5 flex items-center gap-4 text-xs text-slate-500 dark:text-zinc-400 flex-wrap">
                        <span class="inline-flex items-center gap-1">
                            <flux:icon name="clock" class="w-3.5 h-3.5 text-violet-500/70 dark:text-violet-400/70" />
                            <span class="teacher-num font-medium text-slate-700 dark:text-zinc-200">{{ $session->session_time->format('g:i A') }}</span>
                            <span class="text-slate-400 dark:text-zinc-500">·</span>
                            <span>{{ $session->formatted_duration }}</span>
                        </span>

                        @if($studentCount > 0)
                            <span class="inline-flex items-center gap-1">
                                <flux:icon name="users" class="w-3.5 h-3.5 text-violet-500/70 dark:text-violet-400/70" />
                                <span class="teacher-num">{{ $studentCount }}</span>
                                {{ Str::plural('student', $studentCount) }}
                            </span>
                        @endif

                        @if($isScheduled && $session->session_date->isFuture())
                            <span class="inline-flex items-center gap-1">
                                <flux:icon name="calendar" class="w-3.5 h-3.5 text-violet-500/70 dark:text-violet-400/70" />
                                {{ $relativeLabel }}
                            </span>
                        @elseif($isOngoing)
                            <span class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-medium">
                                <flux:icon name="bolt" class="w-3.5 h-3.5" />
                                in session
                            </span>
                        @endif
                    </div>

                    @if($session->teacher_notes)
                        <p class="mt-2 text-xs text-slate-500 dark:text-zinc-400 italic truncate">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500 not-italic mr-1">Notes</span>
                            {{ Str::limit($session->teacher_notes, 90) }}
                        </p>
                    @endif
                </div>

                {{-- Attendance avatar preview --}}
                @if($studentCount > 0)
                    <div class="shrink-0 hidden md:flex items-center -space-x-2">
                        @foreach($session->attendances->take(4) as $idx => $attendance)
                            @php
                                $variant = ($idx % 6) + 1;
                                $initials = collect(explode(' ', trim($attendance->student->user->name)))
                                    ->take(2)
                                    ->map(fn ($p) => strtoupper(substr($p, 0, 1)))
                                    ->join('');
                            @endphp
                            <div class="teacher-avatar teacher-avatar-{{ $variant }} ring-2 ring-white dark:ring-zinc-900" wire:key="att-{{ $session->id }}-{{ $attendance->id }}">
                                {{ $initials }}
                            </div>
                        @endforeach
                        @if($studentCount > 4)
                            <div class="teacher-avatar bg-slate-200 dark:bg-zinc-700 text-slate-600 dark:text-zinc-200 ring-2 ring-white dark:ring-zinc-900">
                                +{{ $studentCount - 4 }}
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Actions --}}
                <div class="shrink-0 flex items-center gap-2 sm:ml-auto">
                    @if($isScheduled)
                        <button
                            type="button"
                            wire:click.stop="requestStartSession({{ $session->id }})"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-r from-violet-700 to-violet-600 px-3.5 py-2 text-xs font-semibold text-white shadow-md shadow-violet-500/25 hover:shadow-lg hover:from-violet-600 hover:to-violet-400 transition"
                        >
                            <flux:icon name="play" class="w-3.5 h-3.5" />
                            Start
                        </button>
                    @elseif($isOngoing)
                        <button
                            type="button"
                            wire:click.stop="completeSession({{ $session->id }})"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-gradient-to-r from-emerald-500 to-teal-600 px-3.5 py-2 text-xs font-semibold text-white shadow-md shadow-emerald-500/25 hover:shadow-lg transition"
                        >
                            <flux:icon name="check" class="w-3.5 h-3.5" />
                            Complete
                        </button>
                    @endif

                    <button
                        type="button"
                        wire:click.stop="selectSession({{ $session->id }})"
                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold text-slate-600 dark:text-zinc-300 ring-1 ring-slate-200 dark:ring-zinc-700 hover:bg-slate-50 dark:hover:bg-zinc-800 transition"
                    >
                        <flux:icon name="eye" class="w-3.5 h-3.5" />
                        <span class="hidden sm:inline">View</span>
                    </button>

                    <flux:icon
                        name="arrow-right"
                        class="w-4 h-4 text-slate-300 dark:text-zinc-600 group-hover:text-violet-500 dark:group-hover:text-violet-400 group-hover:translate-x-0.5 transition-all hidden lg:block"
                    />
                </div>
            </div>
        @empty
            <x-teacher.empty-state
                icon="calendar"
                title="No upcoming sessions"
                message="You don't have any sessions scheduled. Your upcoming sessions will appear here once they're created."
            >
                <button
                    type="button"
                    wire:click="$set('currentView', 'week')"
                    class="teacher-cta"
                >
                    <flux:icon name="calendar" class="w-4 h-4" />
                    View calendar
                </button>
            </x-teacher.empty-state>
        @endforelse
    </div>

    {{-- Load More (if there are many sessions) --}}
    @if($sessions->count() >= 50)
        <div class="text-center py-6 mt-4">
            <p class="text-sm text-slate-500 dark:text-zinc-400 inline-flex items-center gap-1.5">
                <flux:icon name="sparkles" class="w-4 h-4 text-violet-500" />
                Showing first 50 sessions. Use filters to narrow down results.
            </p>
        </div>
    @endif
</div>
