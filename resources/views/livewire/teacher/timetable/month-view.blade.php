{{-- Month View — violet/emerald/amber/rose/sky palette --}}
@php
    $sessionsByDay = isset($sessions) && $sessions
        ? $sessions->groupBy(fn ($s) => $s->session_date->format('Y-m-d'))
        : collect();

    $statusDotClass = [
        'ongoing'   => 'bg-emerald-500',
        'completed' => 'bg-violet-500',
        'scheduled' => 'bg-sky-500',
        'no_show'   => 'bg-amber-500',
        'cancelled' => 'bg-rose-500',
    ];
@endphp

{{-- Desktop / tablet calendar grid --}}
<div class="teacher-card p-5 sm:p-6 hidden md:block">
    <div class="mb-5 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <div class="rounded-lg bg-violet-500/10 dark:bg-violet-400/15 p-2">
                <flux:icon name="calendar-days" class="w-5 h-5 text-violet-600 dark:text-violet-300" />
            </div>
            <div>
                <h3 class="teacher-display text-base font-bold text-slate-900 dark:text-white">
                    {{ $this->currentDate->format('F Y') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-zinc-400">Monthly overview</p>
            </div>
        </div>

        {{-- Legend --}}
        <div class="hidden lg:flex items-center gap-3 text-[11px] font-semibold text-slate-500 dark:text-zinc-400">
            <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-500"></span>Ongoing</span>
            <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-violet-500"></span>Completed</span>
            <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-sky-500"></span>Scheduled</span>
            <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-amber-500"></span>No-show</span>
            <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-rose-500"></span>Cancelled</span>
        </div>
    </div>

    {{-- Day-of-week header row --}}
    <div class="grid grid-cols-7 gap-1.5 mb-2">
        @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dayName)
            <div class="text-center py-2">
                <div class="text-xs font-bold uppercase tracking-wider text-violet-600 dark:text-violet-400">
                    {{ $dayName }}
                </div>
            </div>
        @endforeach
    </div>

    {{-- Calendar Body --}}
    <div class="space-y-1.5">
        @foreach($weeks as $week)
            <div class="grid grid-cols-7 gap-1.5">
                @foreach($week as $day)
                    @php
                        $dateKey = $day['date']->format('Y-m-d');
                        $daySessions = $sessionsByDay->get($dateKey, collect());
                        $statusCounts = $daySessions->groupBy('status')->map->count();
                        $orderedStatuses = ['ongoing', 'completed', 'scheduled', 'no_show', 'cancelled'];
                        $visibleStatuses = collect($orderedStatuses)->filter(fn ($s) => $statusCounts->has($s))->take(3)->values();
                    @endphp

                    <div wire:key="month-{{ $dateKey }}"
                         class="group relative rounded-lg p-2 sm:p-2.5 min-h-[88px] border transition-colors
                                {{ $day['isToday']
                                    ? 'ring-2 ring-violet-500/40 bg-violet-50 dark:bg-violet-500/10 border-violet-200 dark:border-violet-500/30'
                                    : ($day['isCurrentMonth']
                                        ? 'bg-white dark:bg-zinc-900/40 border-slate-200/70 dark:border-white/5 hover:border-violet-300 dark:hover:border-violet-500/40'
                                        : 'bg-slate-50/60 dark:bg-zinc-900/20 border-transparent') }}">

                        {{-- Date number + count chip --}}
                        <div class="flex items-start justify-between">
                            <span class="teacher-num inline-flex items-center justify-center text-sm font-semibold leading-none
                                       {{ $day['isToday']
                                            ? 'h-6 w-6 rounded-full bg-gradient-to-br from-violet-600 to-violet-500 text-white shadow-sm shadow-violet-500/40'
                                            : ($day['isCurrentMonth']
                                                ? 'text-slate-900 dark:text-white'
                                                : 'text-slate-400 dark:text-zinc-600') }}">
                                {{ $day['dayNumber'] }}
                            </span>

                            @if($day['sessionCount'] >= 4)
                                <span class="teacher-num inline-flex items-center rounded-full bg-violet-100 dark:bg-violet-500/20 px-1.5 py-0.5 text-[10px] font-bold text-violet-700 dark:text-violet-200">
                                    {{ $day['sessionCount'] }}
                                </span>
                            @elseif($day['sessionCount'] > 0)
                                <span class="teacher-num text-[10px] font-bold text-violet-600 dark:text-violet-300">
                                    {{ $day['sessionCount'] }}
                                </span>
                            @endif
                        </div>

                        {{-- Status dots (max 3) --}}
                        @if($day['sessionCount'] > 0)
                            <div class="absolute bottom-2 left-2 right-2 flex items-center justify-center gap-1">
                                @if($day['sessionCount'] >= 4)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-violet-500/10 dark:bg-violet-400/15 px-2 py-0.5 text-[10px] font-semibold text-violet-700 dark:text-violet-200">
                                        <flux:icon name="bolt" class="w-2.5 h-2.5" />
                                        {{ $day['sessionCount'] }} sessions
                                    </span>
                                @else
                                    @foreach($visibleStatuses as $status)
                                        <span class="w-1.5 h-1.5 rounded-full {{ $statusDotClass[$status] ?? 'bg-violet-500' }}"></span>
                                    @endforeach
                                    @if($visibleStatuses->isEmpty())
                                        @for($i = 0; $i < min($day['sessionCount'], 3); $i++)
                                            <span class="w-1.5 h-1.5 rounded-full bg-violet-500"></span>
                                        @endfor
                                    @endif
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
</div>

{{-- Mobile alternative — vertical card list per day with sessions --}}
<div class="md:hidden space-y-3">
    <div class="teacher-card p-4">
        <div class="flex items-center gap-2.5">
            <div class="rounded-lg bg-violet-500/10 dark:bg-violet-400/15 p-2">
                <flux:icon name="calendar-days" class="w-5 h-5 text-violet-600 dark:text-violet-300" />
            </div>
            <div>
                <h3 class="teacher-display text-base font-bold text-slate-900 dark:text-white">
                    {{ $this->currentDate->format('F Y') }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-zinc-400">
                    {{ $sessions->count() }} {{ Str::plural('session', $sessions->count()) }} this month
                </p>
            </div>
        </div>
    </div>

    @if($sessions->count() > 0)
        @php
            $mobileGrouped = $sessions->groupBy(fn ($s) => $s->session_date->format('Y-m-d'))->take(12);
        @endphp

        @foreach($mobileGrouped as $date => $daySessions)
            @php
                $dateObj = \Carbon\Carbon::parse($date);
                $isToday = $dateObj->isToday();
            @endphp
            <div wire:key="month-mobile-{{ $date }}"
                 class="teacher-card p-4 {{ $isToday ? 'ring-2 ring-violet-500/40' : '' }}">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <div class="flex flex-col items-center justify-center rounded-lg w-12 h-12 shrink-0
                                    {{ $isToday
                                        ? 'bg-gradient-to-br from-violet-600 to-violet-500 text-white shadow-md shadow-violet-500/30'
                                        : 'bg-violet-50 dark:bg-violet-500/15 text-violet-700 dark:text-violet-200' }}">
                            <span class="text-[10px] font-bold uppercase tracking-wider opacity-80">
                                {{ $dateObj->format('M') }}
                            </span>
                            <span class="teacher-num text-base font-bold leading-none">
                                {{ $dateObj->format('j') }}
                            </span>
                        </div>
                        <div>
                            <div class="teacher-display text-sm font-bold text-slate-900 dark:text-white">
                                {{ $dateObj->format('l') }}
                            </div>
                            <div class="text-xs text-slate-500 dark:text-zinc-400">
                                {{ $daySessions->count() }} {{ Str::plural('session', $daySessions->count()) }}
                            </div>
                        </div>
                    </div>
                    @if($isToday)
                        <span class="inline-flex items-center gap-1 rounded-full bg-violet-100 dark:bg-violet-500/20 px-2 py-0.5 text-[10px] font-bold text-violet-700 dark:text-violet-200 uppercase tracking-wider">
                            <flux:icon name="sparkles" class="w-3 h-3" />
                            Today
                        </span>
                    @endif
                </div>

                <div class="space-y-1.5">
                    @foreach($daySessions->take(3) as $session)
                        @php
                            $statusBorder = match($session->status) {
                                'ongoing'   => 'border-l-emerald-500',
                                'completed' => 'border-l-violet-500',
                                'scheduled' => 'border-l-sky-500',
                                'no_show'   => 'border-l-amber-500',
                                'cancelled' => 'border-l-rose-500',
                                default     => 'border-l-violet-500',
                            };
                        @endphp
                        <div class="border-l-2 {{ $statusBorder }} pl-3 py-1">
                            <div class="text-xs font-semibold text-slate-900 dark:text-white truncate">
                                {{ $session->class->title ?? 'Class' }}
                            </div>
                            <div class="teacher-num text-[11px] text-slate-500 dark:text-zinc-400">
                                {{ $session->session_time->format('g:i A') }}
                            </div>
                        </div>
                    @endforeach
                    @if($daySessions->count() > 3)
                        <div class="pl-3 text-[11px] font-semibold text-violet-600 dark:text-violet-300">
                            +{{ $daySessions->count() - 3 }} more
                        </div>
                    @endif
                </div>
            </div>
        @endforeach

        @if($sessions->groupBy(fn ($s) => $s->session_date->format('Y-m-d'))->count() > 12)
            <div class="text-center text-xs font-semibold text-slate-500 dark:text-zinc-400 py-2">
                And {{ $sessions->groupBy(fn ($s) => $s->session_date->format('Y-m-d'))->count() - 12 }} more days...
            </div>
        @endif
    @else
        <x-teacher.empty-state
            icon="calendar"
            title="No sessions this month"
            message="Switch months or check the week view to see upcoming classes." />
    @endif
</div>
