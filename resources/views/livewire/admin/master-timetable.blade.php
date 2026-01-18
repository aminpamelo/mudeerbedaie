<?php

use App\Models\ClassModel;
use App\Models\ClassSession;
use App\Models\ClassCategory;
use App\Models\ClassTimetable;
use App\Models\Teacher;
use Carbon\Carbon;
use Livewire\Volt\Component;

new class extends Component {
    public string $currentView = 'week';
    public string $previousView = '';
    public Carbon $currentDate;

    // Filters
    public string $categoryFilter = 'all';
    public string $classFilter = 'all';
    public string $teacherFilter = 'all';
    public string $statusFilter = 'active'; // Default to active (scheduled + ongoing)

    // Modal state
    public bool $showModal = false;
    public ?ClassSession $selectedSession = null;

    // Color palette for classes (with dark mode support)
    private array $colorPalette = [
        'blue' => ['bg' => 'bg-blue-100 dark:bg-blue-900/50', 'border' => 'border-blue-300 dark:border-blue-700', 'text' => 'text-blue-800 dark:text-blue-300', 'dot' => 'bg-blue-500'],
        'green' => ['bg' => 'bg-green-100 dark:bg-green-900/50', 'border' => 'border-green-300 dark:border-green-700', 'text' => 'text-green-800 dark:text-green-300', 'dot' => 'bg-green-500'],
        'purple' => ['bg' => 'bg-purple-100 dark:bg-purple-900/50', 'border' => 'border-purple-300 dark:border-purple-700', 'text' => 'text-purple-800 dark:text-purple-300', 'dot' => 'bg-purple-500'],
        'amber' => ['bg' => 'bg-amber-100 dark:bg-amber-900/50', 'border' => 'border-amber-300 dark:border-amber-700', 'text' => 'text-amber-800 dark:text-amber-300', 'dot' => 'bg-amber-500'],
        'rose' => ['bg' => 'bg-rose-100 dark:bg-rose-900/50', 'border' => 'border-rose-300 dark:border-rose-700', 'text' => 'text-rose-800 dark:text-rose-300', 'dot' => 'bg-rose-500'],
        'cyan' => ['bg' => 'bg-cyan-100 dark:bg-cyan-900/50', 'border' => 'border-cyan-300 dark:border-cyan-700', 'text' => 'text-cyan-800 dark:text-cyan-300', 'dot' => 'bg-cyan-500'],
        'orange' => ['bg' => 'bg-orange-100 dark:bg-orange-900/50', 'border' => 'border-orange-300 dark:border-orange-700', 'text' => 'text-orange-800 dark:text-orange-300', 'dot' => 'bg-orange-500'],
        'teal' => ['bg' => 'bg-teal-100 dark:bg-teal-900/50', 'border' => 'border-teal-300 dark:border-teal-700', 'text' => 'text-teal-800 dark:text-teal-300', 'dot' => 'bg-teal-500'],
        'indigo' => ['bg' => 'bg-indigo-100 dark:bg-indigo-900/50', 'border' => 'border-indigo-300 dark:border-indigo-700', 'text' => 'text-indigo-800 dark:text-indigo-300', 'dot' => 'bg-indigo-500'],
        'pink' => ['bg' => 'bg-pink-100 dark:bg-pink-900/50', 'border' => 'border-pink-300 dark:border-pink-700', 'text' => 'text-pink-800 dark:text-pink-300', 'dot' => 'bg-pink-500'],
    ];

    public array $classColors = [];

    public function mount(): void
    {
        $this->currentDate = Carbon::now();
        $this->previousView = $this->currentView;
        $this->generateClassColors();
    }

    public function updatedCurrentView($value): void
    {
        if ($this->previousView !== $value) {
            $this->currentDate = Carbon::now();
            $this->previousView = $value;
        }
    }

    public function updatedCategoryFilter(): void
    {
        $this->generateClassColors();
    }

    public function updatedClassFilter(): void
    {
        $this->generateClassColors();
    }

    public function previousPeriod(): void
    {
        switch ($this->currentView) {
            case 'week':
                $this->currentDate->subWeek();
                break;
            case 'month':
                $this->currentDate->subMonth();
                break;
            case 'day':
                $this->currentDate->subDay();
                break;
        }
    }

    public function nextPeriod(): void
    {
        switch ($this->currentView) {
            case 'week':
                $this->currentDate->addWeek();
                break;
            case 'month':
                $this->currentDate->addMonth();
                break;
            case 'day':
                $this->currentDate->addDay();
                break;
        }
    }

    public function goToToday(): void
    {
        $this->currentDate = Carbon::now();
    }

    public function goToDay(string $date): void
    {
        $this->currentDate = Carbon::parse($date);
        $this->currentView = 'day';
    }

    public function selectSession(int $sessionId): void
    {
        $this->selectedSession = ClassSession::with([
            'class.course',
            'class.teacher.user',
            'class.categories',
            'class.pics',
            'class.timetable',
            'attendances.student.user',
            'starter',
            'verifier',
            'assignedTeacher.user'
        ])->find($sessionId);
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->selectedSession = null;
    }

    private function generateClassColors(): void
    {
        $classes = ClassModel::query()
            ->when($this->categoryFilter !== 'all', function ($q) {
                $q->whereHas('categories', fn($q) => $q->where('class_categories.id', $this->categoryFilter));
            })
            ->when($this->classFilter !== 'all', function ($q) {
                $q->where('id', $this->classFilter);
            })
            ->when($this->teacherFilter !== 'all', function ($q) {
                $q->where('teacher_id', $this->teacherFilter);
            })
            ->pluck('id')
            ->toArray();

        $paletteKeys = array_keys($this->colorPalette);
        $this->classColors = [];

        foreach ($classes as $index => $classId) {
            $colorKey = $paletteKeys[$index % count($paletteKeys)];
            $this->classColors[$classId] = $this->colorPalette[$colorKey];
        }
    }

    public function getClassColor(int $classId): array
    {
        return $this->classColors[$classId] ?? $this->colorPalette['blue'];
    }

    public function isClassEnded($session): bool
    {
        if (!$session->class || !$session->class->timetable) {
            return false;
        }

        $timetable = $session->class->timetable;
        if (!$timetable->end_date) {
            return false;
        }

        // Check if the session date is the last day of the class (end date)
        return $session->session_date->isSameDay($timetable->end_date);
    }

    public function hasClassEnded($session): bool
    {
        if (!$session->class || !$session->class->timetable) {
            return false;
        }

        $timetable = $session->class->timetable;
        if (!$timetable->end_date) {
            return false;
        }

        // Check if the class end date has passed
        return Carbon::today()->gte($timetable->end_date);
    }

    public function with(): array
    {
        $classes = ClassModel::with('course', 'categories')->get();
        $categories = ClassCategory::active()->ordered()->get();
        $teachers = Teacher::with('user')->whereHas('user')->get();

        $sessions = $this->getSessionsForCurrentView();
        $statistics = $this->getStatistics();

        return [
            'sessions' => $sessions,
            'classes' => $classes,
            'categories' => $categories,
            'teachers' => $teachers,
            'statistics' => $statistics,
            'currentPeriodLabel' => $this->getCurrentPeriodLabel(),
            'calendarData' => $this->getCalendarData($sessions),
            'activeClasses' => $this->getActiveClassesForLegend(),
        ];
    }

    private function getSessionsForCurrentView()
    {
        $query = ClassSession::with([
            'class.course',
            'class.teacher.user',
            'class.categories',
            'class.pics',
            'class.timetable',
            'attendances'
        ]);

        // Apply date range based on view
        switch ($this->currentView) {
            case 'week':
                $startOfWeek = $this->currentDate->copy()->startOfWeek();
                $endOfWeek = $this->currentDate->copy()->endOfWeek();
                $query->whereBetween('session_date', [$startOfWeek, $endOfWeek]);
                break;
            case 'month':
                $startOfMonth = $this->currentDate->copy()->startOfMonth()->startOfWeek();
                $endOfMonth = $this->currentDate->copy()->endOfMonth()->endOfWeek();
                $query->whereBetween('session_date', [$startOfMonth, $endOfMonth]);
                break;
            case 'day':
                $query->whereDate('session_date', $this->currentDate);
                break;
        }

        // Apply filters
        if ($this->categoryFilter !== 'all') {
            $query->whereHas('class.categories', fn($q) => $q->where('class_categories.id', $this->categoryFilter));
        }

        if ($this->classFilter !== 'all') {
            $query->where('class_id', $this->classFilter);
        }

        if ($this->teacherFilter !== 'all') {
            $query->whereHas('class', fn($q) => $q->where('teacher_id', $this->teacherFilter));
        }

        // Status filtering
        if ($this->statusFilter === 'active') {
            // Show only scheduled and ongoing sessions
            $query->whereIn('status', ['scheduled', 'ongoing']);
        } elseif ($this->statusFilter === 'all') {
            // Show all statuses (scheduled, ongoing, cancelled, completed)
            // For completed: only show when class has ended (timetable end_date has passed)
            $today = Carbon::today();
            $query->where(function ($q) use ($today) {
                // Show scheduled, ongoing, cancelled sessions
                $q->whereIn('status', ['scheduled', 'ongoing', 'cancelled'])
                  // For completed: show only if class timetable end_date has passed
                  ->orWhere(function ($q2) use ($today) {
                      $q2->where('status', 'completed')
                         ->whereHas('class.timetable', function ($tq) use ($today) {
                             $tq->whereNotNull('end_date')
                                ->where('end_date', '<=', $today);
                         });
                  });
            });
        } elseif ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        return $query->orderBy('session_date')->orderBy('session_time')->get();
    }

    private function getStatistics(): array
    {
        $today = Carbon::now()->startOfDay();
        $weekStart = $today->copy()->startOfWeek();
        $weekEnd = $today->copy()->endOfWeek();

        $baseQuery = ClassSession::query();

        // Apply same filters for statistics
        if ($this->categoryFilter !== 'all') {
            $baseQuery->whereHas('class.categories', fn($q) => $q->where('class_categories.id', $this->categoryFilter));
        }
        if ($this->classFilter !== 'all') {
            $baseQuery->where('class_id', $this->classFilter);
        }
        if ($this->teacherFilter !== 'all') {
            $baseQuery->whereHas('class', fn($q) => $q->where('teacher_id', $this->teacherFilter));
        }

        return [
            'today_sessions' => (clone $baseQuery)->whereDate('session_date', $today)->count(),
            'week_sessions' => (clone $baseQuery)->whereBetween('session_date', [$weekStart, $weekEnd])->count(),
            'ongoing_sessions' => (clone $baseQuery)->where('status', 'ongoing')->count(),
            'scheduled_sessions' => (clone $baseQuery)->where('status', 'scheduled')->where('session_date', '>=', $today)->count(),
        ];
    }

    private function getCurrentPeriodLabel(): string
    {
        switch ($this->currentView) {
            case 'week':
                $start = $this->currentDate->copy()->startOfWeek();
                $end = $this->currentDate->copy()->endOfWeek();
                return $start->format('M d') . ' - ' . $end->format('M d, Y');
            case 'month':
                return $this->currentDate->format('F Y');
            case 'day':
                return $this->currentDate->format('l, F d, Y');
            default:
                return '';
        }
    }

    private function getCalendarData($sessions): array
    {
        switch ($this->currentView) {
            case 'week':
                return $this->getWeekData($sessions);
            case 'month':
                return $this->getMonthData($sessions);
            case 'day':
                return $this->getDayData($sessions);
            default:
                return [];
        }
    }

    private function getWeekData($sessions): array
    {
        $weekStart = $this->currentDate->copy()->startOfWeek();
        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->copy()->addDays($i);
            $daySessions = $sessions->filter(function ($session) use ($date) {
                return $session->session_date->isSameDay($date);
            })->sortBy('session_time')->values();

            $days[] = [
                'date' => $date,
                'sessions' => $daySessions,
                'isToday' => $date->isToday(),
                'dayName' => $date->format('D'),
                'dayNumber' => $date->format('j'),
            ];
        }

        return $days;
    }

    private function getMonthData($sessions): array
    {
        $monthStart = $this->currentDate->copy()->startOfMonth();
        $monthEnd = $this->currentDate->copy()->endOfMonth();
        $calendarStart = $monthStart->copy()->startOfWeek();
        $calendarEnd = $monthEnd->copy()->endOfWeek();

        $weeks = [];
        $currentWeekStart = $calendarStart->copy();

        while ($currentWeekStart <= $calendarEnd) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $date = $currentWeekStart->copy()->addDays($i);
                $daySessions = $sessions->filter(function ($session) use ($date) {
                    return $session->session_date->isSameDay($date);
                });

                $week[] = [
                    'date' => $date,
                    'sessions' => $daySessions,
                    'sessionCount' => $daySessions->count(),
                    'isCurrentMonth' => $date->month === $this->currentDate->month,
                    'isToday' => $date->isToday(),
                    'dayNumber' => $date->format('j'),
                ];
            }
            $weeks[] = $week;
            $currentWeekStart->addWeek();
        }

        return $weeks;
    }

    private function getDayData($sessions): array
    {
        $timeSlots = [];
        $startHour = 6;
        $endHour = 22;

        for ($hour = $startHour; $hour <= $endHour; $hour++) {
            $time = sprintf('%02d:00', $hour);
            $slotSessions = $sessions->filter(function ($session) use ($hour) {
                return (int) $session->session_time->format('H') === $hour;
            })->values();

            $timeSlots[] = [
                'time' => $time,
                'displayTime' => Carbon::createFromFormat('H:i', $time)->format('g A'),
                'sessions' => $slotSessions,
            ];
        }

        return $timeSlots;
    }

    private function getActiveClassesForLegend()
    {
        $query = ClassModel::query();

        if ($this->categoryFilter !== 'all') {
            $query->whereHas('categories', fn($q) => $q->where('class_categories.id', $this->categoryFilter));
        }
        if ($this->classFilter !== 'all') {
            $query->where('id', $this->classFilter);
        }
        if ($this->teacherFilter !== 'all') {
            $query->where('teacher_id', $this->teacherFilter);
        }

        return $query->get();
    }
}; ?>

