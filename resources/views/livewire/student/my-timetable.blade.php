<?php

use App\Models\ClassSession;
use App\Models\ClassStudent;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public Carbon $currentDate;
    public string $classFilter = 'all';
    public bool $showModal = false;
    public ?ClassSession $selectedSession = null;

    public function mount()
    {
        $this->currentDate = Carbon::now();
    }

    public function previousPeriod()
    {
        $this->currentDate->subWeek();
    }

    public function nextPeriod()
    {
        $this->currentDate->addWeek();
    }

    public function goToToday()
    {
        $this->currentDate = Carbon::now();
    }

    public function selectSession(ClassSession $session)
    {
        $this->selectedSession = $session;
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->selectedSession = null;
    }

    public function with()
    {
        $student = auth()->user()->student;

        if (! $student) {
            return [
                'sessions' => collect(),
                'classes' => collect(),
                'statistics' => $this->getEmptyStatistics(),
            ];
        }

        $classes = $student->activeClasses()->with('course', 'teacher.user')->get();
        $sessions = $this->getSessionsForCurrentView($student);

        return [
            'sessions' => $sessions,
            'classes' => $classes,
            'statistics' => $this->getStatistics($student, $sessions),
            'currentPeriodLabel' => $this->getCurrentPeriodLabel(),
            'calendarData' => $this->getCalendarData($sessions),
            'isCurrentWeek' => $this->currentDate->isSameWeek(Carbon::now()),
        ];
    }

    private function getSessionsForCurrentView($student)
    {
        $query = ClassSession::with(['class.course', 'class.teacher.user', 'attendances' => function ($q) use ($student) {
            $q->where('student_id', $student->id);
        }, 'attendances.student.user'])
            ->whereHas('class.students', function ($q) use ($student) {
                $q->where('class_students.student_id', $student->id)
                    ->where('class_students.status', 'active');
            });

        // Apply class filter
        if ($this->classFilter !== 'all') {
            $query->where('class_id', $this->classFilter);
        }

        // Apply date range for week view
        $startOfWeek = $this->currentDate->copy()->startOfWeek();
        $endOfWeek = $this->currentDate->copy()->endOfWeek();
        $query->whereBetween('session_date', [$startOfWeek, $endOfWeek]);

        return $query->orderBy('session_date')->orderBy('session_time')->get();
    }

    private function getStatistics($student, $sessions)
    {
        $now = Carbon::now();

        // Sessions this week
        $weekStart = $now->copy()->startOfWeek();
        $weekEnd = $now->copy()->endOfWeek();
        $sessionsThisWeek = ClassSession::whereHas('class.students', function ($q) use ($student) {
            $q->where('class_students.student_id', $student->id)->where('class_students.status', 'active');
        })->whereBetween('session_date', [$weekStart, $weekEnd])->count();

        // Upcoming sessions
        $upcomingSessions = ClassSession::whereHas('class.students', function ($q) use ($student) {
            $q->where('class_students.student_id', $student->id)->where('class_students.status', 'active');
        })->where('session_date', '>=', $now->startOfDay())
            ->where('status', 'scheduled')->count();

        return [
            'sessions_this_week' => $sessionsThisWeek,
            'upcoming_sessions' => $upcomingSessions,
        ];
    }

    private function getEmptyStatistics()
    {
        return [
            'sessions_this_week' => 0,
            'upcoming_sessions' => 0,
        ];
    }

    private function getCurrentPeriodLabel()
    {
        $start = $this->currentDate->copy()->startOfWeek();
        $end = $this->currentDate->copy()->endOfWeek();

        return $start->format('M d') . ' - ' . $end->format('M d, Y');
    }

    private function getCalendarData($sessions)
    {
        return $this->getWeekData($sessions);
    }

    private function getWeekData($sessions)
    {
        $weekStart = $this->currentDate->copy()->startOfWeek();
        $student = auth()->user()->student;
        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->copy()->addDays($i);
            $dayName = strtolower($date->format('l'));
            $daySessions = $sessions->filter(function ($session) use ($date) {
                return $session->session_date->isSameDay($date);
            })->sortBy('session_time')->values();

            // Get all scheduled times for this day across all student's classes
            $scheduledSlots = [];
            if ($student) {
                foreach ($student->activeClasses as $class) {
                    $timetable = $class->timetable;
                    if ($timetable && $timetable->weekly_schedule && isset($timetable->weekly_schedule[$dayName])) {
                        foreach ($timetable->weekly_schedule[$dayName] as $time) {
                            $scheduledSlots[] = [
                                'time' => $time,
                                'class' => $class,
                                'session' => $daySessions->first(function ($session) use ($time, $class) {
                                    return $session->session_time->format('H:i') === $time
                                        && $session->class_id === $class->id;
                                }),
                            ];
                        }
                    }
                }
            }

            $days[] = [
                'date' => $date,
                'sessions' => $daySessions,
                'scheduledSlots' => collect($scheduledSlots),
                'isToday' => $date->isToday(),
                'dayName' => $date->format('D'),
                'dayNumber' => $date->format('j'),
            ];
        }

        return $days;
    }
}; ?>

