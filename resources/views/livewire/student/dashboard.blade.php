<?php

use App\Models\ClassSession;
use App\Models\ClassStudent;
use App\Models\ClassModel;
use Livewire\Volt\Component;
use Carbon\Carbon;

new class extends Component {
    public function with(): array
    {
        $student = auth()->user()->student;

        if (!$student) {
            return [
                'todaySchedule' => collect(),
                'upcomingSchedule' => collect(),
                'ongoingSession' => null,
                'stats' => [
                    'activeClasses' => 0,
                    'thisWeekSessions' => 0,
                    'completedSessions' => 0,
                ],
                'recentActivity' => collect(),
            ];
        }

        // Get student's active classes with timetables
        $activeClasses = $student->activeClasses()
            ->with(['course', 'teacher.user', 'timetable', 'sessions' => function($q) {
                $q->whereDate('session_date', today());
            }])
            ->get();

        $activeClassIds = $activeClasses->pluck('id');

        // Build today's schedule from timetables
        $todaySchedule = $this->getTodayScheduleFromTimetables($activeClasses);

        // Get ongoing session (if any)
        $ongoingSession = ClassSession::whereIn('class_id', $activeClassIds)
            ->where('status', 'ongoing')
            ->with(['class.course', 'class.teacher.user'])
            ->first();

        // Get upcoming schedule from timetables (next 7 days)
        $upcomingSchedule = $this->getUpcomingScheduleFromTimetables($activeClasses);

        // Calculate stats
        $stats = [
            'activeClasses' => $activeClasses->count(),
            'thisWeekSessions' => $this->getThisWeekScheduleCount($activeClasses),
            'completedSessions' => ClassSession::whereIn('class_id', $activeClassIds)
                ->whereIn('status', ['completed', 'no_show'])
                ->count(),
        ];

        // Get recent activity (completed sessions)
        $recentActivity = ClassSession::whereIn('class_id', $activeClassIds)
            ->whereIn('status', ['completed', 'no_show'])
            ->with(['class.course'])
            ->orderByDesc('completed_at')
            ->limit(5)
            ->get()
            ->map(function ($session) {
                return [
                    'type' => 'session',
                    'title' => $session->class->title,
                    'description' => __('student.dashboard.session_completed'),
                    'date' => $session->completed_at ?? $session->session_date,
                    'icon' => 'check-circle',
                    'iconColor' => 'text-green-500 dark:text-green-400',
                ];
            });

        return [
            'todaySchedule' => $todaySchedule,
            'upcomingSchedule' => $upcomingSchedule,
            'ongoingSession' => $ongoingSession,
            'stats' => $stats,
            'recentActivity' => $recentActivity,
        ];
    }

    private function getTodayScheduleFromTimetables($classes): \Illuminate\Support\Collection
    {
        $schedule = collect();
        $today = Carbon::today();
        $todayName = strtolower($today->format('l'));

        foreach ($classes as $class) {
            $timetable = $class->timetable;

            // Skip if no timetable or not active
            if (!$timetable || !$timetable->is_active) {
                continue;
            }

            // Check if today is within the timetable date range
            if (!$timetable->isDateWithinRange($today)) {
                continue;
            }

            // Get times for today based on recurrence pattern
            $timesForToday = [];

            if ($timetable->recurrence_pattern === 'monthly') {
                $weekOfMonth = $timetable->getWeekOfMonth($today);
                $weekKey = 'week_' . $weekOfMonth;
                $timesForToday = $timetable->weekly_schedule[$weekKey][$todayName] ?? [];
            } else {
                // Weekly or bi-weekly
                $timesForToday = $timetable->weekly_schedule[$todayName] ?? [];

                // For bi-weekly, check if this is the active week
                if ($timetable->recurrence_pattern === 'bi_weekly') {
                    $weeksSinceStart = $timetable->start_date->diffInWeeks($today);
                    if ($weeksSinceStart % 2 !== 0) {
                        $timesForToday = [];
                    }
                }
            }

            // Get today's sessions for this class
            $todaySessions = $class->sessions->keyBy(function($session) {
                return $session->session_time->format('H:i');
            });

            foreach ($timesForToday as $time) {
                $session = $todaySessions->get($time);

                $schedule->push([
                    'class' => $class,
                    'time' => $time,
                    'session' => $session,
                    'status' => $session ? $session->status : 'scheduled',
                    'is_past' => Carbon::createFromFormat('H:i', $time)->isPast(),
                ]);
            }
        }

        // Sort by time
        return $schedule->sortBy('time')->values();
    }

    private function getUpcomingScheduleFromTimetables($classes): \Illuminate\Support\Collection
    {
        $schedule = collect();
        $today = Carbon::today();

        // Look ahead 7 days
        for ($i = 1; $i <= 7; $i++) {
            $date = $today->copy()->addDays($i);
            $dayName = strtolower($date->format('l'));

            foreach ($classes as $class) {
                $timetable = $class->timetable;

                if (!$timetable || !$timetable->is_active) {
                    continue;
                }

                if (!$timetable->isDateWithinRange($date)) {
                    continue;
                }

                $timesForDay = [];

                if ($timetable->recurrence_pattern === 'monthly') {
                    $weekOfMonth = $timetable->getWeekOfMonth($date);
                    $weekKey = 'week_' . $weekOfMonth;
                    $timesForDay = $timetable->weekly_schedule[$weekKey][$dayName] ?? [];
                } else {
                    $timesForDay = $timetable->weekly_schedule[$dayName] ?? [];

                    if ($timetable->recurrence_pattern === 'bi_weekly') {
                        $weeksSinceStart = $timetable->start_date->diffInWeeks($date);
                        if ($weeksSinceStart % 2 !== 0) {
                            $timesForDay = [];
                        }
                    }
                }

                foreach ($timesForDay as $time) {
                    $schedule->push([
                        'class' => $class,
                        'date' => $date,
                        'time' => $time,
                    ]);
                }
            }
        }

        // Sort by date and time, limit to 5
        return $schedule->sortBy([
            ['date', 'asc'],
            ['time', 'asc'],
        ])->take(5)->values();
    }

    private function getThisWeekScheduleCount($classes): int
    {
        $count = 0;
        $today = Carbon::today();
        $weekStart = $today->copy()->startOfWeek();
        $weekEnd = $today->copy()->endOfWeek();

        foreach ($classes as $class) {
            $timetable = $class->timetable;

            if (!$timetable || !$timetable->is_active) {
                continue;
            }

            $currentDate = $weekStart->copy();
            while ($currentDate <= $weekEnd) {
                if (!$timetable->isDateWithinRange($currentDate)) {
                    $currentDate->addDay();
                    continue;
                }

                $dayName = strtolower($currentDate->format('l'));
                $timesForDay = [];

                if ($timetable->recurrence_pattern === 'monthly') {
                    $weekOfMonth = $timetable->getWeekOfMonth($currentDate);
                    $weekKey = 'week_' . $weekOfMonth;
                    $timesForDay = $timetable->weekly_schedule[$weekKey][$dayName] ?? [];
                } else {
                    $timesForDay = $timetable->weekly_schedule[$dayName] ?? [];

                    if ($timetable->recurrence_pattern === 'bi_weekly') {
                        $weeksSinceStart = $timetable->start_date->diffInWeeks($currentDate);
                        if ($weeksSinceStart % 2 !== 0) {
                            $timesForDay = [];
                        }
                    }
                }

                $count += count($timesForDay);
                $currentDate->addDay();
            }
        }

        return $count;
    }

    public function getGreeting(): string
    {
        $hour = now()->hour;

        if ($hour < 12) {
            return __('student.dashboard.greeting.morning');
        } elseif ($hour < 17) {
            return __('student.dashboard.greeting.afternoon');
        } else {
            return __('student.dashboard.greeting.evening');
        }
    }
}; ?>

