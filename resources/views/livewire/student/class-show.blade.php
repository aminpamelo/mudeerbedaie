<?php
use App\Models\ClassModel;
use App\Models\ClassSession;
use App\Models\ClassStudent;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Carbon\Carbon;

new class extends Component {
    public ClassModel $class;

    #[Url(as: 'tab')]
    public string $activeTab = 'overview';

    public string $currentView = 'week';
    public Carbon $currentDate;
    public bool $showModal = false;
    public ?ClassSession $selectedSession = null;

    public function mount(ClassModel $class)
    {
        $this->class = $class;
        $this->currentDate = Carbon::now();
        
        // Verify student has access to this class
        $student = auth()->user()->student;
        $classStudent = ClassStudent::where('class_id', $class->id)
            ->where('student_id', $student->id)
            ->first();
            
        if (!$classStudent) {
            abort(403, 'You do not have access to this class.');
        }
    }
    
    public function setActiveTab(string $tab)
    {
        $this->activeTab = $tab;
    }
    
    public function previousPeriod()
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
    
    public function nextPeriod()
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
    
    public function with(): array
    {
        $student = auth()->user()->student;
        
        // Get student's enrollment in this class
        $classStudent = ClassStudent::where('class_id', $this->class->id)
            ->where('student_id', $student->id)
            ->first();
        
        // Get class sessions
        $sessions = $this->class->sessions()
            ->with(['attendances' => function($q) use ($student) {
                $q->where('student_id', $student->id);
            }])
            ->orderBy('session_date')
            ->orderBy('session_time')
            ->get();
        
        // Get sessions for timetable view
        $timetableSessions = $this->getSessionsForCurrentView();
        
        // Calculate statistics
        $statistics = [
            'total_sessions' => $sessions->count(),
            'completed_sessions' => $sessions->where('status', 'completed')->count(),
            'upcoming_sessions' => $sessions->where('session_date', '>=', now()->toDateString())
                ->where('status', 'scheduled')->count(),
            'attended_sessions' => $sessions->filter(function($session) {
                return $session->attendances->where('status', 'present')->count() > 0;
            })->count(),
        ];
        
        return [
            'classStudent' => $classStudent,
            'sessions' => $sessions,
            'timetableSessions' => $timetableSessions,
            'statistics' => $statistics,
            'calendarData' => $this->getCalendarData($timetableSessions),
            'currentPeriodLabel' => $this->getCurrentPeriodLabel(),
        ];
    }
    
    private function getSessionsForCurrentView()
    {
        $student = auth()->user()->student;
        $query = $this->class->sessions()
            ->with(['attendances' => function($q) use ($student) {
                $q->where('student_id', $student->id);
            }]);
        
        // Apply date range based on view
        switch ($this->currentView) {
            case 'week':
                $startOfWeek = $this->currentDate->copy()->startOfWeek();
                $endOfWeek = $this->currentDate->copy()->endOfWeek();
                $query->whereBetween('session_date', [$startOfWeek, $endOfWeek]);
                break;
            case 'month':
                $startOfMonth = $this->currentDate->copy()->startOfMonth();
                $endOfMonth = $this->currentDate->copy()->endOfMonth();
                $query->whereBetween('session_date', [$startOfMonth, $endOfMonth]);
                break;
            case 'day':
                $query->whereDate('session_date', $this->currentDate);
                break;
        }
        
        return $query->orderBy('session_date')->orderBy('session_time')->get();
    }
    
    private function getCurrentPeriodLabel()
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
    
    private function getCalendarData($sessions)
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
    
    private function getWeekData($sessions)
    {
        $weekStart = $this->currentDate->copy()->startOfWeek();
        $days = [];
        
        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->copy()->addDays($i);
            $dayName = strtolower($date->format('l'));
            $daySessions = $sessions->filter(function($session) use ($date) {
                return $session->session_date->isSameDay($date);
            })->sortBy('session_time')->values();
            
            // Get scheduled slots from timetable
            $scheduledSlots = [];
            $timetable = $this->class->timetable;
            if ($timetable && $timetable->weekly_schedule && isset($timetable->weekly_schedule[$dayName])) {
                foreach ($timetable->weekly_schedule[$dayName] as $time) {
                    $scheduledSlots[] = [
                        'time' => $time,
                        'session' => $daySessions->first(function($session) use ($time) {
                            return $session->session_time->format('H:i') === $time;
                        })
                    ];
                }
            }
            
            $days[] = [
                'date' => $date,
                'sessions' => $daySessions,
                'scheduledSlots' => collect($scheduledSlots),
                'isToday' => $date->isToday(),
                'dayName' => $date->format('D'),
                'dayNumber' => $date->format('j')
            ];
        }
        
        return $days;
    }
    
    private function getMonthData($sessions)
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
                $sessionCount = $sessions->filter(function($session) use ($date) {
                    return $session->session_date->isSameDay($date);
                })->count();
                
                $week[] = [
                    'date' => $date,
                    'sessionCount' => $sessionCount,
                    'isCurrentMonth' => $date->month === $this->currentDate->month,
                    'isToday' => $date->isToday(),
                    'dayNumber' => $date->format('j')
                ];
            }
            $weeks[] = $week;
            $currentWeekStart->addWeek();
        }
        
        return $weeks;
    }
    
    private function getDayData($sessions)
    {
        $timeSlots = [];
        $startHour = 6;
        $endHour = 22;
        
        for ($hour = $startHour; $hour <= $endHour; $hour++) {
            $time = sprintf('%02d:00', $hour);
            $slotSessions = $sessions->filter(function($session) use ($hour) {
                return $session->session_time->format('H') == $hour;
            });
            
            $timeSlots[] = [
                'time' => $time,
                'displayTime' => Carbon::createFromFormat('H:i', $time)->format('g A'),
                'sessions' => $slotSessions
            ];
        }
        
        return $timeSlots;
    }
}; ?>