<div>
    {{-- Header --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('student.timetable.my_schedule') }}</flux:heading>
            <flux:text class="mt-2 text-gray-600 dark:text-gray-400">{{ $currentPeriodLabel }}</flux:text>
        </div>

        {{-- Today Button (Desktop) --}}
        @if(!$isCurrentWeek)
            <flux:button wire:click="goToToday" variant="primary" size="sm" class="hidden sm:flex">
                <div class="flex items-center justify-center">
                    <flux:icon name="calendar-days" class="w-4 h-4 mr-1" />
                    {{ __('student.timetable.this_week') }}
                </div>
            </flux:button>
        @endif
    </div>

    {{-- Quick Stats --}}
    <div class="grid grid-cols-2 gap-4 mb-6">
        <flux:card class="text-center">
            <flux:heading size="lg" class="text-blue-600 dark:text-blue-400">{{ $statistics['sessions_this_week'] }}</flux:heading>
            <flux:text size="xs" class="text-gray-500 dark:text-gray-400 mt-1">{{ __('student.timetable.this_week') }}</flux:text>
        </flux:card>
        <flux:card class="text-center">
            <flux:heading size="lg" class="text-purple-600 dark:text-purple-400">{{ $statistics['upcoming_sessions'] }}</flux:heading>
            <flux:text size="xs" class="text-gray-500 dark:text-gray-400 mt-1">{{ __('student.stats.upcoming') }}</flux:text>
        </flux:card>
    </div>

    {{-- Week Navigation --}}
    <flux:card class="mb-4">
        <div class="flex items-center justify-between">
            <flux:button wire:click="previousPeriod" variant="ghost" size="sm">
                <flux:icon name="chevron-left" class="w-5 h-5" />
            </flux:button>

            <div class="text-center flex-1">
                <flux:text class="font-medium">{{ $currentPeriodLabel }}</flux:text>
                @if($isCurrentWeek)
                    <flux:badge variant="primary" size="sm" class="mt-1">{{ __('student.timetable.current_week') }}</flux:badge>
                @endif
            </div>

            <flux:button wire:click="nextPeriod" variant="ghost" size="sm">
                <flux:icon name="chevron-right" class="w-5 h-5" />
            </flux:button>
        </div>

        {{-- Class Filter --}}
        @if($classes->count() > 1)
            <div class="mt-4 pt-4 border-t border-gray-100 dark:border-zinc-700">
                <flux:select wire:model.live="classFilter" size="sm">
                    <flux:select.option value="all">{{ __('student.timetable.all_classes') }}</flux:select.option>
                    @foreach($classes as $class)
                        <flux:select.option value="{{ $class->id }}">{{ $class->title }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endif
    </flux:card>

    {{-- Today Button (Mobile, floating) --}}
    @if(!$isCurrentWeek)
        <div class="fixed bottom-24 right-4 z-40 lg:hidden">
            <flux:button wire:click="goToToday" variant="primary" size="sm" class="shadow-lg rounded-full">
                <div class="flex items-center justify-center">
                    <flux:icon name="calendar-days" class="w-4 h-4 mr-1" />
                    {{ __('student.timetable.today') }}
                </div>
            </flux:button>
        </div>
    @endif

    {{-- Week View --}}
    <flux:card class="!p-0 overflow-hidden" wire:poll.30s="$refresh">
        @include('livewire.student.my-timetable.week-view', ['days' => $calendarData])
    </flux:card>

    {{-- Session Details Modal --}}
    <flux:modal wire:model="showModal" class="max-w-lg">
        @if($selectedSession)
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ $selectedSession->class->title }}</flux:heading>
                    <flux:text class="text-gray-500 dark:text-gray-400">{{ $selectedSession->class->course->name }}</flux:text>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <flux:text size="sm" class="text-gray-500 dark:text-gray-400">{{ __('student.timetable.date') }}</flux:text>
                        <flux:text class="font-medium">{{ $selectedSession->session_date->format('M j, Y') }}</flux:text>
                    </div>
                    <div>
                        <flux:text size="sm" class="text-gray-500 dark:text-gray-400">{{ __('student.timetable.time') }}</flux:text>
                        <flux:text class="font-medium">{{ $selectedSession->session_time->format('g:i A') }}</flux:text>
                    </div>
                    <div>
                        <flux:text size="sm" class="text-gray-500 dark:text-gray-400">{{ __('student.timetable.duration') }}</flux:text>
                        <flux:text class="font-medium">{{ $selectedSession->formatted_duration }}</flux:text>
                    </div>
                    <div>
                        <flux:text size="sm" class="text-gray-500 dark:text-gray-400">{{ __('student.classes.status') }}</flux:text>
                        <flux:badge class="{{ $selectedSession->student_status_badge_class }}">
                            {{ $selectedSession->student_status_label }}
                        </flux:badge>
                    </div>
                    <div class="col-span-2">
                        <flux:text size="sm" class="text-gray-500 dark:text-gray-400">{{ __('student.timetable.teacher') }}</flux:text>
                        <flux:text class="font-medium">{{ $selectedSession->class->teacher->user->name }}</flux:text>
                    </div>
                </div>

                @if($selectedSession->isOngoing())
                    <div class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-lg p-4">
                        <div class="flex items-center gap-2">
                            <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                            <flux:text class="font-medium text-green-800 dark:text-green-300">{{ __('student.timetable.session_in_progress') }}</flux:text>
                        </div>
                    </div>
                @endif

                @if($selectedSession->teacher_notes)
                    <div>
                        <flux:text size="sm" class="text-gray-500 dark:text-gray-400 mb-1">{{ __('student.timetable.session_notes') }}</flux:text>
                        <flux:text class="text-gray-700 dark:text-gray-300">{{ $selectedSession->teacher_notes }}</flux:text>
                    </div>
                @endif
            </div>

            <div class="mt-6 flex justify-end">
                <flux:button wire:click="closeModal" variant="ghost">{{ __('student.timetable.close') }}</flux:button>
            </div>
        @endif
    </flux:modal>
</div>