<div>
    {{-- Header Section --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <flux:heading size="xl" class="text-gray-900 dark:text-white">{{ $this->getGreeting() }}</flux:heading>
            <flux:text class="mt-2 text-gray-600 dark:text-gray-400">{{ now()->format('l, F j, Y') }}</flux:text>
        </div>
    </div>

    <div class="space-y-6">
        {{-- Live Session Alert --}}
        @if($ongoingSession)
            <div class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-lg p-2.5 sm:p-3 lg:p-4">
                <div class="flex items-center justify-between gap-2.5 sm:gap-3 lg:gap-4">
                    <div class="flex items-center gap-2 lg:gap-3 min-w-0">
                        <div class="w-2 h-2 sm:w-2.5 sm:h-2.5 lg:w-3 lg:h-3 bg-green-500 rounded-full animate-ping flex-shrink-0"></div>
                        <div class="min-w-0">
                            <p class="font-semibold text-[13px] sm:text-sm lg:text-base text-green-800 dark:text-green-300">{{ __('student.dashboard.live_now') }}</p>
                            <p class="text-[11px] sm:text-xs lg:text-sm text-green-700 dark:text-green-400 truncate">{{ $ongoingSession->class->title }}</p>
                        </div>
                    </div>
                    @if($ongoingSession->class->meeting_url)
                        <a href="{{ $ongoingSession->class->meeting_url }}" target="_blank"
                           class="px-2.5 py-1 sm:px-3 sm:py-1.5 lg:px-4 lg:py-2 bg-green-600 text-white text-[13px] sm:text-sm lg:text-base font-medium rounded-lg hover:bg-green-700 transition-colors flex-shrink-0">
                            {{ __('student.dashboard.join') }}
                        </a>
                    @endif
                </div>
            </div>
        @endif

        {{-- Today's Schedule (Timetable-based) --}}
        <flux:card class="!p-0 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 dark:border-zinc-700">
                <div class="flex items-center justify-between">
                    <flux:heading size="sm" class="text-gray-900 dark:text-white">{{ __('student.dashboard.today_schedule') }}</flux:heading>
                    <flux:text size="sm" class="text-gray-500 dark:text-gray-400">{{ trans_choice('student.dashboard.session', $todaySchedule->count(), ['count' => $todaySchedule->count()]) }}</flux:text>
                </div>
            </div>

            @if($todaySchedule->isEmpty())
                <x-student.empty-state type="no-sessions-today" class="py-8" />
            @else
                <div class="divide-y divide-gray-100 dark:divide-zinc-700">
                    @foreach($todaySchedule as $slot)
                        @php
                            $timeCarbon = \Carbon\Carbon::createFromFormat('H:i', $slot['time']);
                        @endphp
                        <div class="px-4 py-3 hover:bg-gray-50 dark:hover:bg-zinc-800 transition-colors">
                            <div class="flex items-center gap-4">
                                {{-- Time --}}
                                <div class="flex-shrink-0 text-center min-w-[60px]">
                                    <flux:text class="text-lg font-semibold text-gray-900 dark:text-white">{{ $timeCarbon->format('g:i') }}</flux:text>
                                    <flux:text size="xs" class="text-gray-500 dark:text-gray-400 uppercase">{{ $timeCarbon->format('A') }}</flux:text>
                                </div>

                                {{-- Session Info --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-4">
                                        <div class="min-w-0">
                                            <flux:text class="font-medium truncate text-gray-900 dark:text-white">{{ $slot['class']->title }}</flux:text>
                                            <flux:text size="sm" class="text-gray-500 dark:text-gray-400 truncate">{{ $slot['class']->course->name }}</flux:text>
                                        </div>

                                        {{-- Status Badge --}}
                                        @if($slot['status'] === 'ongoing')
                                            <div class="flex items-center gap-1.5">
                                                <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                                                <flux:badge color="green" size="sm">{{ __('student.status.live') }}</flux:badge>
                                            </div>
                                        @elseif($slot['status'] === 'completed' || $slot['status'] === 'no_show')
                                            <flux:badge color="zinc" size="sm">{{ __('student.status.done') }}</flux:badge>
                                        @elseif($slot['is_past'])
                                            <flux:badge color="amber" size="sm">{{ __('student.status.missed') }}</flux:badge>
                                        @else
                                            <flux:badge color="blue" size="sm">{{ __('student.status.upcoming') }}</flux:badge>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="px-4 py-3 bg-gray-50 dark:bg-zinc-800 border-t border-gray-100 dark:border-zinc-700">
                <a href="{{ route('student.timetable') }}" wire:navigate
                   class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 flex items-center justify-center gap-1">
                    {{ __('student.dashboard.view_full_schedule') }}
                    <flux:icon name="chevron-right" class="w-4 h-4" />
                </a>
            </div>
        </flux:card>

        {{-- Quick Stats --}}
        <div class="grid grid-cols-3 gap-4">
            <flux:card class="text-center">
                <flux:heading size="lg" class="text-blue-600 dark:text-blue-400">{{ $stats['activeClasses'] }}</flux:heading>
                <flux:text size="xs" class="text-gray-500 dark:text-gray-400 mt-1">{{ __('student.stats.active_classes') }}</flux:text>
            </flux:card>
            <flux:card class="text-center">
                <flux:heading size="lg" class="text-purple-600 dark:text-purple-400">{{ $stats['thisWeekSessions'] }}</flux:heading>
                <flux:text size="xs" class="text-gray-500 dark:text-gray-400 mt-1">{{ __('student.stats.this_week') }}</flux:text>
            </flux:card>
            <flux:card class="text-center">
                <flux:heading size="lg" class="text-green-600 dark:text-green-400">{{ $stats['completedSessions'] }}</flux:heading>
                <flux:text size="xs" class="text-gray-500 dark:text-gray-400 mt-1">{{ __('student.stats.completed') }}</flux:text>
            </flux:card>
        </div>

        {{-- Upcoming Schedule (from Timetable) --}}
        @if($upcomingSchedule->isNotEmpty())
            <flux:card class="!p-0 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-zinc-700">
                    <flux:heading size="sm" class="text-gray-900 dark:text-white">{{ __('student.dashboard.upcoming_schedule') }}</flux:heading>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-zinc-700">
                    @foreach($upcomingSchedule as $slot)
                        @php
                            $timeCarbon = \Carbon\Carbon::createFromFormat('H:i', $slot['time']);
                        @endphp
                        <a href="{{ route('student.classes.show', $slot['class']) }}" wire:navigate
                           class="block px-4 py-3 hover:bg-gray-50 dark:hover:bg-zinc-800 transition-colors">
                            <div class="flex items-center gap-4">
                                {{-- Date Badge --}}
                                <div class="flex-shrink-0 w-12 h-12 bg-blue-50 dark:bg-blue-900/30 rounded-lg flex flex-col items-center justify-center">
                                    <flux:text size="xs" class="text-blue-600 dark:text-blue-400 font-medium uppercase leading-none">{{ $slot['date']->format('M') }}</flux:text>
                                    <flux:text class="text-lg font-bold text-blue-700 dark:text-blue-300 leading-none">{{ $slot['date']->format('j') }}</flux:text>
                                </div>

                                {{-- Session Info --}}
                                <div class="flex-1 min-w-0">
                                    <flux:text class="font-medium truncate text-gray-900 dark:text-white">{{ $slot['class']->title }}</flux:text>
                                    <flux:text size="sm" class="text-gray-500 dark:text-gray-400">
                                        {{ $timeCarbon->format('g:i A') }}
                                        @if($slot['class']->duration_minutes)
                                            &bull; {{ $slot['class']->duration_minutes }} min
                                        @endif
                                    </flux:text>
                                </div>

                                <flux:icon name="chevron-right" class="w-5 h-5 text-gray-400 dark:text-gray-500" />
                            </div>
                        </a>
                    @endforeach
                </div>
            </flux:card>
        @endif

        {{-- Recent Activity --}}
        @if($recentActivity->isNotEmpty())
            <flux:card class="!p-0 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-zinc-700">
                    <flux:heading size="sm" class="text-gray-900 dark:text-white">{{ __('student.dashboard.recent_activity') }}</flux:heading>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-zinc-700">
                    @foreach($recentActivity as $activity)
                        <div class="px-4 py-3">
                            <div class="flex items-center gap-4">
                                <div class="flex-shrink-0 w-8 h-8 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center">
                                    <flux:icon name="{{ $activity['icon'] }}" class="w-4 h-4 {{ $activity['iconColor'] }}" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <flux:text class="font-medium truncate text-gray-900 dark:text-white">{{ $activity['title'] }}</flux:text>
                                    <flux:text size="sm" class="text-gray-500 dark:text-gray-400">{{ $activity['description'] }}</flux:text>
                                </div>
                                <flux:text size="xs" class="text-gray-400 dark:text-gray-500 flex-shrink-0">{{ $activity['date']->diffForHumans(short: true) }}</flux:text>
                            </div>
                        </div>
                    @endforeach
                </div>
            </flux:card>
        @endif

        {{-- Quick Actions --}}
        <div class="grid grid-cols-2 gap-4">
            <flux:card as="a" href="{{ route('student.classes.index') }}" wire:navigate class="hover:bg-gray-50 dark:hover:bg-zinc-700 transition-colors">
                <flux:icon name="academic-cap" class="w-7 h-7 text-blue-500 dark:text-blue-400 mb-2" />
                <flux:text class="font-medium text-gray-900 dark:text-white">{{ __('student.quick_actions.my_classes') }}</flux:text>
                <flux:text size="xs" class="text-gray-500 dark:text-gray-400">{{ __('student.quick_actions.view_enrolled') }}</flux:text>
            </flux:card>
            <flux:card as="a" href="{{ route('student.courses') }}" wire:navigate class="hover:bg-gray-50 dark:hover:bg-zinc-700 transition-colors">
                <flux:icon name="book-open" class="w-7 h-7 text-purple-500 dark:text-purple-400 mb-2" />
                <flux:text class="font-medium text-gray-900 dark:text-white">{{ __('student.quick_actions.browse_courses') }}</flux:text>
                <flux:text size="xs" class="text-gray-500 dark:text-gray-400">{{ __('student.quick_actions.find_new') }}</flux:text>
            </flux:card>
        </div>
    </div>
</div>