<div>
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center gap-4 mb-4">
            <flux:button
                href="{{ route('student.classes.index') }}"
                variant="ghost"
                size="sm"
            >
                <div class="flex items-center justify-center">
                    <flux:icon name="chevron-left" class="w-4 h-4 mr-1" />
                    {{ __('student.classes.back_to_classes') }}
                </div>
            </flux:button>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
            <div class="flex-1 min-w-0">
                <flux:heading size="xl" class="text-gray-900 dark:text-white">{{ $class->title }}</flux:heading>
                <flux:text class="mt-1 text-gray-600 dark:text-gray-400">{{ $class->course->name }}</flux:text>
            </div>

            <div class="flex items-center gap-2 flex-shrink-0">
                @if($classStudent->status === 'active')
                    <flux:badge color="green">{{ ucfirst($classStudent->status) }}</flux:badge>
                @elseif($classStudent->status === 'completed')
                    <flux:badge color="zinc">{{ ucfirst($classStudent->status) }}</flux:badge>
                @else
                    <flux:badge color="amber">{{ ucfirst($classStudent->status) }}</flux:badge>
                @endif
            </div>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div class="mb-6">
        <div class="border-b border-gray-200 dark:border-zinc-700">
            <nav class="-mb-px flex space-x-1 sm:space-x-6 overflow-x-auto scrollbar-hide">
                <button
                    wire:click="setActiveTab('overview')"
                    class="py-3 px-3 sm:px-1 border-b-2 font-medium text-sm whitespace-nowrap transition-colors
                        {{ $activeTab === 'overview'
                            ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                            : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-zinc-600' }}"
                >
                    {{ __('student.classes.overview') }}
                </button>

                <button
                    wire:click="setActiveTab('timetable')"
                    class="py-3 px-3 sm:px-1 border-b-2 font-medium text-sm whitespace-nowrap transition-colors
                        {{ $activeTab === 'timetable'
                            ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                            : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-zinc-600' }}"
                >
                    {{ __('student.classes.timetable') }}
                </button>

                <button
                    wire:click="setActiveTab('sessions')"
                    class="py-3 px-3 sm:px-1 border-b-2 font-medium text-sm whitespace-nowrap transition-colors
                        {{ $activeTab === 'sessions'
                            ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                            : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-zinc-600' }}"
                >
                    {{ __('student.classes.sessions_tab') }}
                </button>
            </nav>
        </div>
    </div>

    <!-- Tab Content -->
    <div class="space-y-6">
        @if($activeTab === 'overview')
            @include('livewire.student.class-show.overview', [
                'class' => $class,
                'classStudent' => $classStudent,
                'statistics' => $statistics
            ])
        @elseif($activeTab === 'timetable')
            @include('livewire.student.class-show.timetable', [
                'class' => $class,
                'sessions' => $timetableSessions,
                'calendarData' => $calendarData,
                'currentView' => $currentView,
                'currentPeriodLabel' => $currentPeriodLabel
            ])
        @elseif($activeTab === 'sessions')
            @include('livewire.student.class-show.sessions', [
                'class' => $class,
                'sessions' => $sessions,
                'statistics' => $statistics
            ])
        @endif
    </div>

    <!-- Session Details Modal -->
    <flux:modal wire:model="showModal" class="max-w-lg">
        @if($selectedSession)
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ $class->title }}</flux:heading>
                    <flux:text class="text-gray-500 dark:text-gray-400">{{ __('student.timetable.session_details') }}</flux:text>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <flux:text size="sm" class="text-gray-500 dark:text-gray-400">{{ __('student.timetable.date') }}</flux:text>
                        <flux:text class="font-medium text-gray-900 dark:text-white">
                            {{ $selectedSession->session_date->format('M j, Y') }}
                        </flux:text>
                    </div>
                    <div>
                        <flux:text size="sm" class="text-gray-500 dark:text-gray-400">{{ __('student.timetable.time') }}</flux:text>
                        <flux:text class="font-medium text-gray-900 dark:text-white">
                            {{ $selectedSession->session_time->format('g:i A') }}
                        </flux:text>
                    </div>
                    <div>
                        <flux:text size="sm" class="text-gray-500 dark:text-gray-400">{{ __('student.timetable.duration') }}</flux:text>
                        <flux:text class="font-medium text-gray-900 dark:text-white">
                            {{ $selectedSession->formatted_duration }}
                        </flux:text>
                    </div>
                    <div>
                        <flux:text size="sm" class="text-gray-500 dark:text-gray-400">{{ __('student.classes.status') }}</flux:text>
                        <flux:badge class="{{ $selectedSession->student_status_badge_class }}">
                            {{ $selectedSession->student_status_label }}
                        </flux:badge>
                    </div>
                    <div class="col-span-2">
                        <flux:text size="sm" class="text-gray-500 dark:text-gray-400">{{ __('student.timetable.teacher') }}</flux:text>
                        <flux:text class="font-medium text-gray-900 dark:text-white">
                            {{ $class->teacher->user->name }}
                        </flux:text>
                    </div>
                </div>

                @if($selectedSession->teacher_notes)
                    <div class="pt-4 border-t border-gray-200 dark:border-zinc-700">
                        <flux:text size="sm" class="text-gray-500 dark:text-gray-400 mb-1">{{ __('student.timetable.session_notes') }}</flux:text>
                        <flux:text class="text-gray-700 dark:text-gray-300">
                            {{ $selectedSession->teacher_notes }}
                        </flux:text>
                    </div>
                @endif
            </div>

            <div class="mt-6 flex justify-end">
                <flux:button wire:click="closeModal" variant="ghost">{{ __('student.timetable.close') }}</flux:button>
            </div>
        @endif
    </flux:modal>
</div>