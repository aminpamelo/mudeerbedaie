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
    public string $statusFilter = 'all';

    // Modal state
    public bool $showModal = false;
    public ?ClassSession $selectedSession = null;

    // Color palette for classes
    private array $colorPalette = [
        'blue' => ['bg' => 'bg-blue-100', 'border' => 'border-blue-300', 'text' => 'text-blue-800', 'dot' => 'bg-blue-500'],
        'green' => ['bg' => 'bg-green-100', 'border' => 'border-green-300', 'text' => 'text-green-800', 'dot' => 'bg-green-500'],
        'purple' => ['bg' => 'bg-purple-100', 'border' => 'border-purple-300', 'text' => 'text-purple-800', 'dot' => 'bg-purple-500'],
        'amber' => ['bg' => 'bg-amber-100', 'border' => 'border-amber-300', 'text' => 'text-amber-800', 'dot' => 'bg-amber-500'],
        'rose' => ['bg' => 'bg-rose-100', 'border' => 'border-rose-300', 'text' => 'text-rose-800', 'dot' => 'bg-rose-500'],
        'cyan' => ['bg' => 'bg-cyan-100', 'border' => 'border-cyan-300', 'text' => 'text-cyan-800', 'dot' => 'bg-cyan-500'],
        'orange' => ['bg' => 'bg-orange-100', 'border' => 'border-orange-300', 'text' => 'text-orange-800', 'dot' => 'bg-orange-500'],
        'teal' => ['bg' => 'bg-teal-100', 'border' => 'border-teal-300', 'text' => 'text-teal-800', 'dot' => 'bg-teal-500'],
        'indigo' => ['bg' => 'bg-indigo-100', 'border' => 'border-indigo-300', 'text' => 'text-indigo-800', 'dot' => 'bg-indigo-500'],
        'pink' => ['bg' => 'bg-pink-100', 'border' => 'border-pink-300', 'text' => 'text-pink-800', 'dot' => 'bg-pink-500'],
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
            'attendances.student.user'
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

        if ($this->statusFilter !== 'all') {
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
                    <flux:text size="sm" class="text-gray-600">Today's Sessions</flux:text>
                    <flux:heading size="lg">{{ $statistics['today_sessions'] }}</flux:heading>
                </div>
                <div class="p-2 bg-blue-50 rounded-lg">
                    <flux:icon name="calendar" class="w-6 h-6 text-blue-600" />
                </div>
            </div>
        </flux:card>

        <flux:card class="p-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text size="sm" class="text-gray-600">This Week</flux:text>
                    <flux:heading size="lg">{{ $statistics['week_sessions'] }}</flux:heading>
                </div>
                <div class="p-2 bg-green-50 rounded-lg">
                    <flux:icon name="calendar-days" class="w-6 h-6 text-green-600" />
                </div>
            </div>
        </flux:card>

        <flux:card class="p-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text size="sm" class="text-gray-600">Ongoing</flux:text>
                    <flux:heading size="lg">{{ $statistics['ongoing_sessions'] }}</flux:heading>
                </div>
                <div class="p-2 bg-yellow-50 rounded-lg">
                    <flux:icon name="play-circle" class="w-6 h-6 text-yellow-600" />
                </div>
            </div>
        </flux:card>

        <flux:card class="p-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:text size="sm" class="text-gray-600">Scheduled</flux:text>
                    <flux:heading size="lg">{{ $statistics['scheduled_sessions'] }}</flux:heading>
                </div>
                <div class="p-2 bg-purple-50 rounded-lg">
                    <flux:icon name="clock" class="w-6 h-6 text-purple-600" />
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
                <div class="flex items-center gap-1 bg-gray-100 rounded-lg p-1">
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

                    <div class="px-4 py-2 text-sm font-semibold min-w-[180px] text-center bg-gray-50 rounded-lg border">
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
            <div class="border-t pt-4">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Category</label>
                        <flux:select wire:model.live="categoryFilter" size="sm">
                            <option value="all">All Categories</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Class</label>
                        <flux:select wire:model.live="classFilter" size="sm">
                            <option value="all">All Classes</option>
                            @foreach($classes as $class)
                                <option value="{{ $class->id }}">{{ $class->title }}</option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Teacher</label>
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
                        <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                        <flux:select wire:model.live="statusFilter" size="sm">
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
                        <span class="text-xs text-gray-600">{{ $class->title }}</span>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 pt-4 border-t flex flex-wrap gap-4">
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-green-500 animate-pulse"></div>
                    <span class="text-xs text-gray-600">Ongoing</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                    <span class="text-xs text-gray-600">Scheduled</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-gray-400"></div>
                    <span class="text-xs text-gray-600">Completed</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-3 h-3 rounded-full bg-red-400"></div>
                    <span class="text-xs text-gray-600">Cancelled</span>
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

    <!-- Session Details Modal -->
    <flux:modal wire:model="showModal" class="max-w-2xl">
        @if($selectedSession)
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ $selectedSession->class->title }}</flux:heading>
                    <flux:badge class="{{ $selectedSession->status_badge_class }}">
                        {{ $selectedSession->status_label }}
                    </flux:badge>
                </div>
            </div>

            <div class="p-6 space-y-4">
                <!-- Session Details Grid -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <flux:text class="font-medium text-sm text-gray-500">Date & Time</flux:text>
                        <flux:text>{{ $selectedSession->formatted_date_time }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="font-medium text-sm text-gray-500">Duration</flux:text>
                        <flux:text>{{ $selectedSession->formatted_duration }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="font-medium text-sm text-gray-500">Course</flux:text>
                        <flux:text>{{ $selectedSession->class->course?->name ?? 'N/A' }}</flux:text>
                    </div>
                    <div>
                        <flux:text class="font-medium text-sm text-gray-500">Teacher</flux:text>
                        <flux:text>{{ $selectedSession->class->teacher?->user?->name ?? 'N/A' }}</flux:text>
                    </div>
                    @if($selectedSession->class->pics->count() > 0)
                        <div class="col-span-2">
                            <flux:text class="font-medium text-sm text-gray-500">PIC</flux:text>
                            <flux:text>{{ $selectedSession->class->pics->pluck('name')->join(', ') }}</flux:text>
                        </div>
                    @endif
                </div>

                <!-- Categories -->
                @if($selectedSession->class->categories->count() > 0)
                    <div>
                        <flux:text class="font-medium text-sm text-gray-500 mb-2">Categories</flux:text>
                        <div class="flex flex-wrap gap-2">
                            @foreach($selectedSession->class->categories as $category)
                                <flux:badge size="sm">{{ $category->name }}</flux:badge>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Attendance Summary -->
                @if($selectedSession->attendances->count() > 0)
                    <div>
                        <flux:text class="font-medium text-sm text-gray-500 mb-2">
                            Attendance ({{ $selectedSession->attendances->count() }} students)
                        </flux:text>
                        <div class="flex gap-4">
                            <span class="text-green-600">
                                {{ $selectedSession->attendances->where('status', 'present')->count() }} Present
                            </span>
                            <span class="text-yellow-600">
                                {{ $selectedSession->attendances->where('status', 'late')->count() }} Late
                            </span>
                            <span class="text-red-600">
                                {{ $selectedSession->attendances->where('status', 'absent')->count() }} Absent
                            </span>
                        </div>
                    </div>
                @endif
            </div>

            <div class="p-6 border-t border-gray-200 flex justify-between">
                <flux:button variant="ghost" href="{{ route('admin.sessions.show', $selectedSession) }}" wire:navigate>
                    View Full Details
                </flux:button>
                <flux:button wire:click="closeModal" variant="ghost">
                    Close
                </flux:button>
            </div>
        @endif
    </flux:modal>
</div>