<div>
    <!-- Page Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading size="xl">Master Timetable</flux:heading>
            <flux:text class="mt-2">Consolidated view of all class schedules</flux:text>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <flux:card class="p-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text size="sm" class="text-gray-600 dark:text-zinc-400">Today's Sessions</flux:text>
                    <flux:heading size="lg">{{ $statistics['today_sessions'] }}</flux:heading>
                </div>
                <div class="p-2 bg-blue-50 dark:bg-blue-900/30 rounded-lg">
                    <flux:icon name="calendar" class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                </div>
            </div>
        </flux:card>

        <flux:card class="p-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text size="sm" class="text-gray-600 dark:text-zinc-400">This Week</flux:text>
                    <flux:heading size="lg">{{ $statistics['week_sessions'] }}</flux:heading>
                </div>
                <div class="p-2 bg-green-50 dark:bg-green-900/30 rounded-lg">
                    <flux:icon name="calendar-days" class="w-6 h-6 text-green-600 dark:text-green-400" />
                </div>
            </div>
        </flux:card>

        <flux:card class="p-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text size="sm" class="text-gray-600 dark:text-zinc-400">Ongoing</flux:text>
                    <flux:heading size="lg">{{ $statistics['ongoing_sessions'] }}</flux:heading>
                </div>
                <div class="p-2 bg-yellow-50 dark:bg-yellow-900/30 rounded-lg">
                    <flux:icon name="play-circle" class="w-6 h-6 text-yellow-600 dark:text-yellow-400" />
                </div>
            </div>
        </flux:card>

        <flux:card class="p-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text size="sm" class="text-gray-600 dark:text-zinc-400">Scheduled</flux:text>
                    <flux:heading size="lg">{{ $statistics['scheduled_sessions'] }}</flux:heading>
                </div>
                <div class="p-2 bg-purple-50 dark:bg-purple-900/30 rounded-lg">
                    <flux:icon name="clock" class="w-6 h-6 text-purple-600 dark:text-purple-400" />
                </div>
            </div>
        </flux:card>
    </div>

    <!-- Controls Section -->
    <flux:card class="mb-6 p-4">
        <div class="space-y-4">
            <!-- Row 1: View Toggle and Navigation -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <!-- View Toggle Buttons -->
                <div class="flex items-center gap-1 bg-gray-100 dark:bg-zinc-800 rounded-lg p-1">
                    <flux:button
                        :variant="$currentView === 'week' ? 'primary' : 'ghost'"
                        wire:click="$set('currentView', 'week')"
                        size="sm"
                    >
                        Week
                    </flux:button>
                    <flux:button
                        :variant="$currentView === 'month' ? 'primary' : 'ghost'"
                        wire:click="$set('currentView', 'month')"
                        size="sm"
                    >
                        Month
                    </flux:button>
                    <flux:button
                        :variant="$currentView === 'day' ? 'primary' : 'ghost'"
                        wire:click="$set('currentView', 'day')"
                        size="sm"
                    >
                        Day
                    </flux:button>
                </div>

                <!-- Navigation Controls -->
                <div class="flex items-center gap-2">
                    <flux:button variant="outline" wire:click="previousPeriod" size="sm">
                        <flux:icon name="chevron-left" class="w-4 h-4" />
                    </flux:button>

                    <div class="px-4 py-2 text-sm font-semibold min-w-[180px] text-center bg-gray-50 dark:bg-zinc-800 dark:text-zinc-100 rounded-lg border dark:border-zinc-700">
                        {{ $currentPeriodLabel }}
                    </div>

                    <flux:button variant="outline" wire:click="nextPeriod" size="sm">
                        <flux:icon name="chevron-right" class="w-4 h-4" />
                    </flux:button>

                    <flux:button variant="primary" wire:click="goToToday" size="sm">
                        Today
                    </flux:button>
                </div>
            </div>

            <!-- Row 2: Filters -->
            <div class="border-t dark:border-zinc-700 pt-4">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-zinc-400 mb-1">Category</label>
                        <flux:select wire:model.live="categoryFilter" size="sm">
                            <option value="all">All Categories</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-zinc-400 mb-1">Class</label>
                        <flux:select wire:model.live="classFilter" size="sm">
                            <option value="all">All Classes</option>
                            @foreach($classes as $class)
                                <option value="{{ $class->id }}">{{ $class->title }}</option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-zinc-400 mb-1">Teacher</label>
                        <flux:select wire:model.live="teacherFilter" size="sm">
                            <option value="all">All Teachers</option>
                            @foreach($teachers as $teacher)
                                @if($teacher->user)
                                    <option value="{{ $teacher->id }}">{{ $teacher->user->name }}</option>
                                @endif
                            @endforeach
                        </flux:select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-zinc-400 mb-1">Status</label>
                        <flux:select wire:model.live="statusFilter" size="sm">
                            <option value="active">Active (Scheduled & Ongoing)</option>
                            <option value="all">All Status</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="ongoing">Ongoing</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </flux:select>
                    </div>
                </div>
            </div>
        </div>
    </flux:card>

    <!-- Color Legend -->
    @if($activeClasses->count() > 0 && $activeClasses->count() <= 15)
        <flux:card class="mb-6 p-4">
            <flux:text size="sm" class="font-medium mb-3">Class Colors</flux:text>
            <div class="flex flex-wrap gap-4">
                @foreach($activeClasses as $class)
                    @php $color = $this->getClassColor($class->id); @endphp
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full {{ $color['dot'] }}"></div>
                        <span class="text-xs text-gray-600 dark:text-zinc-400">{{ $class->title }}</span>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 pt-4 border-t dark:border-zinc-700 flex flex-wrap gap-4">
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-green-500 animate-pulse"></div>
                    <span class="text-xs text-gray-600 dark:text-zinc-400">Ongoing</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                    <span class="text-xs text-gray-600 dark:text-zinc-400">Scheduled</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-gray-400"></div>
                    <span class="text-xs text-gray-600 dark:text-zinc-400">Completed</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-red-400"></div>
                    <span class="text-xs text-gray-600 dark:text-zinc-400">Cancelled</span>
                </div>
            </div>
        </flux:card>
    @endif

    <!-- Calendar Content -->
    <flux:card wire:poll.30s="$refresh">
        @if($currentView === 'week')
            @include('livewire.admin.master-timetable.week-view', ['days' => $calendarData])
        @elseif($currentView === 'month')
            @include('livewire.admin.master-timetable.month-view', ['weeks' => $calendarData])
        @elseif($currentView === 'day')
            @include('livewire.admin.master-timetable.day-view', ['timeSlots' => $calendarData])
        @endif
    </flux:card>

    <!-- Session Details Modal - Enhanced -->
    <flux:modal wire:model="showModal" class="max-w-5xl">
        @if($selectedSession)
            {{-- Modal Header --}}
            <div class="p-6 border-b border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800/50">
                <div class="flex items-start justify-between">
                    <div>
                        <flux:heading size="xl">{{ $selectedSession->class->title }}</flux:heading>
                        <flux:text class="mt-1 text-gray-600 dark:text-zinc-400">
                            {{ $selectedSession->class->course?->name ?? 'No Course' }}
                        </flux:text>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:badge size="lg" class="{{ $selectedSession->status_badge_class }}">
                            {{ $selectedSession->status_label }}
                        </flux:badge>
                        @if($this->isClassEnded($selectedSession))
                            <flux:badge size="lg" color="red">Class Ended</flux:badge>
                        @endif
                    </div>
                </div>
            </div>

            <div class="p-6">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {{-- Left Column - Session Details --}}
                    <div class="lg:col-span-2 space-y-6">
                        {{-- Session Info Card --}}
                        <div class="bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-lg p-4">
                            <flux:heading size="sm" class="mb-4">Session Information</flux:heading>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <flux:text class="font-medium text-sm text-gray-500 dark:text-zinc-400">Date & Time</flux:text>
                                    <flux:text class="text-gray-900 dark:text-zinc-100">{{ $selectedSession->formatted_date_time }}</flux:text>
                                </div>
                                <div>
                                    <flux:text class="font-medium text-sm text-gray-500 dark:text-zinc-400">Duration</flux:text>
                                    <flux:text class="text-gray-900 dark:text-zinc-100">{{ $selectedSession->formatted_duration }}</flux:text>
                                    @if($selectedSession->isCompleted() && $selectedSession->formatted_actual_duration)
                                        <flux:text class="text-xs text-gray-500 dark:text-zinc-500">
                                            Actual: {{ $selectedSession->formatted_actual_duration }}
                                            <span class="ml-1 {{ $selectedSession->meetsKpi() ? 'text-green-600' : 'text-red-600' }}">
                                                ({{ $selectedSession->duration_comparison }})
                                            </span>
                                        </flux:text>
                                    @endif
                                </div>
                                <div>
                                    <flux:text class="font-medium text-sm text-gray-500 dark:text-zinc-400">Assigned Teacher</flux:text>
                                    <flux:text class="text-gray-900 dark:text-zinc-100">{{ $selectedSession->class->teacher?->user?->name ?? 'N/A' }}</flux:text>
                                </div>
                                @if($selectedSession->assignedTeacher)
                                    <div>
                                        <flux:text class="font-medium text-sm text-gray-500 dark:text-zinc-400">Substitute Teacher</flux:text>
                                        <flux:text class="text-gray-900 dark:text-zinc-100">{{ $selectedSession->assignedTeacher->user?->name ?? 'N/A' }}</flux:text>
                                    </div>
                                @endif
                                @if($selectedSession->class->pics->count() > 0)
                                    <div class="col-span-2">
                                        <flux:text class="font-medium text-sm text-gray-500 dark:text-zinc-400">PIC</flux:text>
                                        <flux:text class="text-gray-900 dark:text-zinc-100">{{ $selectedSession->class->pics->pluck('name')->join(', ') }}</flux:text>
                                    </div>
                                @endif
                            </div>

                            {{-- Categories --}}
                            @if($selectedSession->class->categories->count() > 0)
                                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-zinc-700">
                                    <flux:text class="font-medium text-sm text-gray-500 dark:text-zinc-400 mb-2">Categories</flux:text>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($selectedSession->class->categories as $category)
                                            <flux:badge size="sm" variant="outline">{{ $category->name }}</flux:badge>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Attendance Card --}}
                        <div class="bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-lg p-4">
                            <flux:heading size="sm" class="mb-4">Attendance Summary</flux:heading>
                            @if($selectedSession->attendances->count() > 0)
                                <div class="grid grid-cols-4 gap-4">
                                    <div class="text-center p-3 bg-gray-50 dark:bg-zinc-700/50 rounded-lg">
                                        <div class="text-2xl font-bold text-gray-900 dark:text-zinc-100">{{ $selectedSession->attendances->count() }}</div>
                                        <div class="text-xs text-gray-500 dark:text-zinc-400">Total</div>
                                    </div>
                                    <div class="text-center p-3 bg-green-50 dark:bg-green-900/30 rounded-lg">
                                        <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $selectedSession->attendances->where('status', 'present')->count() }}</div>
                                        <div class="text-xs text-green-600 dark:text-green-400">Present</div>
                                    </div>
                                    <div class="text-center p-3 bg-yellow-50 dark:bg-yellow-900/30 rounded-lg">
                                        <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ $selectedSession->attendances->where('status', 'late')->count() }}</div>
                                        <div class="text-xs text-yellow-600 dark:text-yellow-400">Late</div>
                                    </div>
                                    <div class="text-center p-3 bg-red-50 dark:bg-red-900/30 rounded-lg">
                                        <div class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $selectedSession->attendances->where('status', 'absent')->count() }}</div>
                                        <div class="text-xs text-red-600 dark:text-red-400">Absent</div>
                                    </div>
                                </div>
                            @else
                                <div class="text-center py-6 text-gray-400 dark:text-zinc-500">
                                    <flux:icon name="users" class="w-8 h-8 mx-auto mb-2 opacity-50" />
                                    <flux:text>No attendance recorded yet</flux:text>
                                </div>
                            @endif
                        </div>

                        {{-- Teacher Notes --}}
                        @if($selectedSession->teacher_notes)
                            <div class="bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-lg p-4">
                                <flux:heading size="sm" class="mb-2">Teacher Notes</flux:heading>
                                <flux:text class="text-gray-700 dark:text-zinc-300 whitespace-pre-wrap">{{ $selectedSession->teacher_notes }}</flux:text>
                            </div>
                        @endif
                    </div>

                    {{-- Right Column - Timeline & Monitoring --}}
                    <div class="space-y-6">
                        {{-- Session Timeline --}}
                        <div class="bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-lg p-4">
                            <flux:heading size="sm" class="mb-4">Session Timeline</flux:heading>
                            <div class="relative">
                                {{-- Timeline Line --}}
                                <div class="absolute left-3.5 top-4 bottom-4 w-0.5 bg-gray-200 dark:bg-zinc-700"></div>

                                <div class="space-y-4">
                                    {{-- Created/Scheduled --}}
                                    <div class="flex items-start gap-3 relative">
                                        <div class="w-7 h-7 rounded-full bg-blue-100 dark:bg-blue-900/50 border-2 border-blue-500 flex items-center justify-center z-10">
                                            <flux:icon name="calendar" class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <flux:text class="font-medium text-gray-900 dark:text-zinc-100">Scheduled</flux:text>
                                            <flux:text class="text-sm text-gray-500 dark:text-zinc-400">
                                                {{ $selectedSession->created_at->format('M d, Y g:i A') }}
                                            </flux:text>
                                        </div>
                                    </div>

                                    {{-- Started --}}
                                    <div class="flex items-start gap-3 relative">
                                        @if($selectedSession->started_at)
                                            <div class="w-7 h-7 rounded-full bg-green-100 dark:bg-green-900/50 border-2 border-green-500 flex items-center justify-center z-10">
                                                <flux:icon name="play" class="w-3.5 h-3.5 text-green-600 dark:text-green-400" />
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <flux:text class="font-medium text-green-700 dark:text-green-400">Started</flux:text>
                                                <flux:text class="text-sm text-gray-500 dark:text-zinc-400">
                                                    {{ $selectedSession->started_at->format('M d, Y g:i A') }}
                                                </flux:text>
                                                @if($selectedSession->starter)
                                                    <flux:text class="text-xs text-gray-500 dark:text-zinc-500">
                                                        By: {{ $selectedSession->starter->name }}
                                                    </flux:text>
                                                @endif
                                            </div>
                                        @else
                                            <div class="w-7 h-7 rounded-full bg-gray-100 dark:bg-zinc-700 border-2 border-gray-300 dark:border-zinc-600 flex items-center justify-center z-10">
                                                <flux:icon name="play" class="w-3.5 h-3.5 text-gray-400 dark:text-zinc-500" />
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <flux:text class="font-medium text-gray-400 dark:text-zinc-500">Not Started</flux:text>
                                                <flux:text class="text-sm text-gray-400 dark:text-zinc-600">Waiting for teacher</flux:text>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Completed --}}
                                    <div class="flex items-start gap-3 relative">
                                        @if($selectedSession->completed_at)
                                            <div class="w-7 h-7 rounded-full bg-purple-100 dark:bg-purple-900/50 border-2 border-purple-500 flex items-center justify-center z-10">
                                                <flux:icon name="check" class="w-3.5 h-3.5 text-purple-600 dark:text-purple-400" />
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <flux:text class="font-medium text-purple-700 dark:text-purple-400">Completed</flux:text>
                                                <flux:text class="text-sm text-gray-500 dark:text-zinc-400">
                                                    {{ $selectedSession->completed_at->format('M d, Y g:i A') }}
                                                </flux:text>
                                            </div>
                                        @else
                                            <div class="w-7 h-7 rounded-full bg-gray-100 dark:bg-zinc-700 border-2 border-gray-300 dark:border-zinc-600 flex items-center justify-center z-10">
                                                <flux:icon name="check" class="w-3.5 h-3.5 text-gray-400 dark:text-zinc-500" />
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <flux:text class="font-medium text-gray-400 dark:text-zinc-500">Not Completed</flux:text>
                                                <flux:text class="text-sm text-gray-400 dark:text-zinc-600">Pending</flux:text>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Verified --}}
                                    <div class="flex items-start gap-3 relative">
                                        @if($selectedSession->verified_at)
                                            <div class="w-7 h-7 rounded-full bg-emerald-100 dark:bg-emerald-900/50 border-2 border-emerald-500 flex items-center justify-center z-10">
                                                <flux:icon name="shield-check" class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <flux:text class="font-medium text-emerald-700 dark:text-emerald-400">Verified</flux:text>
                                                <flux:text class="text-sm text-gray-500 dark:text-zinc-400">
                                                    {{ $selectedSession->verified_at->format('M d, Y g:i A') }}
                                                </flux:text>
                                                @if($selectedSession->verifier)
                                                    <flux:text class="text-xs text-gray-500 dark:text-zinc-500">
                                                        By: {{ $selectedSession->verifier->name }}
                                                    </flux:text>
                                                @endif
                                            </div>
                                        @else
                                            <div class="w-7 h-7 rounded-full bg-gray-100 dark:bg-zinc-700 border-2 border-gray-300 dark:border-zinc-600 flex items-center justify-center z-10">
                                                <flux:icon name="shield-check" class="w-3.5 h-3.5 text-gray-400 dark:text-zinc-500" />
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <flux:text class="font-medium text-gray-400 dark:text-zinc-500">Not Verified</flux:text>
                                                <flux:text class="text-sm text-gray-400 dark:text-zinc-600">Pending review</flux:text>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Teacher Activity Monitoring --}}
                        <div class="bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-lg p-4">
                            <flux:heading size="sm" class="mb-4">Teacher Activity</flux:heading>

                            @php
                                $isStarted = $selectedSession->started_at !== null;
                                $expectedTime = $selectedSession->session_date->copy()->setTimeFromTimeString($selectedSession->session_time->format('H:i:s'));
                                $isPastTime = now()->gt($expectedTime);
                                $isScheduled = $selectedSession->status === 'scheduled';
                            @endphp

                            @if($isStarted)
                                <div class="flex items-center gap-2 p-3 bg-green-50 dark:bg-green-900/30 rounded-lg border border-green-200 dark:border-green-800">
                                    <flux:icon name="check-circle" class="w-5 h-5 text-green-600 dark:text-green-400" />
                                    <div>
                                        <flux:text class="font-medium text-green-700 dark:text-green-400">Session Started</flux:text>
                                        <flux:text class="text-xs text-green-600 dark:text-green-500">
                                            Teacher confirmed attendance
                                        </flux:text>
                                    </div>
                                </div>
                            @elseif($isScheduled && $isPastTime)
                                <div class="flex items-center gap-2 p-3 bg-red-50 dark:bg-red-900/30 rounded-lg border border-red-200 dark:border-red-800">
                                    <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-600 dark:text-red-400" />
                                    <div>
                                        <flux:text class="font-medium text-red-700 dark:text-red-400">Not Started</flux:text>
                                        <flux:text class="text-xs text-red-600 dark:text-red-500">
                                            Session time has passed - teacher hasn't started
                                        </flux:text>
                                    </div>
                                </div>
                            @elseif($isScheduled)
                                <div class="flex items-center gap-2 p-3 bg-blue-50 dark:bg-blue-900/30 rounded-lg border border-blue-200 dark:border-blue-800">
                                    <flux:icon name="clock" class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                                    <div>
                                        <flux:text class="font-medium text-blue-700 dark:text-blue-400">Waiting</flux:text>
                                        <flux:text class="text-xs text-blue-600 dark:text-blue-500">
                                            Session scheduled for {{ $expectedTime->format('g:i A') }}
                                        </flux:text>
                                    </div>
                                </div>
                            @else
                                <div class="flex items-center gap-2 p-3 bg-gray-50 dark:bg-zinc-700/50 rounded-lg border border-gray-200 dark:border-zinc-600">
                                    <flux:icon name="minus-circle" class="w-5 h-5 text-gray-500 dark:text-zinc-400" />
                                    <div>
                                        <flux:text class="font-medium text-gray-600 dark:text-zinc-400">{{ ucfirst($selectedSession->status) }}</flux:text>
                                    </div>
                                </div>
                            @endif

                            {{-- Payout Status --}}
                            @if($selectedSession->isCompleted())
                                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-zinc-700">
                                    <flux:text class="font-medium text-sm text-gray-500 dark:text-zinc-400 mb-2">Payout Status</flux:text>
                                    <div class="flex items-center justify-between">
                                        <flux:badge class="{{ $selectedSession->payout_status_badge_class }}">
                                            {{ $selectedSession->payout_status_label }}
                                        </flux:badge>
                                        @if($selectedSession->allowance_amount)
                                            <flux:text class="font-semibold text-gray-900 dark:text-zinc-100">
                                                RM {{ number_format($selectedSession->allowance_amount, 2) }}
                                            </flux:text>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Class Info --}}
                        @if($selectedSession->class->timetable)
                            <div class="bg-white dark:bg-zinc-800 border border-gray-200 dark:border-zinc-700 rounded-lg p-4">
                                <flux:heading size="sm" class="mb-4">Class Schedule Info</flux:heading>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-500 dark:text-zinc-400">Start Date</span>
                                        <span class="text-gray-900 dark:text-zinc-100">{{ $selectedSession->class->timetable->start_date?->format('M d, Y') ?? 'N/A' }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500 dark:text-zinc-400">End Date</span>
                                        <span class="text-gray-900 dark:text-zinc-100">{{ $selectedSession->class->timetable->end_date?->format('M d, Y') ?? 'N/A' }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500 dark:text-zinc-400">Recurrence</span>
                                        <span class="text-gray-900 dark:text-zinc-100">{{ ucfirst(str_replace('_', ' ', $selectedSession->class->timetable->recurrence_pattern ?? 'weekly')) }}</span>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Modal Footer --}}
            <div class="p-6 border-t border-gray-200 dark:border-zinc-700 bg-gray-50 dark:bg-zinc-800/50 flex justify-between">
                <flux:button variant="primary" href="{{ route('admin.sessions.show', $selectedSession) }}" wire:navigate>
                    <flux:icon name="eye" class="w-4 h-4 mr-1" />
                    View Full Details
                </flux:button>
                <flux:button wire:click="closeModal" variant="ghost">
                    Close
                </flux:button>
            </div>
        @endif
    </flux:modal>
</div>
